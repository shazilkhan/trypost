<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Enums\Media\Source;
use App\Enums\Media\Type as MediaType;
use App\Events\Ai\PostVideoGenerated;
use App\Models\Media;
use App\Models\Workspace;
use App\Services\Ai\AiVideoClient;
use App\Services\Ai\RecordAiUsage;
use App\Support\VideoDurationProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeneratePostVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public string $workspaceId,
        public string $postId,
        public string $userId,
        public string $generationId,
        public string $prompt,
        public string $aspectRatio,
        public int $duration,
    ) {
        $this->onQueue('ai');
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('GeneratePostVideo failed', [
            'post_id' => $this->postId,
            'generation_id' => $this->generationId,
            'error' => $exception?->getMessage(),
        ]);

        $workspace = Workspace::query()->find($this->workspaceId);

        if ($workspace) {
            RecordAiUsage::forgetVideo($workspace, $this->generationId);
        }

        PostVideoGenerated::dispatch(
            userId: $this->userId,
            generationId: $this->generationId,
            postId: $this->postId,
            media: null,
            error: __('posts.ai.video.errors.failed'),
        );
    }

    public function handle(AiVideoClient $client): void
    {
        $workspace = Workspace::query()->findOrFail($this->workspaceId);

        $bytes = $client->download($this->awaitVideoUri($client));
        $media = $this->storeVideo($workspace, $client, $bytes);

        PostVideoGenerated::dispatch(
            userId: $this->userId,
            generationId: $this->generationId,
            postId: $this->postId,
            media: $this->toMediaItem($media, $client),
            error: null,
        );
    }

    /**
     * Veo answers in anything from seconds to several minutes, so the job
     * polls the operation until it is done or the configured budget runs out.
     */
    private function awaitVideoUri(AiVideoClient $client): string
    {
        $operation = $client->start($this->prompt, $this->aspectRatio, $this->duration);

        $interval = max(1, (int) config('trypost.ai_video.poll_interval_seconds'));
        $attempts = (int) ceil(max($interval, (int) config('trypost.ai_video.timeout_seconds')) / $interval);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            Sleep::for($interval)->seconds();

            $status = $client->poll($operation);

            if (! data_get($status, 'done')) {
                continue;
            }

            $uri = data_get($status, 'uri');

            if (! is_string($uri)) {
                throw new RuntimeException((string) data_get($status, 'error', 'Video generation produced no video.'));
            }

            return $uri;
        }

        throw new RuntimeException('Video generation timed out.');
    }

    /**
     * Clips get their own directory, like AI images do. The queue worker and
     * the web server can run as different users, so a directory the worker
     * creates must not be the one uploads are written to.
     */
    private function storeVideo(Workspace $workspace, AiVideoClient $client, string $bytes): Media
    {
        $path = 'ai-videos/'.Str::uuid().'.mp4';

        Storage::put($path, $bytes);

        try {
            return $workspace->media()->create([
                'group_id' => Str::uuid()->toString(),
                'collection' => 'assets',
                'type' => MediaType::Video,
                'path' => $path,
                'original_filename' => 'ai-video-'.now()->format('Ymd-His').'.mp4',
                'mime_type' => 'video/mp4',
                'size' => strlen($bytes),
                'order' => 0,
                'meta' => VideoDurationProbe::mergeInto($this->dimensions($client), (float) $this->duration),
            ]);
        } catch (Throwable $exception) {
            Storage::delete($path);

            throw $exception;
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    private function dimensions(AiVideoClient $client): array
    {
        [$long, $short] = match ($client->resolution()) {
            '4k' => [3840, 2160],
            '1080p' => [1920, 1080],
            default => [1280, 720],
        };

        return $this->aspectRatio === '9:16'
            ? ['width' => $short, 'height' => $long]
            : ['width' => $long, 'height' => $short];
    }

    /**
     * @return array<string, mixed>
     */
    private function toMediaItem(Media $media, AiVideoClient $client): array
    {
        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => MediaType::Video->value,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
            'size' => $media->size,
            'meta' => $media->meta,
            'source' => Source::Ai->value,
            'source_meta' => [
                'kind' => 'video',
                'prompt' => Str::limit($this->prompt, 500),
                'model' => $client->model(),
                'aspect_ratio' => $this->aspectRatio,
                'duration' => $this->duration,
            ],
        ];
    }
}
