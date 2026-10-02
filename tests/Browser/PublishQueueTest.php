<?php

declare(strict_types=1);

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Enums\Post\QueuePosition;
use App\Enums\Post\ScheduleMode;
use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PostingSchedule;
use Carbon\CarbonInterface;

function waitForPublishQueueTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (document.querySelector('[data-testid="{$testId}"]')?.getBoundingClientRect().height > 0) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForPublishQueueCondition(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if ({$condition}) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForPublishQueueOrder(mixed $page, Post $first, Post $second): void
{
    for ($attempt = 0; $attempt < 50 && ! $first->refresh()->scheduled_at->lessThan($second->refresh()->scheduled_at); $attempt++) {
        $page->script('new Promise((resolve) => setTimeout(resolve, 100))');
    }
}

/**
 * @return array{0: User, 1: Workspace, 2: SocialAccount}
 */
function publishQueueSetup(): array
{
    $user = User::factory()->create(['timezone' => 'UTC']);
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $schedule = PostingSchedule::empty();

    foreach (range(0, 6) as $day) {
        $schedule = $schedule->withTime($day, '09:00')->withTime($day, '12:00')->withTime($day, '18:00');
    }

    $channel = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $workspace->id,
        'timezone' => 'UTC',
        'posting_schedule' => $schedule,
    ]);

    return [$user, $workspace, $channel];
}

function publishQueuePost(User $user, SocialAccount $channel, QueuePosition $position = QueuePosition::Next): Post
{
    $post = Post::factory()->create([
        'workspace_id' => $channel->workspace_id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'schedule_mode' => ScheduleMode::Queue,
        'scheduled_at' => null,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $channel->id,
        'platform' => $channel->platform,
        'enabled' => true,
    ]);

    ReflowChannelQueue::handle($channel, $post, $position);

    return $post->refresh();
}

function publishQueueSlotKey(SocialAccount $channel, CarbonInterface $slot): string
{
    return "{$channel->id}-{$slot->getTimestamp()}";
}

function publishQueueVisitWithCleanStorage(string $url): mixed
{
    $page = visit($url);
    $page->script('window.localStorage.clear()');

    return $page->refresh();
}

test('empty posting times show as slots and a slot opens the composer in queue mode', function () {
    [$user, , $channel] = publishQueueSetup();
    $slot = $channel->posting_schedule->nextSlots(now()->addMinute(), $channel->timezone, 1)[0];
    $slotKey = publishQueueSlotKey($channel, $slot);
    $this->actingAs($user);

    $page = visit(route('app.posts.index'));
    waitForPublishQueueTestId($page, "queue-slot-{$slotKey}");

    $page->assertVisible("@queue-day-{$slot->utc()->format('Y-m-d')}")
        ->assertVisible("@queue-slot-{$slotKey}")
        ->click("@queue-slot-new-{$slotKey}");

    waitForPublishQueueTestId($page, "composer-caption-{$channel->id}");
    $page->fill("@composer-caption-{$channel->id}", 'From an empty slot');
    waitForPublishQueueTestId($page, 'composer-submit');

    expect($page->script('document.querySelector(\'[data-testid="composer-submit"]\').dataset.scheduleMode'))->toBe('next');
    $page->assertNoJavaScriptErrors();
});

test('a member whose posts need approval can still open the composer from an empty slot', function () {
    [, $workspace, $channel] = publishQueueSetup();
    $requester = workspaceMember($workspace, 'approval', ['timezone' => 'UTC']);
    $slot = $channel->posting_schedule->nextSlots(now()->addMinute(), $channel->timezone, 1)[0];
    $slotKey = publishQueueSlotKey($channel, $slot);
    $this->actingAs($requester);

    $page = visit(route('app.posts.index'));
    waitForPublishQueueTestId($page, "queue-slot-new-{$slotKey}");

    $page->assertSeeIn("@queue-slot-new-{$slotKey}", 'New')
        ->click("@queue-slot-new-{$slotKey}");

    waitForPublishQueueTestId($page, "composer-caption-{$channel->id}");
    $page->assertVisible("@composer-caption-{$channel->id}")
        ->assertNoJavaScriptErrors();
});

