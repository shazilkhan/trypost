<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Pest\Browser\Playwright\Client;

function waitForSidebarReorderCondition(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let i = 0; i < 100; i++) {
                if ({$condition}) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

function waitForSidebarReorderTestId(mixed $page, string $testId): void
{
    waitForSidebarReorderCondition($page, "(() => { const el = document.querySelector('[data-testid=\"{$testId}\"]'); return el && el.getBoundingClientRect().height > 0; })()");
}

function sidebarReorderMouse(mixed $page, string $method, array $params = []): void
{
    $page->script('true');
    $awaitable = (fn () => $this->waitablePage)->call($page);
    $playwrightPage = (fn () => $this->page)->call($awaitable);
    $guid = (fn () => $this->guid)->call($playwrightPage);

    iterator_to_array(Client::instance()->execute($guid, $method, $params));
}

/**
 * @return array{x: float, y: float}
 */
function sidebarReorderPoint(mixed $page, string $testId, float $yRatio = 0.5): array
{
    return $page->script(<<<JS
        (() => {
            const rect = document.querySelector('[data-testid="{$testId}"]').getBoundingClientRect();
            return { x: rect.left + rect.width / 2, y: rect.top + rect.height * {$yRatio} };
        })()
    JS);
}

function sidebarReorderPickUp(mixed $page, SocialAccount $channel): void
{
    $page->hover("@sidebar-channel-row-{$channel->id}");
    $handle = sidebarReorderPoint($page, "sidebar-channel-handle-{$channel->id}");
    sidebarReorderMouse($page, 'mouseMove', ['x' => $handle['x'], 'y' => $handle['y']]);
    sidebarReorderMouse($page, 'mouseDown', ['button' => 'left', 'clickCount' => 1]);
    sidebarReorderMouse($page, 'mouseMove', ['x' => $handle['x'] + 8, 'y' => $handle['y'] + 2, 'steps' => 4]);
}

function sidebarReorderMoveTo(mixed $page, string $testId, float $yRatio): void
{
    $target = sidebarReorderPoint($page, $testId, $yRatio);
    sidebarReorderMouse($page, 'mouseMove', ['x' => $target['x'], 'y' => $target['y'], 'steps' => 8]);
}

function sidebarReorderDrop(mixed $page): void
{
    sidebarReorderMouse($page, 'mouseUp', ['button' => 'left', 'clickCount' => 1]);
}

function sidebarReorderDrag(mixed $page, SocialAccount $channel, SocialAccount $target, float $yRatio): void
{
    sidebarReorderPickUp($page, $channel);
    sidebarReorderMoveTo($page, "sidebar-channel-row-{$target->id}", $yRatio);
    sidebarReorderDrop($page);
}

/**
 * @return array{0: User, 1: list<SocialAccount>}
 */
function sidebarReorderSetup(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $channels = collect(['alpha', 'bravo', 'charlie', 'delta'])
        ->map(fn (string $name, int $index): SocialAccount => SocialAccount::factory()->linkedin()->create([
            'workspace_id' => $workspace->id,
            'display_name' => ucfirst($name),
            'position' => $index,
        ]))
        ->all();

    return [$user->fresh(), $channels];
}

function sidebarReorderDomOrder(mixed $page): array
{
    return $page->script(<<<'JS'
        [...document.querySelectorAll('[data-testid="sidebar-channels-list"] > [data-testid^="sidebar-channel-row-"]')]
            .map((row) => row.dataset.testid.replace('sidebar-channel-row-', ''))
    JS);
}

function sidebarReorderDbOrder(User $user): array
{
    return $user->currentWorkspace->socialAccounts()->pluck('id')->all();
}

function sidebarReorderSettle(mixed $page, User $user, array $expected): void
{
    $ids = json_encode($expected);

    for ($attempt = 0; $attempt < 50 && sidebarReorderDbOrder($user) !== $expected; $attempt++) {
        $page->script('new Promise((resolve) => setTimeout(resolve, 100))');
    }

    waitForSidebarReorderCondition($page, "JSON.stringify([...document.querySelectorAll('[data-testid=\"sidebar-channels-list\"] > [data-testid^=\"sidebar-channel-row-\"]')].map((row) => row.dataset.testid.replace('sidebar-channel-row-', ''))) === JSON.stringify({$ids}) && !document.querySelector('[data-testid=\"sidebar-channel-placeholder\"]')");
}

test('the second channel can be dragged to the top twice in a row', function () {
    [$user, [$alpha, $bravo, $charlie, $delta]] = sidebarReorderSetup();
    $this->actingAs($user);

    $page = visit(route('app.posts.index'));
    waitForSidebarReorderTestId($page, "sidebar-channel-{$delta->id}");

    sidebarReorderDrag($page, $bravo, $alpha, 0.25);
    $expected = [$bravo->id, $alpha->id, $charlie->id, $delta->id];
    sidebarReorderSettle($page, $user, $expected);

    expect(sidebarReorderDbOrder($user))->toBe($expected)
        ->and(sidebarReorderDomOrder($page))->toBe($expected);

    sidebarReorderDrag($page, $alpha, $bravo, 0.25);
    $expected = [$alpha->id, $bravo->id, $charlie->id, $delta->id];
    sidebarReorderSettle($page, $user, $expected);

    expect(sidebarReorderDbOrder($user))->toBe($expected)
        ->and(sidebarReorderDomOrder($page))->toBe($expected);

    $page->assertNoJavaScriptErrors();
});

test('probe', function () {
    [$user, [$alpha, $bravo, $charlie, $delta]] = sidebarReorderSetup();
    $this->actingAs($user);

    $page = visit(route('app.posts.index'));
    waitForSidebarReorderTestId($page, "sidebar-channel-{$delta->id}");
    $geo = $page->script(<<<'JS'
        (() => {
            const list = document.querySelector('[data-testid="sidebar-channels-list"]');
            const rows = [...list.querySelectorAll(':scope > li')].map((r) => { const b = r.getBoundingClientRect(); return [b.top, b.bottom]; });
            const l = list.getBoundingClientRect();
            return { list: [l.top, l.bottom], rows };
        })()
    JS);
    dump($geo);
    sidebarReorderPickUp($page, $bravo);
    $a = sidebarReorderPoint($page, "sidebar-channel-row-{$alpha->id}", 0.1);
    sidebarReorderMouse($page, 'mouseMove', ['x' => $a['x'], 'y' => $a['y'], 'steps' => 8]);
    $page->script('new Promise((resolve) => setTimeout(resolve, 300))');
    dump($page->script("[...document.querySelectorAll('[data-dragging]')].map((e) => e.dataset.testid)"));
    dump($page->script(<<<JS
        (() => {
            const el = document.querySelector('[data-testid="sidebar-channel-drop-indicator-{$alpha->id}"]');
            if (!el) return 'none';
            const b = el.getBoundingClientRect();
            const l = document.querySelector('[data-testid="sidebar-channels-list"]').getBoundingClientRect();
            return { top: b.top, listTop: l.top, clipped: b.bottom <= l.top };
        })()
    JS));
    sidebarReorderMouse($page, 'mouseMove', ['x' => $a['x'], 'y' => $geo['rows'][0][0] - 3, 'steps' => 4]);
    dump($page->script("document.querySelector('[data-testid^=\"sidebar-channel-drop-indicator-\"]')?.dataset.testid ?? 'none'"));
    sidebarReorderDrop($page);
    $page->script('new Promise((resolve) => setTimeout(resolve, 800))');
    dump(sidebarReorderDbOrder($user) === [$alpha->id, $bravo->id, $charlie->id, $delta->id] ? 'unchanged' : 'changed');
});
