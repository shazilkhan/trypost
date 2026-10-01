<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Media\DeleteOwnedMedia;
use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Enums\Post\ScheduleMode;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Events\PostDeleted;
use App\Models\Post;
use App\Support\Social\GoogleBusinessDerivativeCleaner;
use Illuminate\Support\Facades\DB;

class DeletePost
{
    public static function execute(Post $post): void
    {
        $post->postPlatforms()
            ->where('platform', Platform::GoogleBusiness)
            ->pluck('id')
            ->each(fn (string $id) => app(GoogleBusinessDerivativeCleaner::class)->cleanup($id));

        $postId = $post->id;
        $workspaceId = $post->workspace_id;
        $queuedChannelId = $post->schedule_mode === ScheduleMode::Queue && $post->status === PostStatus::Scheduled
            ? $post->postPlatforms()->enabled()->value('social_account_id')
            : null;

        DB::transaction(function () use ($post, $postId): void {
            Post::query()->whereKey($postId)->lockForUpdate()->first();
            DeleteOwnedMedia::forPosts([$postId]);
            $post->delete();
        });

        PostDeleted::dispatch($postId, $workspaceId);

        if ($queuedChannelId !== null) {
            ReflowChannelQueue::afterCommit($queuedChannelId);
        }
    }
}
