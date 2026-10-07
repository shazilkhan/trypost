<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Google Veo through the Gemini API: start a long-running
 * generation, poll it, then download the finished clip.
 *
 * @see https://ai.google.dev/gemini-api/docs/veo
 */
class AiVideoClient
{
    public const PROVIDER = 'gemini';

    public const BASE_RESOLUTION = '720p';

    /** @var array<int, string> */
    public const ASPECT_RATIOS = ['9:16', '16:9'];

    /** @var array<int, int> */
    private const DURATIONS = [4, 6, 8];

    private const LONGEST_DURATION = 8;

    public function isAvailable(): bool
    {
        return (bool) config('trypost.ai_video.enabled') && filled($this->apiKey());
    }

    public function model(): string
    {
        return (string) config('trypost.ai_video.model');
    }

    public function resolution(): string
    {
        return (string) config('trypost.ai_video.resolution', self::BASE_RESOLUTION);
    }

    /**
     * Veo only renders its longest clip above the base resolution.
     *
     * @return array<int, int>
     */
    public function allowedDurations(): array
    {
        return $this->resolution() === self::BASE_RESOLUTION
            ? self::DURATIONS
            : [self::LONGEST_DURATION];
    }

    /**
     * Start a generation and return the operation name to poll.
     */
    public function start(string $prompt, string $aspectRatio, int $durationSeconds): string
    {
        $response = $this->request()
            ->timeout(60)
            ->post("{$this->baseUrl()}/models/{$this->model()}:predictLongRunning", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => [
                    'aspectRatio' => $aspectRatio,
                    // The docs quote these values, but the API rejects a string.
                    'durationSeconds' => $durationSeconds,
                    'resolution' => $this->resolution(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Video generation request failed: {$this->errorMessage($response)}");
        }

        $operation = (string) $response->json('name', '');

        if ($operation === '') {
            throw new RuntimeException('Video generation request returned no operation name.');
        }

        return $operation;
    }

    /**
     * @return array{done: bool, uri: ?string, error: ?string}
     */
    public function poll(string $operation): array
    {
        $response = $this->request()->timeout(30)->get("{$this->baseUrl()}/{$operation}");

        if ($response->failed()) {
            throw new RuntimeException("Video generation status check failed: {$this->errorMessage($response)}");
        }

        if (! $response->json('done', false)) {
            return ['done' => false, 'uri' => null, 'error' => null];
        }

        $error = $response->json('error.message');

        if (is_string($error) && $error !== '') {
            return ['done' => true, 'uri' => null, 'error' => $error];
        }

        $uri = $response->json('response.generateVideoResponse.generatedSamples.0.video.uri');

        if (! is_string($uri) || $uri === '') {
            $filtered = collect($response->json('response.generateVideoResponse.raiMediaFilteredReasons', []))
                ->filter(fn ($reason) => is_string($reason) && $reason !== '')
                ->implode(' ');

            return [
                'done' => true,
                'uri' => null,
                'error' => $filtered !== '' ? $filtered : 'The provider returned no video.',
            ];
        }

        return ['done' => true, 'uri' => $uri, 'error' => null];
    }

    /**
     * Download the finished clip. The URI comes from the provider's own
     * response, so it must stay on the configured API host.
     */
    public function download(string $uri): string
    {
        if (parse_url($uri, PHP_URL_HOST) !== parse_url($this->baseUrl(), PHP_URL_HOST)) {
            throw new RuntimeException('Video download URI is not on the provider host.');
        }

        $response = Http::withHeaders($this->authHeaders())->timeout(180)->get($uri);

        if ($response->failed() || $response->body() === '') {
            throw new RuntimeException("Video download failed: {$this->errorMessage($response)}");
        }

        return $response->body();
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders($this->authHeaders())->acceptJson();
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['x-goog-api-key' => (string) $this->apiKey()];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('trypost.ai_video.api'), '/');
    }

    private function apiKey(): ?string
    {
        return config('ai.providers.gemini.key');
    }

    private function errorMessage(Response $response): string
    {
        return (string) ($response->json('error.message') ?? "HTTP {$response->status()}");
    }
}
