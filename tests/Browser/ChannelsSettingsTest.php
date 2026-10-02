<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

function waitForChannelsSettingsTestId(mixed $page, string $testId): void
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

function channelsSettingsAdmin(): User
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

test('channels are listed one per row with settings and actions', function () {
    $user = channelsSettingsAdmin();
    $channel = SocialAccount::factory()->linkedin()->create(['workspace_id' => $user->current_workspace_id]);
    $this->actingAs($user);

    $page = visit(route('app.workspace.channels'));
    waitForChannelsSettingsTestId($page, "channel-row-{$channel->id}");

    $page->assertVisible("@channel-settings-{$channel->id}")
        ->click("@channel-menu-{$channel->id}");
    waitForChannelsSettingsTestId($page, "channel-disconnect-{$channel->id}");

    $page->assertMissing("@channel-toggle-{$channel->id}")
        ->assertVisible("@channel-disconnect-{$channel->id}")
        ->assertNoJavaScriptErrors();
});

test('disconnecting a channel asks for the translated disconnect keyword, not the handle', function () {
    $user = channelsSettingsAdmin();
    $channel = SocialAccount::factory()->linkedin()->create(['workspace_id' => $user->current_workspace_id]);
    $this->actingAs($user);

    $keyword = __('channels.disconnect_modal.keyword');

    $page = visit(route('app.workspace.channels'));
    waitForChannelsSettingsTestId($page, "channel-row-{$channel->id}");

    $page->click("@channel-menu-{$channel->id}");
    waitForChannelsSettingsTestId($page, "channel-disconnect-{$channel->id}");

    $page->click("@channel-disconnect-{$channel->id}")
        ->assertVisible('@confirm-delete-modal')
        ->assertSeeIn('@confirm-delete-description', __('channels.disconnect_modal.description'))
        ->fill('@confirm-delete-input', $channel->username)
        ->assertAttribute('@confirm-delete-action', 'disabled', '')
        ->fill('@confirm-delete-input', mb_strtolower($keyword))
        ->assertAttribute('@confirm-delete-action', 'disabled', '')
        ->fill('@confirm-delete-input', $keyword)
        ->click('@confirm-delete-action')
        ->assertMissing('@confirm-delete-modal')
        ->assertNoJavaScriptErrors();

    expect(SocialAccount::find($channel->id))->toBeNull();
});

test('a lost connection offers reconnect on the row', function () {
    $user = channelsSettingsAdmin();
    $channel = SocialAccount::factory()->linkedin()->tokenExpired()->create(['workspace_id' => $user->current_workspace_id]);
    $this->actingAs($user);

    $page = visit(route('app.workspace.channels'));
    waitForChannelsSettingsTestId($page, "channel-reconnect-{$channel->id}");

    $page->assertVisible("@channel-reconnect-{$channel->id}")->assertNoJavaScriptErrors();
});

test('a channel without a display name shows its username', function () {
    $user = channelsSettingsAdmin();
    $channel = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $user->current_workspace_id,
        'display_name' => null,
        'avatar_url' => null,
        'username' => 'fallback-handle',
    ]);
    $this->actingAs($user);

    $page = visit(route('app.workspace.channels'));
    waitForChannelsSettingsTestId($page, "channel-name-{$channel->id}");

    expect($page->script("document.querySelector('[data-testid=\"channel-name-{$channel->id}\"]').textContent.trim()"))
        ->toBe('fallback-handle');

    $page = visit(route('app.posts.index'));
    waitForChannelsSettingsTestId($page, "sidebar-channel-{$channel->id}");
    expect($page->script("document.querySelector('[data-testid=\"sidebar-channel-{$channel->id}\"]').textContent"))
        ->toContain('fallback-handle');
    $page->assertNoJavaScriptErrors();
});

test('an empty workspace shows the empty state with a connect button', function () {
    $this->actingAs(channelsSettingsAdmin());

    $page = visit(route('app.workspace.channels'));
    waitForChannelsSettingsTestId($page, 'channels-empty');

    $page->assertVisible('@channels-empty')
        ->assertVisible('@channels-connect')
        ->assertNoJavaScriptErrors();
});
