<?php

declare(strict_types=1);

use App\Enums\Ai\UsageType;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\GeneratePostVideo;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.self_hosted' => true,
        'trypost.ai_video.enabled' => true,
        'trypost.ai_video.model' => 'veo-3.1-generate-preview',
        'trypost.ai_video.resolution' => '720p',
        'trypost.ai_video.monthly_limit' => 2,
        'ai.providers.gemini.key' => 'test-key',
    ]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'media' => [],
    ]);

    $this->payload = fn (array $overrides = []): array => [
        'prompt' => 'A barista pours latte art in a sunny café.',
        'aspect_ratio' => '9:16',
        'duration' => 8,
        'generation_id' => Str::uuid()->toString(),
        ...$overrides,
    ];
});

test('generate video queues the job and counts it against the monthly limit', function () {
    Bus::fake();

    $payload = ($this->payload)(['prompt' => '  A barista pours latte art.  ']);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), $payload)
        ->assertStatus(Response::HTTP_ACCEPTED)
        ->assertJsonPath('generation_id', data_get($payload, 'generation_id'))
        ->assertJsonPath('channel', "user.{$this->user->id}.ai-video.".data_get($payload, 'generation_id'))
        ->assertJsonPath('remaining', 1);

    Bus::assertDispatched(GeneratePostVideo::class, fn (GeneratePostVideo $job) => $job->generationId === data_get($payload, 'generation_id')
        && $job->postId === $this->post->id
        && $job->workspaceId === $this->workspace->id
        && $job->userId === $this->user->id
        && $job->prompt === 'A barista pours latte art.'
        && $job->aspectRatio === '9:16'
        && $job->duration === 8);

    $usage = AiUsageLog::query()->where('account_id', $this->workspace->account_id)->sole();

    expect($usage->type)->toBe(UsageType::Video)
        ->and($usage->provider)->toBe('gemini')
        ->and($usage->model)->toBe('veo-3.1-generate-preview')
        ->and($usage->credits)->toBe((int) config('ai-credits.video.default'))
        ->and($usage->post_id)->toBe($this->post->id)
        ->and($usage->user_id)->toBe($this->user->id)
        ->and(data_get($usage->metadata, 'generation_id'))->toBe(data_get($payload, 'generation_id'));
});

test('generate video is refused once the monthly limit is reached', function () {
    Bus::fake();

    AiUsageLog::factory()->count(2)->create([
        'account_id' => $this->workspace->account_id,
        'workspace_id' => $this->workspace->id,
        'type' => UsageType::Video,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)())
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
        ->assertJsonPath('message', __('posts.ai.video.errors.limit_reached', ['limit' => 2]))
        ->assertJsonValidationErrors(['prompt']);

    Bus::assertNotDispatched(GeneratePostVideo::class);

    expect(AiUsageLog::query()->where('account_id', $this->workspace->account_id)->count())->toBe(2);
});

test('other AI usage and other accounts do not count against the video limit', function () {
    Bus::fake();

    AiUsageLog::factory()->count(3)->create([
        'account_id' => $this->workspace->account_id,
        'workspace_id' => $this->workspace->id,
        'type' => UsageType::Image,
    ]);
    AiUsageLog::factory()->count(3)->create(['type' => UsageType::Video]);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)())
        ->assertStatus(Response::HTTP_ACCEPTED)
        ->assertJsonPath('remaining', 1);
});

test('generate video is not found when the feature is switched off', function () {
    Bus::fake();
    config(['trypost.ai_video.enabled' => false]);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)())
        ->assertNotFound();

    Bus::assertNotDispatched(GeneratePostVideo::class);
    expect(AiUsageLog::query()->count())->toBe(0);
});

test('generate video is not found without a provider key', function () {
    Bus::fake();
    config(['ai.providers.gemini.key' => null]);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)())
        ->assertNotFound();

    Bus::assertNotDispatched(GeneratePostVideo::class);
});

test('generate video validates the prompt, orientation and length', function (array $overrides, string $field) {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    Bus::assertNotDispatched(GeneratePostVideo::class);
    expect(AiUsageLog::query()->count())->toBe(0);
})->with([
    'blank prompt' => [['prompt' => '   '], 'prompt'],
    'oversized prompt' => [['prompt' => str_repeat('a', 2001)], 'prompt'],
    'unknown orientation' => [['aspect_ratio' => '1:1'], 'aspect_ratio'],
    'unsupported length' => [['duration' => 5], 'duration'],
    'generation id that is not a uuid' => [['generation_id' => 'nope'], 'generation_id'],
]);

test('only the longest clip is accepted above the base resolution', function () {
    Bus::fake();
    config(['trypost.ai_video.resolution' => '1080p']);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)(['duration' => 4]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration']);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $this->post), ($this->payload)(['duration' => 8]))
        ->assertStatus(Response::HTTP_ACCEPTED);
});

test('generate video denies access when the post is from another workspace', function () {
    Bus::fake();

    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'user_id' => $otherUser->id,
        'account_id' => $otherUser->account_id,
    ]);
    $otherPost = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.video', $otherPost), ($this->payload)())
        ->assertNotFound();

    Bus::assertNotDispatched(GeneratePostVideo::class);
    expect(AiUsageLog::query()->count())->toBe(0);
});

test('the post editor receives the video options and remaining allowance', function () {
    AiUsageLog::factory()->create([
        'account_id' => $this->workspace->account_id,
        'workspace_id' => $this->workspace->id,
        'type' => UsageType::Video,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.edit', $this->post))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('aiVideo.enabled', true)
            ->where('aiVideo.durations', [4, 6, 8])
            ->where('aiVideo.aspectRatios', ['9:16', '16:9'])
            ->where('aiVideo.limit', 2)
            ->where('aiVideo.remaining', 1));
});

test('the post editor hides video generation when it is switched off', function () {
    config(['trypost.ai_video.enabled' => false]);

    $this->actingAs($this->user)
        ->get(route('app.posts.edit', $this->post))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('aiVideo.enabled', false)->where('aiVideo.remaining', 0));
});
