<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Post\Approval\NotifyApprovalRequested;
use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Media\MediaCopyBatch;
use App\Support\PostApproval;
use App\Support\PostCompositionValidator;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $groupId = (string) Str::uuid7();
        $pending = PostApproval::isRequired($workspace, $user, (string) data_get($resolved, 'status'));
        $status = $pending ? PostStatus::PendingApproval->value : data_get($resolved, 'status');
        $scheduledAt = $pending && data_get($resolved, 'status') === PostStatus::Publishing->value
            ? null
            : data_get($resolved, 'scheduled_at');

        $create = fn (): Collection => MediaCopyBatch::run(function (MediaCopyBatch $batch) use ($workspace, $user, $resolved, $groupId, $status, $scheduledAt, $beforeCreate, $afterCreate, $legacyMedia): Collection {
            if ($beforeCreate !== null) {
                $beforeCreate($batch);
            }

            $posts = collect($resolved['destinations'])->map(function (array $destination) use ($workspace, $user, $resolved, $groupId, $status, $scheduledAt, $batch, $legacyMedia): Post {
                $post = CreateChannelPost::execute($workspace, $user, [
                    ...$destination,
                    'post_group_id' => $groupId,
                    'legacy_media' => $legacyMedia,
                    'status' => $status,
                    'scheduled_at' => $scheduledAt,
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

        $posts = $resolved['queue'] === null || $pending
            ? $create()
            : ReflowChannelQueue::withLock(
                collect($resolved['destinations'])->pluck('social_account_id')->all(),
                $create,
            );

        if ($pending) {
            NotifyApprovalRequested::execute($posts, $user);
        }

        return $posts;
    }
}
