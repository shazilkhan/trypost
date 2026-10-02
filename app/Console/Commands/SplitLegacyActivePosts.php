<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Post\Status;
use App\Models\Post;
use App\Models\PostNote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('posts:split-legacy-active')]
#[Description('Split editable multi-target posts into one post per enabled target and group every multi-target post')]
class SplitLegacyActivePosts extends Command
{
    public function handle(): int
    {
        $splitPosts = 0;
        $createdPosts = 0;

        Post::query()
            ->whereIn('status', [Status::Draft, Status::Scheduled])
            ->whereHas('postPlatforms', fn ($query) => $query->enabled(), '>', 1)
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use (&$splitPosts, &$createdPosts): void {
                foreach ($posts as $candidate) {
                    $clones = DB::transaction(fn (): int => $this->splitPost($candidate->id));
                    if ($clones > 0) {
                        $splitPosts++;
                        $createdPosts += $clones;
                    }
                }
            });

        $this->info("Split {$splitPosts} original posts and created {$createdPosts} independent posts.");

        $groupedPosts = 0;

        Post::query()
            ->whereNull('post_group_id')
            ->has('postPlatforms', '>', 1)
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use (&$groupedPosts): void {
                foreach ($posts as $post) {
                    $groupedPosts += Post::query()
                        ->whereKey($post->id)
                        ->whereNull('post_group_id')
                        ->toBase()
                        ->update(['post_group_id' => (string) Str::uuid7()]);
                }
            });

        $this->info("Grouped {$groupedPosts} multi-target posts.");

        return self::SUCCESS;
    }

    private function splitPost(string $postId): int
    {
        $post = Post::query()->lockForUpdate()->findOrFail($postId);
        if (! in_array($post->status, [Status::Draft, Status::Scheduled], true)) {
            return 0;
        }

        $targets = $post->postPlatforms()->enabled()->orderBy('id')->lockForUpdate()->get();
        if ($targets->count() <= 1) {
            return 0;
        }

        if ($post->post_group_id === null) {
            $groupId = (string) Str::uuid7();
            Post::query()->whereKey($post->id)->toBase()->update(['post_group_id' => $groupId]);
            $post->forceFill(['post_group_id' => $groupId])->syncOriginal();
        }

        $labelIds = $post->labels()->pluck('workspace_labels.id')->all();
        $notes = $post->notes()->orderBy('id')->get();

        foreach ($targets->skip(1) as $target) {
            $clone = Post::withoutEvents(function () use ($post): Post {
                $clone = $post->replicate();
                $clone->created_at = $post->created_at;
                $clone->updated_at = $post->updated_at;
                $clone->saveQuietly();

                return $clone;
            });
            $clone->labels()->sync($labelIds);

            foreach ($notes as $note) {
                PostNote::withoutEvents(function () use ($note, $clone): void {
                    $copy = $note->replicate();
                    $copy->post_id = $clone->id;
                    $copy->created_at = $note->created_at;
                    $copy->updated_at = $note->updated_at;
                    $copy->saveQuietly();
                });
            }

            $target->updateQuietly(['post_id' => $clone->id]);
        }

        return $targets->count() - 1;
    }
}
