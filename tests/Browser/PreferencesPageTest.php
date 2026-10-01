<?php

declare(strict_types=1);

use App\Enums\User\DefaultPostAction;
use App\Enums\User\Locale;
use App\Enums\User\Theme;
use App\Enums\User\TimeFormat;
use App\Enums\User\WeekStart;
use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

function waitForPreferencesTestId(mixed $page, string $testId): void
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

function preferencesUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

function choosePreference(mixed $page, string $testid, string $value, string $savedField): void
{
    waitForPreferencesTestId($page, "{$testid}-trigger");
    $page->click("@{$testid}-trigger");
    waitForPreferencesTestId($page, "{$testid}-option-{$value}");
    $page->click("@{$testid}-option-{$value}");
    waitForPreferencesTestId($page, "preferences-saved-{$savedField}");
}

test('the preferences page lists every preference and is linked from the settings sidebar', function () {
    $this->actingAs(preferencesUser());

    $page = visit(route('app.profile.edit'));
    waitForPreferencesTestId($page, 'settings-nav-preferences');
    $page->click('@settings-nav-preferences');
    waitForPreferencesTestId($page, 'preferences-page');

    $page->assertVisible('@preferences-theme-trigger')
        ->assertVisible('@preferences-language-trigger')
        ->assertVisible('@preferences-timezone-trigger')
        ->assertVisible('@preferences-time-format-trigger')
        ->assertVisible('@preferences-week-start-trigger')
        ->assertVisible('@preferences-default-post-action-trigger')
        ->assertNoJavaScriptErrors();
});

test('choosing dark applies it at once and the server renders it on the next load', function () {
    $user = preferencesUser();
    $this->actingAs($user);

    $page = visit(route('app.settings.preferences'))->inLightMode();
    choosePreference($page, 'preferences-theme', 'dark', 'theme');

    $page->assertScript("document.documentElement.classList.contains('dark')", true);
    expect($user->fresh()->theme)->toBe(Theme::Dark);

    $page = visit(route('app.settings.preferences'))->inLightMode();
    waitForPreferencesTestId($page, 'preferences-page');
    $page->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

test('the system theme follows the operating system', function () {
    $this->actingAs(preferencesUser(['theme' => Theme::System]));

    $dark = visit(route('app.settings.preferences'))->inDarkMode();
    waitForPreferencesTestId($dark, 'preferences-page');
    $dark->assertScript("document.documentElement.classList.contains('dark')", true);

    $light = visit(route('app.settings.preferences'))->inLightMode();
    waitForPreferencesTestId($light, 'preferences-page');
    $light->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertNoJavaScriptErrors();
});

test('the language changes in place and stays the user locale', function () {
    $user = preferencesUser(['locale' => Locale::English]);
    $this->actingAs($user);

    $page = visit(route('app.settings.preferences'));
    choosePreference($page, 'preferences-language', 'pt-BR', 'locale');

    $title = __('settings.preferences.title', [], 'pt-BR');
    $page->script("(async () => { for (let i = 0; i < 150; i++) { if (document.body.innerText.includes('{$title}')) return; await new Promise((r) => setTimeout(r, 50)); } })();");

    $page->assertSeeIn('@header-title', $title)->assertNoJavaScriptErrors();
    expect($user->fresh()->locale)->toBe(Locale::PortugueseBrazil);
});

test('the language picker filters languages as you type', function () {
    $this->actingAs(preferencesUser(['locale' => Locale::English]));

    $page = visit(route('app.settings.preferences'));
    waitForPreferencesTestId($page, 'preferences-language-trigger');
    $page->click('@preferences-language-trigger');
    waitForPreferencesTestId($page, 'preferences-language-search');
    $page->type('@preferences-language-search', 'Portug');
    waitForPreferencesTestId($page, 'preferences-language-option-pt-BR');

    $page->assertVisible('@preferences-language-option-pt-BR')
        ->assertMissing('@preferences-language-option-de')
        ->assertNoJavaScriptErrors();
});

test('the time zone picker suggests the browser time zone first', function () {
    $this->actingAs(preferencesUser());

    $page = visit(route('app.settings.preferences'));
    waitForPreferencesTestId($page, 'preferences-timezone-trigger');
    $page->click('@preferences-timezone-trigger');
    waitForPreferencesTestId($page, 'preferences-timezone-detected');

    $page->assertVisible('@preferences-timezone-detected')->assertNoJavaScriptErrors();
});

test('a 12-hour clock shows meridiem hours on the calendar', function () {
    $user = preferencesUser(['locale' => Locale::German]);
    $this->actingAs($user);

    $page = visit(route('app.settings.preferences'));
    choosePreference($page, 'preferences-time-format', '12h', 'time_format');
    expect($user->fresh()->time_format)->toBe(TimeFormat::TwelveHour);

    $page = visit(route('app.calendar', ['view' => 'week']));
    waitForPreferencesTestId($page, 'calendar-slot-'.now()->startOfWeek()->format('Y-m-d').'-14');

    $page->assertScript("document.body.innerText.includes('2 PM')", true)
        ->assertNoJavaScriptErrors();
});

test('the week start and default posting action save', function () {
    $user = preferencesUser();
    $this->actingAs($user);

    $page = visit(route('app.settings.preferences'));
    choosePreference($page, 'preferences-week-start', 'sunday', 'week_starts_on');
    choosePreference($page, 'preferences-default-post-action', 'custom', 'default_post_action');

    $user->refresh();
    expect($user->week_starts_on)->toBe(WeekStart::Sunday)
        ->and($user->default_post_action)->toBe(DefaultPostAction::Custom);

    $page = visit(route('app.calendar', ['view' => 'month']));
    waitForPreferencesTestId($page, 'calendar-month-grid');
    $page->assertNoJavaScriptErrors();
});
