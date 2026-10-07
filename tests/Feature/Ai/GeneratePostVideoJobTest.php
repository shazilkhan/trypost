<?php

declare(strict_types=1);

use App\Enums\Ai\UsageType;
use App\Enums\Media\Type as MediaType;
use App\Events\Ai\PostVideoGenerated;
use App\Jobs\Ai\GeneratePostVideo;
use App\Models\AiUsageLog;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiVideoClient;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config([
        'trypost.ai_video.enabled' => true,
        'trypost.ai_video.model' => 'veo-3.1-generate-preview',
        'trypost.ai_video.resolution' => '720p',
        'trypost.ai_video.poll_interval_seconds' => 10,
        'trypost.ai_video.timeout_seconds' => 30,
        'ai.providers.gemini.key' => 'test-key',
    ]);

    Storage::fake();
    Sleep::fake();
    Event::fake([PostVideoGenerated::class]);

    $this->api = config('trypost.ai_video.api');
    $this->operation = 'models/veo-3.1-generate-preview/operations/op-123';
    $this->generationId = '0196f5ca-bf2e-7d15-9a22-5709ab10d6c9';

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'media' => [],
    ]);

    $this->job = fn (string $aspectRatio = '9:16', int $duration = 8): GeneratePostVideo => new GeneratePostVideo(
        workspaceId: $this->workspace->id,
        postId: $this->post->id,
        userId: $this->user->id,
        generationId: $this->generationId,
        prompt: 'A barista pours latte art in a sunny café.',
        aspectRatio: $aspectRatio,
        duration: $duration,
    );

    $this->finished = fn (string $uri): array => [
        'name' => $this->operation,
        'done' => true,
        'response' => ['generateVideoResponse' => ['generatedSamples' => [['video' => ['uri' => $uri]]]]],
    ];
});

test('job generates the clip, stores it as a video asset and notifies the editor', function () {
    $videoUri = "{$this->api}/files/abc123:download?alt=media";

    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['name' => $this->operation]),
        "{$this->api}/{$this->operation}" => Http::sequence()
            ->push(['name' => $this->operation, 'done' => false])
            ->push(($this->finished)($videoUri)),
        "{$this->api}/files/*" => Http::response('FAKE-MP4-BYTES', 200, ['Content-Type' => 'video/mp4']),
    ]);

    ($this->job)()->handle(app(AiVideoClient::class));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), ':predictLongRunning')
        && $request->hasHeader('x-goog-api-key', 'test-key')
        && data_get($request->data(), 'instances.0.prompt') === 'A barista pours latte art in a sunny café.'
        && data_get($request->data(), 'parameters.aspectRatio') === '9:16'
        && data_get($request->data(), 'parameters.durationSeconds') === 8
        && data_get($request->data(), 'parameters.resolution') === '720p');

    Sleep::assertSleptTimes(2);

    $media = Media::query()->sole();

    expect($media->type)->toBe(MediaType::Video)
        ->and($media->collection)->toBe('assets')
        ->and($media->mime_type)->toBe('video/mp4')
        ->and($media->size)->toBe(strlen('FAKE-MP4-BYTES'))
        ->and($media->mediable_id)->toBe($this->workspace->id)
        ->and($media->meta)->toEqual(['width' => 720, 'height' => 1280, 'duration' => 8.0]);

    Storage::assertExists($media->path);
    expect(Storage::get($media->path))->toBe('FAKE-MP4-BYTES');

    Event::assertDispatched(PostVideoGenerated::class, fn (PostVideoGenerated $event) => $event->userId === $this->user->id
        && $event->generationId === $this->generationId
        && $event->postId === $this->post->id
        && $event->error === null
        && data_get($event->media, 'id') === $media->id
        && data_get($event->media, 'type') === 'video'
        && data_get($event->media, 'source') === 'ai'
        && data_get($event->media, 'source_meta.kind') === 'video'
        && data_get($event->media, 'source_meta.duration') === 8);
});