test('scheduled posts replace the slots with a list of only those posts', function () {
    [$user, , $channel] = publishQueueSetup();
    $queued = publishQueuePost($user, $channel);
    $custom = Post::factory()->create([
        'workspace_id' => $channel->workspace_id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'schedule_mode' => ScheduleMode::Custom,
        'scheduled_at' => now()->utc()->addDays(120)->setTime(15, 30),
    ]);
    PostPlatform::factory()->create([
        'post_id' => $custom->id,
        'social_account_id' => $channel->id,
        'platform' => $channel->platform,
        'enabled' => true,
    ]);
    $this->actingAs($user);

    $page = publishQueueVisitWithCleanStorage(route('app.posts.index'));
    waitForPublishQueueTestId($page, "post-card-{$custom->id}");

    $page->assertVisible("@post-card-{$queued->id}")
        ->assertPresent("@queue-day-{$custom->scheduled_at->format('Y-m-d')}")
        ->assertAttribute("@post-schedule-mode-{$custom->id}", 'data-mode', 'custom')
        ->assertMissing("@post-schedule-mode-{$queued->id}")
        ->assertMissing('@queue-more-times');

    expect($page->script('document.querySelectorAll(\'[data-testid^="queue-slot-"]\').length'))->toBe(0);
    $page->assertNoJavaScriptErrors();
});

test('move down from the card menu swaps a queued post with the next one', function () {
    [$user, , $channel] = publishQueueSetup();
    $a = publishQueuePost($user, $channel);
    $b = publishQueuePost($user, $channel);
    $this->actingAs($user);

    $page = visit(route('app.channels.publish', $channel));
    waitForPublishQueueTestId($page, "post-card-menu-{$a->id}");
    $page->click("@post-card-menu-{$a->id}");
    waitForPublishQueueTestId($page, "post-move-down-{$a->id}");
    $page->click("@post-move-down-{$a->id}");
    waitForPublishQueueOrder($page, $b, $a);

    expect($b->refresh()->scheduled_at->lessThan($a->refresh()->scheduled_at))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});

test('dragging a queued post above another reorders the queue', function () {
    [$user, , $channel] = publishQueueSetup();
    $a = publishQueuePost($user, $channel);
    $b = publishQueuePost($user, $channel);
    $this->actingAs($user);

    $page = visit(route('app.channels.publish', $channel));
    waitForPublishQueueTestId($page, "post-drag-handle-{$b->id}");
    $page->drag("@post-card-{$b->id}", "@post-card-{$a->id}");
    waitForPublishQueueOrder($page, $b, $a);

    expect($b->refresh()->scheduled_at->lessThan($a->refresh()->scheduled_at))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});

test('a stale reorder is rolled back and reported', function () {
    [$user, , $channel] = publishQueueSetup();
    $a = publishQueuePost($user, $channel);
    $b = publishQueuePost($user, $channel);
    $this->actingAs($user);

    $page = visit(route('app.channels.publish', $channel));
    waitForPublishQueueTestId($page, "post-card-menu-{$a->id}");
    $top = publishQueuePost($user, $channel, QueuePosition::Top);
    $page->click("@post-card-menu-{$a->id}");
    waitForPublishQueueTestId($page, "post-move-down-{$a->id}");
    $page->click("@post-move-down-{$a->id}");
    waitForPublishQueueTestId($page, 'queue-reorder-error-toast');

    $page->assertSeeIn('@queue-reorder-error-toast', __('posts.errors.queue_order_stale'));
    waitForPublishQueueTestId($page, "post-card-{$top->id}");
    $page->assertVisible("@post-card-{$top->id}");
    expect($page->script("document.querySelector('[data-post-id=\"{$a->id}\"]').compareDocumentPosition(document.querySelector('[data-post-id=\"{$b->id}\"]')) & Node.DOCUMENT_POSITION_FOLLOWING"))->toBeGreaterThan(0)
        ->and($a->refresh()->scheduled_at->lessThan($b->refresh()->scheduled_at))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});

test('the posting times toggle hides every slot and survives a reload', function () {
    [$user, , $channel] = publishQueueSetup();
    $slot = $channel->posting_schedule->nextSlots(now()->addMinute(), $channel->timezone, 1)[0];
    $slotKey = publishQueueSlotKey($channel, $slot);
    $this->actingAs($user);

    $page = publishQueueVisitWithCleanStorage(route('app.channels.publish', $channel));
    waitForPublishQueueTestId($page, "queue-slot-{$slotKey}");
    $page->click('@publish-menu');
    waitForPublishQueueTestId($page, 'publish-toggle-slots');
    $page->click('@publish-toggle-slots');
    waitForPublishQueueCondition($page, '!document.querySelector(\'[data-testid^="queue-slot-"]\')');

    expect($page->script('document.querySelectorAll(\'[data-testid^="queue-slot-"]\').length'))->toBe(0);

    $page->refresh();
    waitForPublishQueueTestId($page, 'publish-page');

    expect($page->script('document.querySelectorAll(\'[data-testid^="queue-slot-"]\').length'))->toBe(0);
    $page->assertNoJavaScriptErrors();
});

