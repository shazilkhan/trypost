<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Notification\Type;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public User $user,
        public Type $type,
        public Mailable $mailable,
    ) {}

    public function handle(): void
    {
        if (! $this->user->wantsEmailFor($this->type)) {
            return;
        }

        Mail::to($this->user)->send($this->mailable);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendNotification job failed', [
            'user_id' => $this->user->id,
            'type' => $this->type->value,
            'error' => $exception->getMessage(),
        ]);
    }
}