test('job stores landscape dimensions for a horizontal clip', function () {
    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['name' => $this->operation]),
        "{$this->api}/{$this->operation}" => Http::response(($this->finished)("{$this->api}/files/wide:download?alt=media")),
        "{$this->api}/files/*" => Http::response('FAKE-MP4-BYTES'),
    ]);

    ($this->job)('16:9', 4)->handle(app(AiVideoClient::class));

    expect(Media::query()->sole()->meta)->toEqual(['width' => 1280, 'height' => 720, 'duration' => 4.0]);
});

test('job fails when the provider reports an error', function () {
    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['name' => $this->operation]),
        "{$this->api}/{$this->operation}" => Http::response([
            'name' => $this->operation,
            'done' => true,
            'error' => ['code' => 3, 'message' => 'The prompt could not be processed.'],
        ]),
    ]);

    expect(fn () => ($this->job)()->handle(app(AiVideoClient::class)))
        ->toThrow(RuntimeException::class, 'The prompt could not be processed.');

    expect(Media::query()->count())->toBe(0);
    Event::assertNotDispatched(PostVideoGenerated::class);
});

test('job fails with the filter reason when the provider returns no video', function () {
    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['name' => $this->operation]),
        "{$this->api}/{$this->operation}" => Http::response([
            'name' => $this->operation,
            'done' => true,
            'response' => ['generateVideoResponse' => [
                'raiMediaFilteredCount' => 1,
                'raiMediaFilteredReasons' => ['Blocked by the safety filters.'],
            ]],
        ]),
    ]);

    expect(fn () => ($this->job)()->handle(app(AiVideoClient::class)))
        ->toThrow(RuntimeException::class, 'Blocked by the safety filters.');
});

test('job gives up when the clip is not ready within the time budget', function () {
    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['name' => $this->operation]),
        "{$this->api}/{$this->operation}" => Http::response(['name' => $this->operation, 'done' => false]),
    ]);

    expect(fn () => ($this->job)()->handle(app(AiVideoClient::class)))
        ->toThrow(RuntimeException::class, 'Video generation timed out.');

    Sleep::assertSleptTimes(3);
    expect(Media::query()->count())->toBe(0);
});

test('job fails when the start request is rejected', function () {
    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['error' => ['message' => 'API key not valid.']], 400),
    ]);

    expect(fn () => ($this->job)()->handle(app(AiVideoClient::class)))
        ->toThrow(RuntimeException::class, 'API key not valid.');
});

test('job refuses to download a clip from another host', function () {
    Http::fake([
        "{$this->api}/models/*:predictLongRunning" => Http::response(['name' => $this->operation]),
        "{$this->api}/{$this->operation}" => Http::response(($this->finished)('https://evil.example.com/video.mp4')),
        'evil.example.com/*' => Http::response('NOPE'),
    ]);

    expect(fn () => ($this->job)()->handle(app(AiVideoClient::class)))
        ->toThrow(RuntimeException::class, 'not on the provider host');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.example.com'));
    expect(Media::query()->count())->toBe(0);
});

test('a failed job releases its place in the monthly limit and reports the error', function () {
    RecordAiUsage::recordVideo(
        workspace: $this->workspace,
        provider: AiVideoClient::PROVIDER,
        model: 'veo-3.1-generate-preview',
        userId: $this->user->id,
        postId: $this->post->id,
        metadata: ['generation_id' => $this->generationId],
    );
    RecordAiUsage::recordVideo(
        workspace: $this->workspace,
        provider: AiVideoClient::PROVIDER,
        model: 'veo-3.1-generate-preview',
        metadata: ['generation_id' => 'another-generation'],
    );

    ($this->job)()->failed(new RuntimeException('boom'));

    $remaining = AiUsageLog::query()->where('type', UsageType::Video)->get();

    expect($remaining)->toHaveCount(1)
        ->and(data_get($remaining->first()->metadata, 'generation_id'))->toBe('another-generation');

    Event::assertDispatched(PostVideoGenerated::class, fn (PostVideoGenerated $event) => $event->generationId === $this->generationId
        && $event->media === null
        && $event->error === __('posts.ai.video.errors.failed'));
});
