<?php

declare(strict_types=1);

test('the shared tokens match the design system', function () {
    $styles = file_get_contents(resource_path('css/app.css'));
    $buttons = file_get_contents(resource_path('js/components/ui/button/index.ts'));

    expect($styles)
        ->toContain('--primary: #b0ec9c;')
        ->toContain('--primary-foreground: #292928;')
        ->toContain('--primary-hover: #90d788;')
        ->toContain('--primary-strong: #4e975b;')
        ->toContain('--primary-subtle: #d9f1d1;')
        ->toContain('--primary-text: #337046;')
        ->toContain('--ring: #4e975b;')
        ->toContain('--sidebar-primary: #b0ec9c;')
        ->toContain('--success-subtle: #d9f1d1;')
        ->toContain('--success-text: #337046;')
        ->toContain('--color-primary-strong: var(--primary-strong);')
        ->toContain('--foreground: #292928;')
        ->toContain('--border: #eae8e5;')
        ->toContain('--border-strong: #dedcd9;')
        ->toContain('--sidebar: #f7f6f3;')
        ->toContain('--success: #4e975b;')
        ->toContain('--color-success: var(--success);')
        ->toContain('--destructive-text: #7f0f00;')
        ->toContain('--color-destructive-text: var(--destructive-text);')
        ->toContain('--critical: #ffb2a8;')
        ->toContain('--critical-hover: #ff8575;')
        ->toContain('--color-critical: var(--critical);')
        ->toContain("'Inter', ui-sans-serif")
        ->toContain("'Outfit', ui-sans-serif")
        ->toContain('--font-heading: var(--font-heading);')
        ->toContain('--radius: 0.5rem;')
        ->toContain('--text-sm: 14px;');

    expect($styles)->not->toContain('#fa5d19');

    expect(file_get_contents(resource_path('views/app.blade.php')))
        ->toContain('family=Inter:wght@400..700&family=Outfit:wght@400;500;600;700');

    expect(file_get_contents(resource_path('js/components/ui/badge/index.ts')))
        ->toContain('bg-success-subtle text-success-text');

    expect($buttons)
        ->toContain('bg-primary text-primary-foreground hover:bg-primary-hover')
        ->toContain('bg-critical text-foreground hover:bg-critical-hover')
        ->toContain('"default": "h-8 px-3"')
        ->toContain('"lg": "h-10 gap-2 px-4"');
});

test('post pages use the full-width design while retaining the composer', function () {
    $posts = file_get_contents(resource_path('js/pages/publish/Index.vue'));
    $tabs = file_get_contents(resource_path('js/components/publish/PublishTabs.vue'));
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));

    expect($posts)
        ->toContain('<AppLayout full-width>')
        ->toContain('data-testid="posts-scroll"')
        ->toContain('<PublishTabs')
        ->toContain('<PostComposerDialog');

    expect($tabs)->toContain('data-testid="posts-tabs"');

    expect($layout)
        ->toContain(':default-open="isOpen"')
        ->toContain('<GlobalPostComposer />');
});
