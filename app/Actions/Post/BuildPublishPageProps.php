<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Analytics\ReadPublicationAnalytics;
use App\Actions\Post\Queue\BuildQueueTimeline;
use App\Actions\SocialAccount\CountPostsSentThisWeek;
use App\Enums\Post\Status as PostStatus;
use App\Http\Resources\App\SocialAccountResource;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PostStatusRules;
use App\Support\RequestIds;
use App\Support\Timezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BuildPublishPageProps
{
    public const TAB_QUEUE = 'queue';

    public const TAB_DRAFTS = 'drafts';

    public const TAB_SENT = 'sent';

    public const MIN_QUEUE_DAYS = 14;

    public const MAX_QUEUE_DAYS = 90;

    public const SENT_STATUSES = [
        PostStatus::Published,
        PostStatus::PartiallyPublished,
        PostStatus::Failed,
    ];

    private const NEEDS_ATTENTION_STATUSES = [
        PostStatus::Failed,
        PostStatus::Publishing,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function handle(Request $request, Workspace $workspace, ?SocialAccount $channel, ?Post $editPost = null): array
    {
        $user = $request->user();
        $userTimezone = Timezone::normalize($user->timezone);
        $displayTimezone = self::displayTimezone($request->query('tz'), $userTimezone);

        $labelIds = RequestIds::uuidList($request->collect('labels'));
        $untagged = $request->boolean('untagged');
        $requestedChannelIds = $channel ? [] : RequestIds::uuidList($request->collect('channels')->unique());

        $workspaceAccounts = null;
        $filterAccounts = function () use (&$workspaceAccounts, $channel, $workspace): Collection {
            return $workspaceAccounts ??= $channel ? collect() : $workspace->socialAccounts()->get();
        };

        $channels = fn (): Collection => match (true) {
            $channel !== null => collect([$channel]),
            $requestedChannelIds !== [] => $filterAccounts()->whereIn('id', $requestedChannelIds)->values(),
            default => $filterAccounts(),
        };

        $scopedChannelIds = $channel ? [$channel->id] : ($requestedChannelIds !== [] ? $requestedChannelIds : null);

        $basePosts = $workspace->posts()->when($scopedChannelIds !== null, fn (Builder $query) => $query->whereHas(
            'postPlatforms',
            fn (Builder $platforms) => $platforms->enabled()->whereIn('social_account_id', $scopedChannelIds),
        ));

        $openPostNotesId = $request->query('notes');
        $openPostNotesId = is_string($openPostNotesId) && Str::isUuid($openPostNotesId) ? $openPostNotesId : null;

        $tab = self::tab($request->query('tab'), $openPostNotesId ? (clone $basePosts)->whereKey($openPostNotesId)->value('status') : null);

        $cards = fn (): Builder => self::cardQuery(clone $basePosts, $scopedChannelIds, $labelIds, $untagged);

        $props = [
            'workspace' => $workspace,
            'scope' => $channel ? 'channel' : 'all',
            'channel' => fn (): ?array => $channel ? self::channelHeader($channel) : null,
            'tab' => $tab,
            'counts' => fn (): array => self::counts(clone $basePosts),
            'displayTimezone' => $displayTimezone,
            'timezones' => fn (): array => Timezone::options(),
            'channelTimezones' => fn (): array => $channels()
                ->map(fn (SocialAccount $account): string => Timezone::normalize($account->timezone))
                ->reject(fn (string $timezone): bool => $timezone === $userTimezone)
                ->unique()
                ->values()
                ->all(),
            'labels' => fn () => $workspace->labels()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => [
                'labels' => $labelIds,
                'untagged' => $untagged,
                'channels' => $requestedChannelIds,
            ],
            'filterAccounts' => fn () => SocialAccountResource::collection($filterAccounts()),
        ];

        if ($tab === self::TAB_QUEUE) {
            $props['queue'] = fn (): array => self::queue($workspace, $channels(), $displayTimezone, $request, $labelIds, $untagged, $cards);
        }

        $props['posts'] = Inertia::scroll(fn () => self::paginatedCards($tab, $cards(), $tab === self::TAB_QUEUE ? null : $openPostNotesId));

        $composerRequested = $editPost !== null;

        return [
            ...$props,
            'openComposer' => $composerRequested,
            'openComposerAssistant' => $request->boolean('assistant'),
            'initialComposerDate' => $request->query('date'),
            'openPostNotesId' => $openPostNotesId,
            'highlightNoteId' => is_string($request->query('note')) ? $request->query('note') : null,
            'authUserId' => $user->id,
            'editPost' => $editPost,
            ...BuildComposerProps::lazy($workspace, $composerRequested),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function channelHeader(SocialAccount $channel): array
    {
        return [
            ...SocialAccountResource::make($channel)->resolve(),
            'posting_goal' => $channel->posting_goal,
            'sent_this_week' => CountPostsSentThisWeek::handle($channel),
        ];
    }

    public static function displayTimezone(mixed $requested, string $userTimezone): string
    {
        if (! is_string($requested) || $requested === '') {
            return $userTimezone;
        }

        $normalized = Timezone::normalize($requested);

        return $normalized === Timezone::DEFAULT && $requested !== Timezone::DEFAULT ? $userTimezone : $normalized;
    }

    private static function tab(mixed $requested, ?PostStatus $notesPostStatus): string
    {
        if (blank($requested) && $notesPostStatus !== null) {
            return match (true) {
                $notesPostStatus === PostStatus::Draft => self::TAB_DRAFTS,
                in_array($notesPostStatus, self::SENT_STATUSES, true) => self::TAB_SENT,
                default => self::TAB_QUEUE,
            };
        }

        return match ($requested) {
            self::TAB_DRAFTS => self::TAB_DRAFTS,
            self::TAB_SENT => self::TAB_SENT,
            default => self::TAB_QUEUE,
        };
    }

    /**
     * @return array{queue: int, drafts: int, sent: int}
     */
    private static function counts(HasMany $basePosts): array
    {
        $byStatus = $basePosts
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);

        return [
            'queue' => $byStatus->get(PostStatus::Scheduled->value, 0),
            'drafts' => $byStatus->get(PostStatus::Draft->value, 0),
            'sent' => collect(self::SENT_STATUSES)->sum(fn (PostStatus $status): int => $byStatus->get($status->value, 0)),
        ];
    }

    /**
     * @param  list<string>|null  $channelIds
     * @param  list<string>  $labelIds
     */
    private static function cardQuery(HasMany $basePosts, ?array $channelIds, array $labelIds, bool $untagged): Builder
    {
        return $basePosts->getQuery()
            ->with([
                'postPlatforms' => fn ($platforms) => $platforms->enabled()
                    ->when($channelIds !== null, fn ($platforms) => $platforms->whereIn('social_account_id', $channelIds))
                    ->with('socialAccount'),
                'user.avatarMedia',
                'labels',
            ])
            ->withCount('notes')
            ->matchingLabelFilter($labelIds, $untagged);
    }

    /**
     * @param  Collection<int, SocialAccount>  $channels
     * @param  list<string>  $labelIds
     * @param  callable(): Builder  $cards
     * @return array{days: list<array<string, mixed>>, needsAttention: list<Post>, queueDays: int, maxQueueDays: int}
     */
    private static function queue(Workspace $workspace, Collection $channels, string $displayTimezone, Request $request, array $labelIds, bool $untagged, callable $cards): array
    {
        $queueDays = max(self::MIN_QUEUE_DAYS, min(self::MAX_QUEUE_DAYS, $request->integer('queue_days', self::MIN_QUEUE_DAYS)));

        $days = $cards()->where('status', PostStatus::Scheduled)->exists()
            ? []
            : BuildQueueTimeline::handle($workspace, $channels, $displayTimezone, now()->addDays($queueDays), $labelIds, $untagged);

        $needsAttention = $cards()
            ->whereIn('status', self::NEEDS_ATTENTION_STATUSES)
            ->latest('updated_at')
            ->limit((int) config('app.pagination.default'))
            ->get();

        self::decorate($needsAttention);

        return [
            'days' => $days,
            'needsAttention' => $needsAttention->values()->all(),
            'queueDays' => $queueDays,
            'maxQueueDays' => self::MAX_QUEUE_DAYS,
        ];
    }

    private static function paginatedCards(string $tab, Builder $query, ?string $openPostNotesId): LengthAwarePaginator
    {
        $query->when($openPostNotesId, fn (Builder $query) => $query->whereKey($openPostNotesId));

        match ($tab) {
            self::TAB_QUEUE => $query->where('status', PostStatus::Scheduled)
                ->orderBy('posts.scheduled_at'),
            self::TAB_DRAFTS => $query->where('status', PostStatus::Draft)
                ->orderByRaw('CASE WHEN posts.scheduled_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('posts.scheduled_at')
                ->latest('posts.created_at'),
            default => $query->whereIn('status', self::SENT_STATUSES)
                ->orderByRaw('COALESCE(posts.published_at, posts.updated_at) DESC'),
        };

        $paginator = $query->orderBy('posts.id')->paginate((int) config('app.pagination.default'));

        self::decorate($paginator->getCollection());

        if ($tab === self::TAB_SENT) {
            self::attachMetrics($paginator->getCollection());
        }

        return $paginator;
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private static function decorate(Collection $posts): void
    {
        $hasSchedule = [];
        $posts->pluck('user')->filter()->each(fn (User $user) => $user->makeHidden('avatarMedia'));

        foreach ($posts as $post) {
            $post->setAttribute('can_delete', ! PostStatusRules::blocksDeletion($post));

            foreach ($post->postPlatforms as $platform) {
                $account = $platform->socialAccount;

                if ($account === null) {
                    continue;
                }

                $hasSchedule[$account->id] ??= $account->hasPostingSchedule();
                $account->setAttribute('has_posting_schedule', $hasSchedule[$account->id]);
            }
        }
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private static function attachMetrics(Collection $posts): void
    {
        $first = $posts->first();

        if ($first === null) {
            return;
        }

        $metrics = app(ReadPublicationAnalytics::class)->latestForPost(
            $first,
            $posts->flatMap(fn (Post $post) => $post->postPlatforms)->values(),
        );

        foreach ($posts as $post) {
            $post->setAttribute('metrics', $post->postPlatforms
                ->mapWithKeys(fn ($platform): array => [$platform->id => data_get($metrics, $platform->id)])
                ->all());
        }
    }
}
