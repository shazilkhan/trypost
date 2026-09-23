<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\Workspace;

test('the login screen uses the TryPost Connect design system', function () {
    $page = visit(route('login'));

    $page->assertVisible('@login-submit')
        ->assertNoJavaScriptErrors();

    $design = $page->script(<<<'JS'
        (() => {
            const root = getComputedStyle(document.documentElement);
            const button = getComputedStyle(document.querySelector('[data-testid="login-submit"]'));

            return {
                background: root.getPropertyValue('--background').trim(),
                primary: root.getPropertyValue('--primary').trim(),
                border: root.getPropertyValue('--border').trim(),
                radius: root.getPropertyValue('--radius').trim(),
                displayFont: root.getPropertyValue('--font-display').trim(),
                buttonBackground: button.backgroundColor,
                buttonHeight: button.height,
            };
        })();
    JS);

    expect($design)
        ->background->toBeIn(['#fff', '#ffffff'])
        ->primary->toBe('#fa5d19')
        ->border->toBe('#e9e6e3')
        ->radius->toBeIn(['0.525rem', '.525rem'])
        ->displayFont->toContain('Inter')
        ->buttonBackground->toBe('rgb(250, 93, 25)')
        ->buttonHeight->toBe('36px');
});

test('app pages render titles and breadcrumbs in the shared header', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $webhook = Webhook::factory()->create([
        'workspace_id' => $workspace->id,
        'endpoint' => 'https://hooks.example.com/social/published',
    ]);
    WebhookLog::factory()->create([
        'webhook_id' => $webhook->id,
    ]);

    $this->actingAs($user);

    $accountsPage = visit(route('app.accounts'))
        ->assertVisible('@header-title')
        ->assertVisible('@app-content-shell')
        ->assertNoJavaScriptErrors();

    $contentShell = $accountsPage->script(<<<'JS'
        (() => {
            const shell = document.querySelector('[data-testid="app-content-shell"]');
            const style = getComputedStyle(shell);
            const sidebar = document.querySelector('[data-slot="sidebar"][data-state]');

            return {
                borderRadius: style.borderRadius,
                borderWidth: style.borderWidth,
                marginTop: style.marginTop,
                overflow: style.overflow,
                sidebarState: sidebar?.getAttribute('data-state'),
            };
        })();
    JS);

    expect($contentShell)
        ->borderRadius->toBe('12.4px')
        ->borderWidth->toBe('1px')
        ->marginTop->toBe('8px')
        ->overflow->toBe('hidden')
        ->sidebarState->toBe('expanded');

    $webhookPage = visit(route('app.webhooks.show', $webhook))
        ->assertVisible('@breadcrumbs')
        ->assertVisible('@webhook-overview')
        ->assertVisible('@webhook-log-viewer')
        ->assertVisible('@webhook-log-list')
        ->assertVisible('@webhook-log-detail')
        ->assertSee('hooks.example.com/social/published')
        ->assertNoJavaScriptErrors();

    $detailLayout = $webhookPage->script(<<<'JS'
        (() => {
            const overview = getComputedStyle(document.querySelector('[data-testid="webhook-overview"]'));
            const viewer = getComputedStyle(document.querySelector('[data-testid="webhook-log-viewer"]'));

            return {
                overviewRadius: overview.borderRadius,
                viewerRadius: viewer.borderRadius,
                viewerShadow: viewer.boxShadow,
            };
        })();
    JS);

    expect($detailLayout)
        ->overviewRadius->toBe('0px')
        ->viewerRadius->toBe('0px')
        ->viewerShadow->toBe('none');
});

test('accounts and webhooks use full-height scrolling index layouts', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    Webhook::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($user);

    visit(route('app.accounts'))
        ->assertVisible('@accounts-scroll')
        ->assertNoJavaScriptErrors();

    $webhooksPage = visit(route('app.webhooks.index'))
        ->assertVisible('@webhooks-scroll')
        ->assertVisible('@header-title')
        ->assertNoJavaScriptErrors();

    $tableContainer = $webhooksPage->script(<<<'JS'
        (() => {
            const container = document.querySelector('[data-slot="table-container"]');
            const style = getComputedStyle(container);

            return {
                borderRadius: style.borderRadius,
                borderWidth: style.borderWidth,
                boxShadow: style.boxShadow,
            };
        })();
    JS);

    expect($tableContainer)
        ->borderRadius->toBe('0px')
        ->borderWidth->toBe('0px')
        ->boxShadow->toBe('none');
});
