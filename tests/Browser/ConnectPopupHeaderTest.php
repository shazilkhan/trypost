<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

function waitForConnectPopupTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 200; attempt++) {
                const element = document.querySelector('[data-testid="{$testId}"]');
                if (element && element.getBoundingClientRect().height > 0) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

test('a connect popup opens with the TryPost mark next to the network logo', function (string $route, string $platform, string $title) {
    $expectedTitle = __($title);
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $this->actingAs($user->fresh());

    $page = visit(route($route))->resize(600, 700);
    waitForConnectPopupTestId($page, 'connect-popup-header');

    expect($page->script(<<<'JS'
        (() => {
            const header = document.querySelector('[data-testid="connect-popup-header"]');
            return {
                mark: header.querySelector('[data-testid="app-logo"] svg') !== null,
                network: header.querySelector('img')?.getAttribute('src') ?? null,
                title: header.querySelector('h1').textContent.trim(),
            };
        })()
    JS))->toMatchArray([
        'mark' => true,
        'network' => "/images/accounts/{$platform}.png",
        'title' => $expectedTitle,
    ]);

    $page->assertNoJavaScriptErrors();
})->with([
    'bluesky' => ['app.social.bluesky.connect', 'bluesky', 'accounts.bluesky.title'],
    'mastodon' => ['app.social.mastodon.connect', 'mastodon', 'accounts.mastodon.title'],
]);

test('the bluesky and mastodon forms use the standard field layout with hints under the inputs', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $this->actingAs($user->fresh());

    $page = visit(route('app.social.bluesky.connect'))->resize(600, 700);
    waitForConnectPopupTestId($page, 'bluesky-app-password-hint');

    $page->assertSee('Handle or email')
        ->assertVisible('@bluesky-app-password-hint')
        ->assertNoJavaScriptErrors();
    expect($page->script("document.querySelectorAll('[role=\"alert\"], [data-slot=\"alert\"]').length"))->toBe(0);

    $page = visit(route('app.social.mastodon.connect'))->resize(600, 700);
    waitForConnectPopupTestId($page, 'mastodon-instance-hint');

    $page->assertSee('For example: mastodon.social or techhub.social.')
        ->assertNoJavaScriptErrors();
    expect($page->script("document.querySelectorAll('[role=\"alert\"], [data-slot=\"alert\"]').length"))->toBe(0);
});
