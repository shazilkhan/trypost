<?php

declare(strict_types=1);

namespace App\Actions\Post\Queue;

use App\Enums\Post\QueuePosition;
use App\Enums\Post\ScheduleMode;
use App\Enums\Post\Status as PostStatus;
use App\Exceptions\Post\QueueBusyException;
use App\Models\Post;
use App\Models\SocialAccount;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReflowChannelQueue
{
    /**
     * Callers that enqueue inside a DB transaction must take the lock outside it:
     * withLock($ids, fn () => DB::transaction(fn () => handleLocked(...))).
     * Cache::lock()->block() never times out under frozen test time; call travelBack() before contention tests.
     *
     * @param  list<string>  $channelIds
     */
    public static function withLock(array $channelIds, Closure $callback, int $waitSeconds = 5): mixed
    {
        $ids = array_values(array_unique($channelIds));
        sort($ids);
        $locks = [];

        try {
            foreach ($ids as $id) {
                $lock = Cache::lock("queue:{$id}", 10);
                $lock->block($waitSeconds);
                $locks[] = $lock;
            }

            return $callback();
        } catch (LockTimeoutException) {
            throw new QueueBusyException;
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Takes the channel lock itself. Do not call inside a DB transaction; use withLock() around the transaction instead.
     */
    public static function handle(SocialAccount $channel, ?Post $insert = null, QueuePosition $position = QueuePosition::Next, int $waitSeconds = 5): void
    {
        self::withLock([$channel->id], fn () => self::handleLocked($channel, $insert, $position), $waitSeconds);
    }

    /**
     * Reflows the channel once the surrounding transaction commits (immediately outside one).
     * A channel deleted meanwhile is skipped; a busy lock is reported and leaves the previous, still valid times.
     */
    public static function afterCommit(string $channelId): void
    {
        DB::afterCommit(function () use ($channelId): void {
            $channel = SocialAccount::query()->find($channelId);

            if ($channel === null) {
                return;
            }

            try {
                self::handle($channel);
            } catch (QueueBusyException $exception) {
                report($exception);
            }
        });
    }

    public static function handleLocked(SocialAccount $channel, ?Post $insert = null, QueuePosition $position = QueuePosition::Next): void
    {
        $channel->refresh();

        $queued = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('schedule_mode', ScheduleMode::Queue)
            ->where('scheduled_at', '>', now()->addMinute())
            ->when($insert, fn ($query) => $query->whereKeyNot($insert->id))
            ->whereHas('postPlatforms', fn ($platforms) => $platforms->enabled()->where('social_account_id', $channel->id))
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        if ($insert !== null) {
            $queued = $position === QueuePosition::Top
                ? $queued->prepend($insert)
                : $queued->push($insert);
        }

        $slots = $channel->posting_schedule?->nextSlots(now()->addMinute(), $channel->timezone, $queued->count()) ?? [];

        DB::transaction(function () use ($queued, $slots): void {
            foreach ($queued->values() as $index => $post) {
                $slot = $slots[$index] ?? null;

                if ($slot === null) {
                    if ($post->scheduled_at !== null) {
                        self::updateIfScheduled($post, ['schedule_mode' => ScheduleMode::Custom]);
                    }

                    continue;
                }

                if ($post->scheduled_at === null || ! $post->scheduled_at->equalTo($slot)) {
                    self::updateIfScheduled($post, ['scheduled_at' => $slot, 'schedule_mode' => ScheduleMode::Queue]);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function updateIfScheduled(Post $post, array $attributes): bool
    {
        $affected = Post::query()
            ->whereKey($post->id)
            ->where('status', PostStatus::Scheduled)
            ->where('schedule_mode', ScheduleMode::Queue)
            ->update($attributes);

        if ($affected === 1) {
            $post->forceFill($attributes)->syncOriginal();
        }

        return $affected === 1;
    }
}
