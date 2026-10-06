<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Media\DeleteOwnedMedia;
use App\Enums\Post\Origin;
use App\Enums\SocialAccount\Platform;
use App\Events\PostDeleted;
use App\Models\AnalyticsPublication;
use App\Models\Post;
use App\Support\PostStatusRules;
use App\Support\Social\GoogleBusinessDerivativeCleaner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePost
{
    /**
     * @throws ValidationException
     */
    public static function execute(Post $post, bool $respectStatus = false): void
    {
        if ($respectStatus && PostStatusRules::blocksDeletion($post)) {
            throw ValidationException::withMessages(['post' => __('posts.flash.cannot_delete_published')]);
        }

        $post->postPlatforms()
            ->where('platform', Platform::GoogleBusiness)
            ->pluck('id')
            ->each(fn (string $id) => app(GoogleBusinessDerivativeCleaner::class)->cleanup($id));

        $postId = $post->id;
        $workspaceId = $post->workspace_id;
        $isImported = $post->origin === Origin::Network;

        DB::transaction(function () use ($post, $postId): void {
            Post::query()->whereKey($postId)->lockForUpdate()->first();

            AnalyticsPublication::query()
                ->whereIn('post_platform_id', $post->postPlatforms()->pluck('id')->all())
                ->update(['post_dismissed_at' => now()]);

            DeleteOwnedMedia::forPosts([$postId]);
            $post->delete();
        });

        if (! $isImported) {
            PostDeleted::dispatch($postId, $workspaceId);
        }
    }
}
