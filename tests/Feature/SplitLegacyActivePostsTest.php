<?php

declare(strict_types=1);

use App\Actions\Post\UpdatePost;
use App\Enums\Post\Status;
use App\Events\PostCreated;
use App\Models\Post;
use App\Models\PostNote;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

test('split preserves IDs and clones labels, media and notes once', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => Status::Scheduled,
        'scheduled_at' => now()->addDay(),
        'content' => 'Legacy shared caption',
        'media' => [['id' => 'media-id', 'path' => 'posts/photo.jpg']],
    ]);
    $accounts = SocialAccount::factory()->count(3)->create(['workspace_id' => $workspace->id]);
    $first = PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $accounts[0]->id]);
    $second = PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $accounts[1]->id]);
    $disabled = PostPlatform::factory()->disabled()->create(['post_id' => $post->id, 'social_account_id' => $accounts[2]->id]);
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id]);
    $post->labels()->attach($label);
    $comment = PostNote::factory()->create(['post_id' => $post->id, 'user_id' => $user->id]);
    $older = PostNote::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'updated_at' => now()->subDays(3),
    ]);

    Event::fake([PostCreated::class]);
    $this->artisan('posts:split-legacy-active')->assertSuccessful();
    $this->artisan('posts:split-legacy-active')->assertSuccessful();

    $clone = Post::whereKeyNot($post->id)->sole();
    expect(Post::count())->toBe(2)
        ->and($first->fresh()->post_id)->toBe($post->id)
        ->and($second->fresh()->post_id)->toBe($clone->id)
        ->and($disabled->fresh()->post_id)->toBe($post->id)
        ->and($clone->content)->toBe($post->content)
        ->and($clone->media)->toEqual($post->media)
        ->and($clone->scheduled_at->equalTo($post->scheduled_at))->toBeTrue()
        ->and($clone->labels()->pluck('workspace_labels.id')->all())->toBe([$label->id])
        ->and($clone->notes()->count())->toBe(2)
        ->and($clone->notes()->where('body', $comment->body)->exists())->toBeTrue()
        ->and($clone->notes()->where('body', $older->body)->sole()->updated_at->equalTo($older->updated_at))->toBeTrue();
    Event::assertNotDispatched(PostCreated::class);
});

test('split leaves settled and in-flight aggregate rows untouched', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $accounts = SocialAccount::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    foreach ([Status::Published, Status::Publishing] as $status) {
        $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => $status]);
        foreach ($accounts as $account) {
            PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
        }
    }

    $this->artisan('posts:split-legacy-active')->assertSuccessful();

    expect(Post::count())->toBe(2)
        ->and(PostPlatform::count())->toBe(4);
});

test('the original post remains editable after a split with a disabled target', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $accounts = SocialAccount::factory()->linkedin()->count(3)->create(['workspace_id' => $workspace->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => Status::Draft,
        'content' => 'Original caption',
        'media' => [],
    ]);
    $first = PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $accounts[0]->id]);
    $second = PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $accounts[1]->id]);
    $disabled = PostPlatform::factory()->disabled()->create(['post_id' => $post->id, 'social_account_id' => $accounts[2]->id]);

    $this->artisan('posts:split-legacy-active')->assertSuccessful();
    UpdatePost::execute($workspace, $post->fresh(), [
        'status' => Status::Draft->value,
        'content' => 'Changed caption',
        'content_type' => $first->content_type->value,
    ]);

    expect($post->fresh()->content)->toBe('Changed caption')
        ->and($second->fresh()->post->content)->toBe('Original caption')
        ->and($disabled->fresh()->enabled)->toBeFalse();

    expect(fn () => UpdatePost::execute($workspace, $post->fresh(), [
        'platforms' => [['id' => $disabled->id]],
    ]))->toThrow(ValidationException::class);
    expect($disabled->fresh()->enabled)->toBeFalse();
});

function legacyAggregatePost(Workspace $workspace, User $user, Status $status, int $targets, array $attributes = []): Post
{
    $post = Post::factory()->create(array_merge([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => $status,
        'scheduled_at' => $status === Status::Scheduled ? now()->addDay() : null,
    ], $attributes));

    foreach (SocialAccount::factory()->count($targets)->create(['workspace_id' => $workspace->id]) as $account) {
        PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
    }

    return $post;
}

test('split posts share one group and a re-run changes nothing', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = legacyAggregatePost($workspace, $user, Status::Scheduled, 3);
    $updatedAt = $post->fresh()->updated_at;

    $this->artisan('posts:split-legacy-active')
        ->expectsOutputToContain('Split 1 original posts and created 2 independent posts.')
        ->assertSuccessful();

    $groups = Post::query()->pluck('post_group_id', 'id');
    $groupId = $groups->get($post->id);

    expect($groups)->toHaveCount(3)
        ->and($groupId)->not->toBeNull()
        ->and($groups->unique()->values()->all())->toBe([$groupId])
        ->and($post->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();

    $this->artisan('posts:split-legacy-active')
        ->expectsOutputToContain('Split 0 original posts and created 0 independent posts.')
        ->expectsOutputToContain('Grouped 0 multi-target posts.')
        ->assertSuccessful();

    expect(Post::count())->toBe(3)
        ->and(Post::query()->pluck('post_group_id', 'id')->all())->toEqual($groups->all());
});

test('split reuses a group the original already has', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $groupId = '0190a3b2-0000-7000-8000-0000000000aa';
    legacyAggregatePost($workspace, $user, Status::Draft, 2, ['post_group_id' => $groupId]);

    $this->artisan('posts:split-legacy-active')->assertSuccessful();

    expect(Post::query()->pluck('post_group_id')->unique()->all())->toBe([$groupId]);
});

test('unsplit multi-target history gets its own group and single-target posts stay ungrouped', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $published = legacyAggregatePost($workspace, $user, Status::Published, 2);
    $failed = legacyAggregatePost($workspace, $user, Status::Failed, 3);
    $partial = legacyAggregatePost($workspace, $user, Status::PartiallyPublished, 2);
    $single = legacyAggregatePost($workspace, $user, Status::Published, 1);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $user->id]);
    $alreadyGrouped = legacyAggregatePost($otherWorkspace, $user, Status::Published, 2, ['post_group_id' => '0190a3b2-0000-7000-8000-0000000000bb']);
    $otherSingle = legacyAggregatePost($otherWorkspace, $user, Status::Draft, 1);

    $this->artisan('posts:split-legacy-active')
        ->expectsOutputToContain('Grouped 3 multi-target posts.')
        ->assertSuccessful();

    $groups = collect([$published, $failed, $partial])->map(fn (Post $post) => $post->fresh()->post_group_id);

    expect($groups->filter()->unique())->toHaveCount(3)
        ->and(Post::count())->toBe(6)
        ->and($single->fresh()->post_group_id)->toBeNull()
        ->and($otherSingle->fresh()->post_group_id)->toBeNull()
        ->and($alreadyGrouped->fresh()->post_group_id)->toBe('0190a3b2-0000-7000-8000-0000000000bb');

    $this->artisan('posts:split-legacy-active')
        ->expectsOutputToContain('Grouped 0 multi-target posts.')
        ->assertSuccessful();

    expect(collect([$published, $failed, $partial])->map(fn (Post $post) => $post->fresh()->post_group_id)->all())->toBe($groups->all());
});
