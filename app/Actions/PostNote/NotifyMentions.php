<?php

declare(strict_types=1);

namespace App\Actions\PostNote;

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Jobs\SendNotification;
use App\Mail\MentionedInComment;
use App\Models\PostNote;
use App\Models\User;
use App\Support\MentionParser;
use App\Support\WorkspacePresence;
use Illuminate\Support\Str;

final class NotifyMentions
{
    /**
     * Dispatch in-app + email notifications for every workspace member that has
     * been mentioned in a note but was not mentioned in its previous body.
     * The note author is never notified about their own mention.
     */
    public static function execute(PostNote $comment, ?string $previousBody = null): void
    {
        $newIds = MentionParser::extractUserIds($comment->body ?? '');

        if (empty($newIds)) {
            return;
        }

        $previousIds = $previousBody !== null ? MentionParser::extractUserIds($previousBody) : [];
        $diff = array_values(array_diff($newIds, $previousIds, [$comment->user_id]));

        if (empty($diff)) {
            return;
        }

        $post = $comment->post()->with('workspace')->first();
        if (! $post || ! $post->workspace) {
            return;
        }

        $workspace = $post->workspace;

        $eligibleUsers = $workspace->members()
            ->whereIn('users.id', $diff)
            ->get();

        if ($eligibleUsers->isEmpty()) {
            return;
        }

        $author = $comment->user()->first();
        if (! $author instanceof User) {
            return;
        }

        $excerpt = Str::limit(self::stripMarkers($comment->body ?? ''), 140);

        foreach ($eligibleUsers as $member) {
            // If the recipient is currently active in the workspace (heartbeat
            // within the last minute), they will see the in-app notification
            // immediately — skip the email to avoid noise.
            $online = WorkspacePresence::isOnline($workspace->id, $member->id);
            $channel = $online ? Channel::InApp : Channel::Both;

            SendNotification::dispatch(
                user: $member,
                workspaceId: $workspace->id,
                type: Type::MentionedInComment,
                channel: $channel,
                title: __('mail.mentioned_in_comment.title', ['name' => $author->name], $member->preferredLocale()),
                body: $excerpt,
                data: [
                    'post_id' => $post->id,
                    'note_id' => $comment->id,
                    'comment_id' => $comment->id,
                    'parent_id' => $comment->parent_id,
                    'author_id' => $comment->user_id,
                    'author_name' => $author->name,
                ],
                mailable: $online ? null : new MentionedInComment(
                    comment: $comment,
                    author: $author,
                    excerpt: $excerpt,
                ),
            );
        }
    }

    private static function stripMarkers(string $body): string
    {
        return trim((string) preg_replace('/@\[[0-9a-fA-F-]{36}\]/', '@…', $body));
    }
}
