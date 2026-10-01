<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

function waitForSettingsSidebarTestId(mixed $page, string $testId): void
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

function settingsSidebarUser(Role $role): User
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);
    $workspace->members()->attach($owner->id, ['role' => Role::Admin->value]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    if ($role === Role::Admin) {
        return $owner->fresh();
    }

    $member = User::factory()->create(['account_id' => $owner->account_id]);
    $workspace->members()->attach($member->id, ['role' => $role->value]);
    $member->update(['current_workspace_id' => $workspace->id]);

    return $member->fresh();
}

test('settings pages swap the app sidebar for the settings sidebar', function () {
    $this->actingAs(settingsSidebarUser(Role::Admin));

    $page = visit(route('app.profile.edit'));
    waitForSettingsSidebarTestId($page, 'settings-sidebar');

    $page->assertVisible('@settings-sidebar')
        ->assertVisible('@settings-nav-profile')
        ->assertVisible('@settings-nav-preferences')
        ->assertVisible('@settings-nav-channels')
        ->assertPresent('[data-testid="settings-nav-channels"] svg.tabler-icon-layout-grid')
        ->assertVisible('@settings-nav-signatures')
        ->assertVisible('@settings-nav-webhooks')
        ->assertMissing('@sidebar-new')
        ->assertMissing('@settings-tab-profile')
        ->assertNoJavaScriptErrors();
});

test('members only see the settings they may use', function () {
    $this->actingAs(settingsSidebarUser(Role::Member));

    $page = visit(route('app.profile.edit'));
    waitForSettingsSidebarTestId($page, 'settings-sidebar');

    $page->assertVisible('@settings-nav-profile')
        ->assertVisible('@settings-nav-signatures')
        ->assertVisible('@settings-nav-mcp')
        ->assertMissing('@settings-nav-channels')
        ->assertMissing('@settings-nav-general')
        ->assertMissing('@settings-nav-webhooks')
        ->assertNoJavaScriptErrors();
});

test('a user without a workspace sees only personal settings', function () {
    $this->actingAs(User::factory()->create(['current_workspace_id' => null]));

    $page = visit(route('app.profile.edit'));
    waitForSettingsSidebarTestId($page, 'settings-sidebar');

    $page->assertVisible('@settings-nav-profile')
        ->assertMissing('@settings-nav-general')
        ->assertMissing('@settings-nav-mcp')
        ->assertNoJavaScriptErrors();
});

test('the back link returns to the app', function () {
    $this->actingAs(settingsSidebarUser(Role::Admin));

    $page = visit(route('app.labels.index'));
    waitForSettingsSidebarTestId($page, 'settings-back');
    $page->click('@settings-back');
    waitForSettingsSidebarTestId($page, 'sidebar-new');

    $page->assertVisible('@sidebar-new')->assertNoJavaScriptErrors();
});
