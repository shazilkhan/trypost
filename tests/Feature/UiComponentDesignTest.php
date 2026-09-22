<?php

declare(strict_types=1);

function componentSource(string $path): string
{
    return resourceSource("components/{$path}");
}

function resourceSource(string $path): string
{
    $source = file_get_contents(resource_path("js/{$path}"));

    expect($source)->not->toBeFalse();

    return $source;
}

test('app surfaces use clean icon panels instead of tilted sticker treatments', function (string $path) {
    expect(resourceSource($path))
        ->not->toMatch('/(?:^|\s)-?rotate-[123](?:\s|[\'\"])/')
        ->not->toContain('border-2 border-dashed');
})->with([
    'accounts' => 'components/accounts/ConnectedAccountsByNetwork.vue',
    'analytics metrics' => 'components/analytics/MetricsGrid.vue',
    'asset gallery' => 'components/assets/GalleryBrowser.vue',
    'notifications' => 'components/NotificationBell.vue',
    'post create' => 'pages/posts/Create.vue',
    'post show' => 'pages/posts/Show.vue',
    'security settings' => 'pages/settings/profile/Authentication.vue',
    'settings index' => 'pages/settings/Index.vue',
    'workspace mcp' => 'pages/settings/workspace/Mcp.vue',
]);

test('form controls share the brand focus treatment', function (string $path) {
    expect(componentSource($path))
        ->toContain('border-ring')
        ->toContain('ring-ring/50')
        ->toContain('aria-invalid');
})->with([
    'input' => 'ui/input/Input.vue',
    'textarea' => 'ui/textarea/Textarea.vue',
    'native select' => 'ui/native-select/NativeSelect.vue',
    'select trigger' => 'ui/select/SelectTrigger.vue',
    'tags input' => 'ui/tags-input/TagsInput.vue',
]);

test('tables render as flat page grids instead of rounded cards', function () {
    expect(componentSource('ui/table/Table.vue'))
        ->toContain('class="relative w-full"')
        ->not->toContain('rounded-lg')
        ->not->toContain('shadow-xs')
        ->not->toContain('border border-border');
});

test('webhook detail uses flat full-height panels', function (string $path, string $testId) {
    expect(componentSource($path))
        ->toContain("data-testid=\"{$testId}\"")
        ->not->toContain('rounded-xl border border-border bg-card')
        ->not->toContain('shadow-2xs');
})->with([
    'overview' => ['webhook/WebhookOverview.vue', 'webhook-overview'],
    'log viewer' => ['webhook/WebhookLogViewer.vue', 'webhook-log-viewer'],
    'log list' => ['webhook/WebhookLogList.vue', 'webhook-log-list'],
    'log detail' => ['webhook/WebhookLogDetail.vue', 'webhook-log-detail'],
]);

test('post listings use the full-height scrolling table layout', function () {
    expect(resourceSource('pages/posts/Index.vue'))
        ->toContain('<AppLayout full-width>')
        ->toContain('<template #header-actions>')
        ->toContain('data-testid="posts-search"')
        ->toContain('data-testid="new-post-link"')
        ->toContain('data-testid="posts-scroll"')
        ->toContain('<TableHeader sticky>')
        ->toContain('items-element="#posts-body"')
        ->toContain("reset: ['posts']")
        ->not->toContain('gap-6 px-6 py-8');
});

test('selection components use the yellow interaction treatment', function (string $path) {
    expect(componentSource($path))
        ->toContain('amber-')
        ->not->toContain('violet-');
})->with([
    'combobox item' => 'ui/combobox/ComboboxItem.vue',
    'command item' => 'ui/command/CommandItem.vue',
    'select item' => 'ui/select/SelectItem.vue',
    'tabs trigger' => 'ui/tabs/TabsTrigger.vue',
    'calendar day' => 'ui/calendar/CalendarCellTrigger.vue',
    'range calendar day' => 'ui/range-calendar/RangeCalendarCellTrigger.vue',
    'range calendar track' => 'ui/range-calendar/RangeCalendarCell.vue',
    'date range presets' => 'ui/date-range-picker/DateRangePicker.vue',
]);

test('platform option controls no longer use the legacy heavy violet selection', function (string $path) {
    expect(componentSource($path))
        ->toContain('border-amber-300')
        ->toContain('bg-amber-100')
        ->not->toContain('border-foreground bg-violet-100');
})->with([
    'facebook settings' => 'posts/editor/FacebookSettings.vue',
    'instagram settings' => 'posts/editor/InstagramSettings.vue',
    'google business settings' => 'posts/editor/GoogleBusinessSettings.vue',
    'pinterest settings' => 'posts/editor/PinterestSettings.vue',
    'tiktok settings' => 'posts/editor/TikTokSettings.vue',
]);
