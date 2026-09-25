<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Events\PostNoteChanged;
use App\Jobs\SendNotification;
use App\Models\Post;
use App\Models\PostNote;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('index returns paginated comments with replies', function () {
    $parent = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $reply = PostNote::factory()->reply($parent)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.posts.notes.index', $this->post));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $parent->id);
    $response->assertJsonCount(1, 'data.0.replies');
    $response->assertJsonPath('data.0.replies.0.id', $reply->id);
});

test('store creates a comment', function () {
    Event::fake([PostNoteChanged::class]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.notes.store', $this->post), [
            'body' => 'This is a comment.',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('body', 'This is a comment.');

    $this->assertDatabaseHas('post_notes', [
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'This is a comment.',
        'parent_id' => null,
    ]);

    Event::assertDispatched(PostNoteChanged::class, fn (PostNoteChanged $event) => $event->postId === $this->post->id
        && $event->workspaceId === $this->workspace->id
        && $event->change === 'created'
    );
});

test('note changes broadcast to the post and its workspace', function () {
    $event = new PostNoteChanged($this->post->id, $this->workspace->id, 'updated');

    expect($event->broadcastAs())->toBe('post.note.changed')
        ->and($event->broadcastWith())->toBe([
            'post_id' => $this->post->id,
            'change' => 'updated',
        ])
        ->and(array_map(strval(...), $event->broadcastOn()))->toBe([
            "private-post.{$this->post->id}",
            "private-workspace.{$this->workspace->id}",
        ]);
});

test('store creates a reply', function () {
    $parent = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.notes.store', $this->post), [
            'body' => 'This is a reply.',
            'parent_id' => $parent->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('parent_id', $parent->id);

    $this->assertDatabaseHas('post_notes', [
        'post_id' => $this->post->id,
        'parent_id' => $parent->id,
        'body' => 'This is a reply.',
    ]);
});

test('index returns mentioned_users map for chip rendering', function () {
    $other = User::factory()->create(['name' => 'Other Member']);
    $this->workspace->members()->attach($other->id, ['role' => Role::Member->value]);

    PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => "Ping @[{$other->id}] please",
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.posts.notes.index', $this->post));

    $response->assertOk();
    $response->assertJsonPath("mentioned_users.{$other->id}", 'Other Member');
});

test('index does not disclose names of users outside the workspace', function () {
    $outsider = User::factory()->create(['name' => 'Private Name']);

    PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => "Ping @[{$outsider->id}]",
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.posts.notes.index', $this->post))
        ->assertOk()
        ->assertJsonPath('mentioned_users', []);
});

test('store dispatches a mention notification to a workspace member', function () {
    Queue::fake();

    $other = User::factory()->create();
    $this->workspace->members()->attach($other->id, ['role' => Role::Member->value]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.notes.store', $this->post), [
            'body' => "Hey @[{$other->id}] please review",
        ]);

    $response->assertCreated();

    Queue::assertPushed(
        SendNotification::class,
        fn ($job) => $job->user->id === $other->id
    );
});

test('update with newly added mention dispatches a notification', function () {
    Queue::fake();

    $other = User::factory()->create();
    $this->workspace->members()->attach($other->id, ['role' => Role::Member->value]);

    $comment = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'Plain body, no mention.',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('app.posts.notes.update', [$this->post, $comment]), [
            'body' => "Updated to mention @[{$other->id}]",
        ]);

    $response->assertOk();

    Queue::assertPushed(
        SendNotification::class,
        fn ($job) => $job->user->id === $other->id
    );
});

test('store rejects reply to a reply', function () {
    $parent = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $reply = PostNote::factory()->reply($parent)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.notes.store', $this->post), [
            'body' => 'Nested reply attempt.',
            'parent_id' => $reply->id,
        ]);

    $response->assertStatus(422);
});

test('update own comment', function () {
    $comment = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'Original body.',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('app.posts.notes.update', [$this->post, $comment]), [
            'body' => 'Updated body.',
        ]);

    $response->assertOk();
    $response->assertJsonPath('body', 'Updated body.');

    $this->assertDatabaseHas('post_notes', [
        'id' => $comment->id,
        'body' => 'Updated body.',
    ]);
});

test('cannot update other user comment', function () {
    $otherUser = User::factory()->create([]);
    $this->workspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $this->workspace->id]);

    $comment = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'Original body.',
    ]);

    $response = $this->actingAs($otherUser)
        ->putJson(route('app.posts.notes.update', [$this->post, $comment]), [
            'body' => 'Hacked body.',
        ]);

    $response->assertForbidden();
});

test('delete own comment', function () {
    $comment = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('app.posts.notes.destroy', [$this->post, $comment]));

    $response->assertNoContent();

    $this->assertDatabaseMissing('post_notes', [
        'id' => $comment->id,
    ]);
});

test('cannot delete other user comment', function () {
    $otherUser = User::factory()->create([]);
    $this->workspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $this->workspace->id]);

    $comment = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($otherUser)
        ->deleteJson(route('app.posts.notes.destroy', [$this->post, $comment]));

    $response->assertForbidden();
});

test('react toggles emoji', function () {
    Event::fake([PostNoteChanged::class]);
    $comment = PostNote::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    // First reaction adds the emoji
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.notes.react', [$this->post, $comment]), [
            'emoji' => '👍',
        ]);

    $response->assertOk();
    $comment->refresh();
    expect($comment->reactions)->toHaveCount(1);
    expect($comment->reactions[0]['emoji'])->toBe('👍');
    expect($comment->reactions[0]['user_id'])->toBe($this->user->id);

    // Same reaction again removes the emoji
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.notes.react', [$this->post, $comment]), [
            'emoji' => '👍',
        ]);

    $response->assertOk();
    $comment->refresh();
    expect($comment->reactions)->toHaveCount(0);

    Event::assertDispatchedTimes(PostNoteChanged::class, 2);
    Event::assertDispatched(PostNoteChanged::class, fn (PostNoteChanged $event) => $event->change === 'reacted');
});

test('cannot comment on post from other workspace', function () {
    $otherUser = User::factory()->create([]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($otherUser)
        ->postJson(route('app.posts.notes.store', $this->post), [
            'body' => 'Cross-workspace comment.',
        ]);

    $response->assertForbidden();
});