test('a far display time zone regroups slots under its own calendar day', function () {
    [$user, , $channel] = publishQueueSetup();
    $slot = now()->utc()->addDay()->setTime(18, 0);
    $slotKey = publishQueueSlotKey($channel, $slot);
    $utcDay = $slot->format('Y-m-d');
    $aucklandDay = $slot->setTimezone('Pacific/Auckland')->format('Y-m-d');
    $this->actingAs($user);

    $page = publishQueueVisitWithCleanStorage(route('app.posts.index'));
    waitForPublishQueueTestId($page, "queue-slot-{$slotKey}");

    expect($page->script("!!document.querySelector('[data-testid=\"queue-day-{$utcDay}\"] [data-testid=\"queue-slot-{$slotKey}\"]')"))->toBeTrue();

    $page->click('@publish-timezone-trigger');
    waitForPublishQueueTestId($page, 'publish-timezone-search');
    $page->type('@publish-timezone-search', 'Auckland');
    waitForPublishQueueTestId($page, 'publish-timezone-option-Pacific-Auckland');
    $page->click('@publish-timezone-option-Pacific-Auckland');
    waitForPublishQueueCondition($page, "!!document.querySelector('[data-testid=\"queue-day-{$aucklandDay}\"] [data-testid=\"queue-slot-{$slotKey}\"]')");

    expect($page->script('new URLSearchParams(location.search).get("tz")'))->toBe('Pacific/Auckland')
        ->and($page->script("!!document.querySelector('[data-testid=\"queue-day-{$aucklandDay}\"] [data-testid=\"queue-slot-{$slotKey}\"]')"))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});

test('changing the time zone keeps a tab that came from a notes link', function () {
    [$user, , $channel] = publishQueueSetup();
    $draft = Post::factory()->create([
        'workspace_id' => $channel->workspace_id,
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
        'scheduled_at' => null,
    ]);
    PostPlatform::factory()->create([
        'post_id' => $draft->id,
        'social_account_id' => $channel->id,
        'platform' => $channel->platform,
        'enabled' => true,
    ]);
    $this->actingAs($user);

    $page = publishQueueVisitWithCleanStorage(route('app.posts.index', ['notes' => $draft->id]));
    waitForPublishQueueTestId($page, "post-card-{$draft->id}");
    $page->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))");

    $page->click('@publish-timezone-trigger');
    waitForPublishQueueTestId($page, 'publish-timezone-search');
    $page->type('@publish-timezone-search', 'Auckland');
    waitForPublishQueueTestId($page, 'publish-timezone-option-Pacific-Auckland');
    $page->click('@publish-timezone-option-Pacific-Auckland');
    waitForPublishQueueCondition($page, 'new URLSearchParams(location.search).get("tz") === "Pacific/Auckland"');
    waitForPublishQueueTestId($page, "post-card-{$draft->id}");

    expect($page->script('new URLSearchParams(location.search).get("tab")'))->toBe('drafts')
        ->and($page->script('new URLSearchParams(location.search).has("notes")'))->toBeFalse();
    $page->assertVisible("@post-card-{$draft->id}")->assertNoJavaScriptErrors();
});

test('more times extends the queue range', function () {
    [$user] = publishQueueSetup();
    $farDay = now()->utc()->addDays(20)->format('Y-m-d');
    $this->actingAs($user);

    $page = visit(route('app.posts.index'));
    waitForPublishQueueTestId($page, 'queue-more-times');
    $page->assertMissing("@queue-day-{$farDay}")
        ->click('@queue-more-times');
    waitForPublishQueueTestId($page, "queue-day-{$farDay}");

    $page->assertPresent("@queue-day-{$farDay}")
        ->assertNoJavaScriptErrors();
});

test('a failed post is listed under needs attention', function () {
    [$user, $workspace, $channel] = publishQueueSetup();
    $failed = Post::factory()->failed()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    PostPlatform::factory()->failed()->create([
        'post_id' => $failed->id,
        'social_account_id' => $channel->id,
        'platform' => $channel->platform,
        'enabled' => true,
    ]);
    $this->actingAs($user);

    $page = visit(route('app.posts.index'));
    waitForPublishQueueTestId($page, 'needs-attention');

    expect($page->script("!!document.querySelector('[data-testid=\"needs-attention\"] [data-post-id=\"{$failed->id}\"]')"))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});
