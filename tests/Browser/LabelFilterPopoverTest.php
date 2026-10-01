<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;

function waitForLabelPopoverTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const selector = '[data-testid="{$testId}"]';
            for (let attempt = 0; attempt < 200; attempt++) {
                const element = document.querySelector(selector);
                if (element && element.getBoundingClientRect().height > 0) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForLabelPopoverUrl(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                const params = new URLSearchParams(location.search);
                if ({$condition}) return;
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        })();
    JS);
}

test('the label filter offers untagged, clears the selection and links to the labels page', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $user->account_id, 'user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));
    waitForLabelPopoverTestId($page, 'posts-label-filter');
    $page->click('@posts-label-filter');
    waitForLabelPopoverTestId($page, 'posts-label-untagged');

    $page->assertScript('document.querySelector("[data-testid=posts-label-settings]").getAttribute("href")', route('app.labels.index', absolute: false))
        ->assertPresent('@posts-label-clear')
        ->click('@posts-label-untagged-checkbox');
    waitForLabelPopoverUrl($page, "params.get('untagged') === '1'");

    $page->assertScript('new URLSearchParams(location.search).get("untagged")', '1')
        ->click("@posts-label-checkbox-{$label->id}");
    waitForLabelPopoverUrl($page, "[...params.keys()].some((key) => key.startsWith('labels'))");

    $page->assertScript('document.querySelector("[data-testid=posts-label-count]")?.innerText', '2')
        ->click('@posts-label-clear');
    waitForLabelPopoverUrl($page, "!params.has('untagged') && ![...params.keys()].some((key) => key.startsWith('labels'))");

    $page->assertScript('new URLSearchParams(location.search).has("untagged")', false)
        ->assertScript('[...new URLSearchParams(location.search).keys()].some((key) => key.startsWith("labels"))', false)
        ->assertMissing('@posts-label-count')
        ->assertNoJavaScriptErrors();
});
