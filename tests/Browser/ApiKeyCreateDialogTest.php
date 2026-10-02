<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

function waitForApiKeyCreateTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 150; i++) {
                const el = document.querySelector(sel);
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

test('the expiry date picker matches the dialog inputs and opens a calendar', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user->fresh());

    $page = visit(route('app.api-keys.index'));
    waitForApiKeyCreateTestId($page, 'create-api-key-button');
    $page->click('@create-api-key-button');
    waitForApiKeyCreateTestId($page, 'token-expires-trigger');

    $page->assertScript(
        "(() => { const a = document.getElementById('token-name').getBoundingClientRect(); const b = document.getElementById('token-expires').getBoundingClientRect(); return Math.round(a.height) === Math.round(b.height) && getComputedStyle(document.getElementById('token-expires')).fontWeight === '400'; })()",
        true,
    )->assertSeeIn('@token-expires-trigger', __('settings.api_keys.create_dialog.expires_placeholder'));

    $page->click('@token-expires-trigger');
    waitForApiKeyCreateTestId($page, 'token-expires-calendar');

    $page->assertVisible('@token-expires-calendar')->assertNoJavaScriptErrors();
});

test('a generated key shows only its last four characters and copies without a toast', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user->fresh());

    $page = visit(route('app.api-keys.index'));
    waitForApiKeyCreateTestId($page, 'create-api-key-button');
    $page->click('@create-api-key-button');
    waitForApiKeyCreateTestId($page, 'token-name');
    $page->fill('@token-name', 'Generated key');
    $page->click('@create-api-key-submit');
    waitForApiKeyCreateTestId($page, 'api-key-generated-dialog');

    $page->assertVisible('@api-key-generated-input')
        ->assertScript("(() => { const dialog = document.querySelector('[data-testid=\"api-key-generated-dialog\"]'); const field = document.querySelector('[data-testid=\"api-key-generated-input\"]'); return dialog.scrollWidth <= dialog.clientWidth && field.getBoundingClientRect().right <= dialog.getBoundingClientRect().right; })()", true)
        ->assertScript("document.querySelector('[data-testid=\"api-key-generated-dialog\"] [data-slot=\"dialog-close\"]') === null", true);

    $page->script('navigator.clipboard.writeText = async () => {};');
    $page->click('@api-key-generated-copy');
    waitForApiKeyCreateTestId($page, 'api-key-generated-copied');

    $page->assertVisible('@api-key-generated-copied')
        ->assertScript("document.querySelector('[data-sonner-toast]') === null", true)
        ->assertNoJavaScriptErrors();
});
