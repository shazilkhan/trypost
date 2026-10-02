<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;

function waitForLabelMenuTestId(mixed $page, string $testId): void
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

function labelMenuOwner(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);

    return [$user, $label];
}

function assertLabelFilterSelected(mixed $page, string $testId, string $labelId): void
{
    waitForLabelMenuTestId($page, "{$testId}-filter");
    $page->click("@{$testId}-filter");
    waitForLabelMenuTestId($page, "{$testId}-checkbox-{$labelId}");
    $page->assertAttribute("@{$testId}-checkbox-{$labelId}", 'data-state', 'checked')
        ->assertNoJavaScriptErrors();
}

test('label row menu opens posts and reporting filtered by the label in a new tab', function () {
    [$user, $label] = labelMenuOwner();
    $this->actingAs($user);

    $page = visit(route('app.labels.index'));
    waitForLabelMenuTestId($page, "label-menu-{$label->id}");
    $page->click("@label-menu-{$label->id}");
    waitForLabelMenuTestId($page, "label-view-posts-{$label->id}");

    $links = $page->script(<<<JS
        (() => ['label-view-posts-{$label->id}', 'label-open-reporting-{$label->id}'].map((id) => {
            const link = document.querySelector('[data-testid="' + id + '"]');
            return { href: decodeURIComponent(link.href), target: link.target, rel: link.rel };
        }))();
    JS);

    expect($links[0]['target'])->toBe('_blank')
        ->and($links[0]['rel'])->toContain('noopener')
        ->and($links[0]['href'])->toStartWith(route('app.posts.index'))
        ->and($links[0]['href'])->toContain("labels[]={$label->id}")
        ->and($links[1]['target'])->toBe('_blank')
        ->and($links[1]['rel'])->toContain('noopener')
        ->and($links[1]['href'])->toStartWith(route('app.insights'))
        ->and($links[1]['href'])->toContain("labels[]={$label->id}");

    $page->assertNoJavaScriptErrors();
});

test('filtered posts and analytics pages land with the label selected', function () {
    [$user, $label] = labelMenuOwner();
    $this->actingAs($user);

    assertLabelFilterSelected(visit(route('app.posts.index', ['labels' => [$label->id]])), 'posts-label', $label->id);
    assertLabelFilterSelected(visit(route('app.insights', ['labels' => [$label->id]])), 'analytics-label', $label->id);
});
