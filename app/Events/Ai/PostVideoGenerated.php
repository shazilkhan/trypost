<?php

declare(strict_types=1);

namespace App\Events\Ai;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostVideoGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $media
     */
    public function __construct(
        public string $userId,
        public string $generationId,
        public string $postId,
        public ?array $media = null,
        public ?string $error = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'ai.video.generated';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}.ai-video.{$this->generationId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'generation_id' => $this->generationId,
            'post_id' => $this->postId,
            'media' => $this->media,
            'error' => $this->error,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
