<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Media\MediaCopyBatch;
use App\Support\PostCompositionValidator;
use Closure;
use Illuminate\Support\Collection;

class CreatePosts
{
    /**
     * `$beforeCreate` and `$afterCreate` run inside the same transaction (and queue lock), before and
     * after the posts are created. `$legacyMedia` are stored items of a post being replaced that may
     * pass through without a row (see SyncOwnedMedia).
     *
     * @param  array<string, mixed>  $composition
     * @param  (Closure(MediaCopyBatch): void)|null  $beforeCreate
     * @param  (Closure(Collection<int, Post>): void)|null  $afterCreate
     * @param  list<array<string, mixed>>  $legacyMedia
     * @return Collection<int, Post>
     */
    public static function execute(
        Workspace $workspace,
        User $user,
        array $composition,
        ?Closure $beforeCreate = null,
        ?Closure $afterCreate = null,
        array $legacyMedia = [],
    ): Collection {
        $resolved = PostCompositionValidator::validate($workspace, $composition, $legacyMedia);

        $create = fn (): Collection => MediaCopyBatch::run(function (MediaCopyBatch $batch) use ($workspace, $user, $resolved, $beforeCreate, $afterCreate, $legacyMedia): Collection {
            if ($beforeCreate !== null) {
                $beforeCreate($batch);
            }

            $posts = collect($resolved['destinations'])->map(function (array $destination) use ($workspace, $user, $resolved, $batch, $legacyMedia): Post {
                $post = CreateChannelPost::execute($workspace, $user, [
                    ...$destination,
                    'legacy_media' => $legacyMedia,
                    'status' => $resolved['status'],
                    'scheduled_at' => $resolved['scheduled_at'] ?? null,
                    'queue' => $resolved['queue'],
                    'label_ids' => $resolved['label_ids'] ?? [],
                    'created_via' => $resolved['created_via'] ?? null,
                ], $batch);

                if ($post->status === PostStatus::Publishing) {
                    PublishPost::dispatch($post)->afterCommit();
                }

                return $post;
            });

            if ($afterCreate !== null) {
                $afterCreate($posts);
            }

            return $posts;
        });

        if ($resolved['queue'] === null) {
            return $create();
        }

        return ReflowChannelQueue::withLock(
            collect($resolved['destinations'])->pluck('social_account_id')->all(),
            $create,
        );
    }
}
