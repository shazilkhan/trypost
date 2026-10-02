<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

function waitForHelpMenuTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector(sel);
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

test('the help button uses the strong brand color and opens the help menu', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    subscribeAccount($user->account);

    $this->actingAs($user);

    $page = visit(route('app.calendar'));

    waitForHelpMenuTestId($page, 'help-menu-trigger');

    $colors = $page->script(<<<'JS'
        (() => {
            const style = getComputedStyle(document.querySelector('[data-testid="help-menu-trigger"]'));
            return [style.backgroundColor, style.color];
        })()
    JS);

    expect($colors)->toBe(['rgb(109, 40, 217)', 'rgb(255, 255, 255)']);

    $page->click('@help-menu-trigger');
    waitForHelpMenuTestId($page, 'help-menu-content');

    $page->assertVisible('@help-menu-docs')
        ->assertVisible('@help-menu-discord')
        ->assertNoJavaScriptErrors();
});
