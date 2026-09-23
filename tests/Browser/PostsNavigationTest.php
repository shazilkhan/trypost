<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;

function postsNavigationState(mixed $page): array
{
    return $page->script(<<<'JS'
        (() => {
            const url = new URL(window.location.href);
            const postsLinks = Array.from(document.querySelectorAll('[data-testid="nav-/posts"]'));
            const visiblePostsLinks = postsLinks.filter((link) => link.getBoundingClientRect().height > 0);
            const postsLink = visiblePostsLinks[0];

            return {
                pathname: url.pathname,
                tab: url.searchParams.get('tab'),
                view: url.searchParams.get('view'),
                search: url.searchParams.get('search'),
                labels: Array.from(url.searchParams.entries())
                    .filter(([key]) => key === 'labels[]' || key.startsWith('labels['))
                    .map(([, value]) => value),
                visiblePostsLinks: visiblePostsLinks.length,
                sidebarActive: postsLink?.closest('[data-active="true"]') !== null,
            };
        })();
    JS);
}

function clickPostsNavigationAndWait(
    mixed $page,
    string $testId,
    string $pathname,
    ?string $queryKey = null,
    ?string $queryValue = null,
): void {
    $queryCondition = $queryKey === null
        ? 'true'
        : "new URL(window.location.href).searchParams.get('{$queryKey}') === '{$queryValue}'";

    $page->script(<<<JS
        (async () => {
            const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

            for (let i = 0; i < 100; i++) {
                const link = document.querySelector('[data-testid="{$testId}"]');

                if (link && link.getBoundingClientRect().height > 0) {
                    link.click();
                    break;
                }

                await wait(50);
            }

            for (let i = 0; i < 150; i++) {
                if (window.location.pathname === '{$pathname}' && {$queryCondition}) {
                    await wait(250);
                    return;
                }

                await wait(50);
            }
        })();
    JS);
}

test('posts list tabs and calendar switch share one canonical navigation', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id]);

    subscribeAccount($user->account);
    $this->actingAs($user);

    $page = visit(route('app.posts.index', [
        'search' => 'launch',
        'labels' => [$label->id],
    ]));

    $page->assertVisible('@header-title')
        ->assertVisible('@posts-view-list')
        ->assertVisible('@posts-view-calendar')
        ->assertVisible('@posts-tabs')
        ->assertVisible('@posts-tab-all')
        ->assertVisible('@posts-tab-scheduled')
        ->assertVisible('@posts-tab-draft')
        ->assertVisible('@posts-tab-published')
        ->assertVisible('@posts-search');

    expect(postsNavigationState($page))
        ->pathname->toBe('/posts')
        ->search->toBe('launch')
        ->labels->toBe([$label->id])
        ->visiblePostsLinks->toBe(1)
        ->sidebarActive->toBeTrue();

    clickPostsNavigationAndWait(
        $page,
        'posts-tab-scheduled',
        '/posts',
        'tab',
        'scheduled',
    );

    expect(postsNavigationState($page))
        ->pathname->toBe('/posts')
        ->tab->toBe('scheduled')
        ->search->toBe('launch')
        ->labels->toBe([$label->id])
        ->sidebarActive->toBeTrue();

    clickPostsNavigationAndWait(
        $page,
        'posts-view-calendar',
        '/calendar',
        'view',
        'week',
    );

    $page->assertVisible('@posts-view-list')
        ->assertVisible('@posts-view-calendar')
        ->assertMissing('@posts-tabs')
        ->assertMissing('@posts-search')
        ->assertSee(__('calendar.day'))
        ->assertSee(__('calendar.week'))
        ->assertSee(__('calendar.month'));

    expect(postsNavigationState($page))
        ->pathname->toBe('/calendar')
        ->view->toBe('week')
        ->visiblePostsLinks->toBe(1)
        ->sidebarActive->toBeTrue();

    clickPostsNavigationAndWait($page, 'posts-view-list', '/posts');

    $page->assertVisible('@posts-tabs')
        ->assertVisible('@posts-search')
        ->assertNoJavaScriptErrors();

    expect(postsNavigationState($page))
        ->pathname->toBe('/posts')
        ->tab->toBeNull()
        ->sidebarActive->toBeTrue();
});
