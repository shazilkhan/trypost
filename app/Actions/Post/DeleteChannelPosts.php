<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Media\DeleteOwnedMedia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Support\Social\GoogleBusinessDerivativeCleaner;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deletes every post of a channel that is going away, whatever its status or
 * origin, quietly: no post.deleted webhook, notification or observer. A legacy
 * post that still publishes through another channel only loses this channel's
 * target. Analytics publications stay and are unlinked by the FK, so the same
 * identity connected again imports its posts afresh.
 */
class DeleteChannelPosts
{
    /**
     * @return array{deleted_posts: int, detached_targets: int}
     */
    public static function forAccount(SocialAccount $account): array
    {
        return self::purge(
            fn (Builder $targets): Builder => $targets->where('social_account_id', $account->id),
            fn (Builder $live): Builder => $live->where('social_account_id', '!=', $account->id),
            $account->workspace_id,
        );
    }

    /**
     * Targets left without an account by a disconnect from before channels
     * took their posts with them.
     *
     * @return array{deleted_posts: int, detached_targets: int}
     */
    public static function orphaned(?string $workspaceId = null): array
    {
        return self::purge(
            fn (Builder $targets): Builder => $targets->whereNull('social_account_id'),
            fn (Builder $live): Builder => $live,
            $workspaceId,
        );
    }

    /**
     * @param  Closure(Builder<PostPlatform>): Builder<PostPlatform>  $channelTargets
     * @param  Closure(Builder<PostPlatform>): Builder<PostPlatform>  $otherChannels
     * @return array{deleted_posts: int, detached_targets: int}
     */
    private static function purge(Closure $channelTargets, Closure $otherChannels, ?string $workspaceId): array
    {
        $liveElsewhere = fn (Builder $targets): Builder => $otherChannels($targets->enabled()->whereNotNull('social_account_id'));

        $posts = fn (): Builder => Post::query()
            ->when($workspaceId !== null, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->whereHas('postPlatforms', $channelTargets);

        $deletedPosts = 0;

        $posts()
            ->whereDoesntHave('postPlatforms', $liveElsewhere)
            ->select('id')
            ->chunkById(PruneExpiredPostHistory::CHUNK, function (Collection $chunk) use (&$deletedPosts): void {
                $ids = $chunk->modelKeys();

                self::pruneGoogleBusinessImages(PostPlatform::query()->whereIn('post_id', $ids));

                DB::transaction(function () use ($ids): void {
                    DeleteOwnedMedia::forPosts($ids);
                    Post::query()->whereKey($ids)->delete();
                });

                $deletedPosts += count($ids);
            });

        $detachedTargets = 0;

        $posts()
            ->whereHas('postPlatforms', $liveElsewhere)
            ->select(['id', 'status'])
            ->chunkById(PruneExpiredPostHistory::CHUNK, function (Collection $chunk) use ($channelTargets, &$detachedTargets): void {
                $targets = $channelTargets(PostPlatform::query()->whereIn('post_id', $chunk->modelKeys()));

                self::pruneGoogleBusinessImages(clone $targets);
                $detachedTargets += $targets->delete();

                $chunk
                    ->filter(fn (Post $post): bool => $post->status === PostStatus::Publishing)
                    ->each(fn (Post $post) => app(FinalizePostPublication::class)->handle($post));
            });

        return ['deleted_posts' => $deletedPosts, 'detached_targets' => $detachedTargets];
    }

    /**
     * @param  Builder<PostPlatform>  $targets
     */
    private static function pruneGoogleBusinessImages(Builder $targets): void
    {
        $targets
            ->where('platform', Platform::GoogleBusiness)
            ->pluck('id')
            ->each(fn (string $id) => app(GoogleBusinessDerivativeCleaner::class)->cleanup($id));
    }
}
