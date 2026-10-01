<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Idea;
use App\Models\IdeaStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;

function waitForCreateIdeasBoardTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (document.querySelector('[data-testid="{$testId}"]')?.getBoundingClientRect().height > 0) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForCreateIdeasBoardCondition(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if ({$condition}) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForCreateIdeasBoardDatabase(mixed $page, Closure $condition): void
{
    for ($attempt = 0; $attempt < 50 && ! $condition(); $attempt++) {
        $page->script('new Promise((resolve) => setTimeout(resolve, 100))');
    }
}

/**
 * @return array{0: User, 1: Workspace, 2: array<string, IdeaStage>}
 */
function createIdeasBoardSetup(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $stages = $workspace->ideaStages()->get();

    return [$user->fresh(), $workspace, [
        'todo' => $stages[0],
        'in_progress' => $stages[1],
        'done' => $stages[2],
    ]];
}

function createIdeasBoardIdea(Workspace $workspace, User $user, ?IdeaStage $stage, int $position, string $title): Idea
{
    return Idea::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'idea_stage_id' => $stage?->id,
        'position' => $position,
        'title' => $title,
    ]);
}

function createIdeasBoardColumnIds(mixed $page): array
{
    return $page->script('[...document.querySelectorAll(\'[data-testid^="idea-column-"]\')].map((el) => el.dataset.testid).filter((id) => /^idea-column-(unassigned|[0-9a-f-]{36})$/.test(id))');
}

test('the board shows unassigned first and then the default stages with counts', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    createIdeasBoardIdea($workspace, $user, null, 0, 'First');
    createIdeasBoardIdea($workspace, $user, null, 1, 'Second');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, 'idea-column-unassigned');

    expect(createIdeasBoardColumnIds($page))->toBe([
        'idea-column-unassigned',
        "idea-column-{$stages['todo']->id}",
        "idea-column-{$stages['in_progress']->id}",
        "idea-column-{$stages['done']->id}",
    ]);
    $page->assertSeeIn('@idea-column-count-unassigned', '2')
        ->assertNoJavaScriptErrors();
});

test('dragging a card onto another in the same column reorders it', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $b = createIdeasBoardIdea($workspace, $user, $stages['todo'], 1, 'Idea B');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-{$b->id}");
    $page->drag("@idea-card-{$b->id}", "@idea-card-{$a->id}");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $b->refresh()->position < $a->refresh()->position);

    expect($b->refresh()->position)->toBeLessThan($a->refresh()->position);
    $page->assertNoJavaScriptErrors();
});

test('dragging a card onto an empty column moves it there', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-{$a->id}");
    $page->drag("@idea-card-{$a->id}", "@idea-column-{$stages['done']->id}");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $a->refresh()->idea_stage_id === $stages['done']->id);

    expect($a->refresh()->idea_stage_id)->toBe($stages['done']->id);
    $page->assertNoJavaScriptErrors();
});

test('move to stage from the card menu moves the idea', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-menu-{$a->id}");
    $page->click("@idea-card-menu-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-move-{$a->id}");
    $page->click("@idea-move-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-move-to-{$a->id}-unassigned");
    $page->click("@idea-move-to-{$a->id}-unassigned");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $a->refresh()->idea_stage_id === null);

    expect($a->refresh()->idea_stage_id)->toBeNull();
    $page->assertNoJavaScriptErrors();
});

test('a new stage is created on enter and nothing is created on escape', function () {
    [$user, $workspace] = createIdeasBoardSetup();
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, 'idea-stage-new');
    $page->click('@idea-stage-new');
    waitForCreateIdeasBoardTestId($page, 'idea-stage-new-input');
    $page->type('@idea-stage-new-input', 'Reviewed')->keys('@idea-stage-new-input', 'Enter');
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $workspace->ideaStages()->where('name', 'Reviewed')->exists());

    $stage = $workspace->ideaStages()->where('name', 'Reviewed')->firstOrFail();
    waitForCreateIdeasBoardTestId($page, "idea-column-{$stage->id}");
    $page->assertVisible("@idea-column-{$stage->id}");

    waitForCreateIdeasBoardTestId($page, 'idea-stage-new');
    $page->click('@idea-stage-new');
    waitForCreateIdeasBoardTestId($page, 'idea-stage-new-input');
    $page->type('@idea-stage-new-input', 'Discarded')->keys('@idea-stage-new-input', 'Escape');
    waitForCreateIdeasBoardTestId($page, 'idea-stage-new');

    expect($workspace->ideaStages()->count())->toBe(4);
    $page->assertMissing('@idea-stage-new-input')
        ->assertNoJavaScriptErrors();
});

test('deleting a stage keeps its ideas under unassigned', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-column-menu-{$stages['todo']->id}");
    $page->click("@idea-column-menu-{$stages['todo']->id}");
    waitForCreateIdeasBoardTestId($page, "idea-stage-delete-{$stages['todo']->id}");
    $page->click("@idea-stage-delete-{$stages['todo']->id}");
    waitForCreateIdeasBoardTestId($page, 'confirm-delete-input');
    $page->type('@confirm-delete-input', __('common.confirm_modal.delete_keyword'))
        ->click('@confirm-delete-action');
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"idea-column-{$stages['todo']->id}\"]')");

    $page->assertMissing("@idea-column-{$stages['todo']->id}");
    expect($page->script("!!document.querySelector('[data-testid=\"idea-column-unassigned\"] [data-testid=\"idea-card-{$a->id}\"]')"))->toBeTrue()
        ->and($a->refresh()->idea_stage_id)->toBeNull();
    $page->assertNoJavaScriptErrors();
});

test('dragging a stage handle onto another column reorders the stages', function () {
    [$user, , $stages] = createIdeasBoardSetup();
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-stage-handle-{$stages['done']->id}");
    $page->hover("@idea-column-{$stages['done']->id}");
    $page->drag("@idea-stage-handle-{$stages['done']->id}", "@idea-column-{$stages['todo']->id}");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $stages['done']->refresh()->position < $stages['in_progress']->refresh()->position);

    expect($stages['done']->refresh()->position)->toBeLessThan($stages['in_progress']->refresh()->position);
    $page->assertNoJavaScriptErrors();
});

test('selected ideas are deleted in bulk and the selection can be cleared', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $b = createIdeasBoardIdea($workspace, $user, $stages['todo'], 1, 'Idea B');
    $c = createIdeasBoardIdea($workspace, $user, $stages['todo'], 2, 'Idea C');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-menu-{$a->id}");
    $page->click("@idea-card-menu-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-select-{$a->id}");
    $page->click("@idea-select-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-card-checkbox-{$b->id}");
    $page->click("@idea-card-checkbox-{$b->id}");
    waitForCreateIdeasBoardTestId($page, 'ideas-bulk-delete');
    $page->assertSeeIn('@ideas-bulk-delete', '2')
        ->click('@ideas-bulk-delete');
    waitForCreateIdeasBoardTestId($page, 'confirm-delete-input');
    $page->type('@confirm-delete-input', __('common.confirm_modal.delete_keyword'))
        ->click('@ideas-bulk-delete-confirm');
    waitForCreateIdeasBoardDatabase($page, fn (): bool => Idea::whereKey([$a->id, $b->id])->doesntExist());

    expect(Idea::whereKey([$a->id, $b->id])->exists())->toBeFalse()
        ->and(Idea::whereKey($c->id)->exists())->toBeTrue();
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"ideas-bulk-bar\"]')");
    $page->assertMissing('@ideas-bulk-bar');

    $page->click("@idea-card-menu-{$c->id}");
    waitForCreateIdeasBoardTestId($page, "idea-select-{$c->id}");
    $page->click("@idea-select-{$c->id}");
    waitForCreateIdeasBoardTestId($page, 'ideas-clear-selection');
    $page->click('@ideas-clear-selection');
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"ideas-bulk-bar\"]')");

    $page->assertMissing('@ideas-bulk-bar')
        ->assertMissing("@idea-card-checkbox-{$c->id}")
        ->assertNoJavaScriptErrors();
});

test('the gallery view lists ideas and filters them by label and stage', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id]);
    $tagged = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Tagged');
    $tagged->labels()->attach($label->id);
    $inDone = createIdeasBoardIdea($workspace, $user, $stages['done'], 0, 'In done');
    $loose = createIdeasBoardIdea($workspace, $user, null, 0, 'Loose');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, 'ideas-view-gallery');
    $page->click('@ideas-view-gallery');
    waitForCreateIdeasBoardTestId($page, 'ideas-gallery');

    $page->assertVisible("@idea-card-{$tagged->id}")
        ->assertVisible("@idea-card-{$inDone->id}")
        ->assertVisible("@idea-card-{$loose->id}");
    expect($page->script('location.search'))->toContain('view=gallery');

    $page->click('@ideas-filter-labels-filter');
    waitForCreateIdeasBoardTestId($page, "ideas-filter-labels-option-{$label->id}");
    $page->click("@ideas-filter-labels-option-{$label->id}");
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"idea-card-{$loose->id}\"]')");

    $page->assertVisible("@idea-card-{$tagged->id}")
        ->assertMissing("@idea-card-{$loose->id}")
        ->assertMissing("@idea-card-{$inDone->id}");

    $page->click("@ideas-filter-labels-option-{$label->id}");
    waitForCreateIdeasBoardTestId($page, "idea-card-{$loose->id}");

    $page->click('@ideas-filter-labels-untagged-checkbox');
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"idea-card-{$tagged->id}\"]')");
    $page->assertVisible("@idea-card-{$loose->id}")
        ->assertMissing("@idea-card-{$tagged->id}");
    expect($page->script('location.search'))->toContain('untagged=1');

    $page->click('@ideas-filter-labels-clear');
    waitForCreateIdeasBoardTestId($page, "idea-card-{$tagged->id}");
    $page->assertVisible("@idea-card-{$loose->id}")
        ->assertPresent('@ideas-filter-labels-settings');
    $page->keys('@ideas-filter-labels-search', 'Escape');
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"ideas-filter-labels-search\"]')");
    $page->click('@ideas-filter-stages-filter');
    waitForCreateIdeasBoardTestId($page, "ideas-filter-stages-option-{$stages['done']->id}");
    $page->click("@ideas-filter-stages-option-{$stages['done']->id}");
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"idea-card-{$loose->id}\"]')");

    $page->assertVisible("@idea-card-{$inDone->id}")
        ->assertMissing("@idea-card-{$tagged->id}")
        ->assertMissing("@idea-card-{$loose->id}")
        ->assertNoJavaScriptErrors();
});

test('duplicating an idea adds a copy right after it', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $b = createIdeasBoardIdea($workspace, $user, $stages['todo'], 1, 'Idea B');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-menu-{$a->id}");
    $page->click("@idea-card-menu-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-duplicate-{$a->id}");
    $page->click("@idea-duplicate-{$a->id}");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => Idea::count() === 3);

    $copy = Idea::whereNotIn('id', [$a->id, $b->id])->firstOrFail();
    waitForCreateIdeasBoardTestId($page, "idea-card-{$copy->id}");

    $order = $page->script("[...document.querySelectorAll('[data-testid=\"idea-column-{$stages['todo']->id}\"] [data-idea-id]')].map((el) => el.dataset.ideaId)");
    expect($order)->toBe([$a->id, $copy->id, $b->id]);
    $page->assertNoJavaScriptErrors();
});

test('a drop made while a move is in flight is applied after the first move completes', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $b = createIdeasBoardIdea($workspace, $user, $stages['todo'], 1, 'Idea B');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-{$b->id}");
    $page->script(<<<'JS'
        (() => {
            const open = XMLHttpRequest.prototype.open;
            const send = XMLHttpRequest.prototype.send;
            window.__ideaMoves = [];
            XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                this.__ideaMove = method.toUpperCase() === 'PUT' && String(url).endsWith('/move');
                return open.call(this, method, url, ...rest);
            };
            XMLHttpRequest.prototype.send = function (body) {
                if (!this.__ideaMove) return send.call(this, body);
                window.__ideaMoves.push(body);
                if (window.__ideaMoves.length === 1) {
                    window.__releaseIdeaMove = () => send.call(this, body);
                    return;
                }
                return send.call(this, body);
            };
        })();
    JS);

    $page->drag("@idea-card-{$a->id}", "@idea-column-{$stages['done']->id}");
    waitForCreateIdeasBoardCondition($page, 'window.__ideaMoves.length === 1');
    $page->drag("@idea-card-{$b->id}", "@idea-column-{$stages['done']->id}");
    waitForCreateIdeasBoardCondition($page, "!!document.querySelector('[data-testid=\"idea-column-{$stages['done']->id}\"] [data-idea-id=\"{$b->id}\"]')");

    expect($page->script('window.__ideaMoves.length'))->toBe(1)
        ->and($a->refresh()->idea_stage_id)->toBe($stages['todo']->id)
        ->and($b->refresh()->idea_stage_id)->toBe($stages['todo']->id);

    $page->script('window.__releaseIdeaMove()');
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $b->refresh()->idea_stage_id === $stages['done']->id);

    $order = $page->script("[...document.querySelectorAll('[data-testid=\"idea-column-{$stages['done']->id}\"] [data-idea-id]')].map((el) => el.dataset.ideaId)");
    expect($page->script('window.__ideaMoves.length'))->toBe(2)
        ->and($a->refresh()->idea_stage_id)->toBe($stages['done']->id)
        ->and($b->refresh()->idea_stage_id)->toBe($stages['done']->id)
        ->and(Idea::where('idea_stage_id', $stages['done']->id)->orderBy('position')->pluck('id')->all())->toBe($order);
    $page->assertNoJavaScriptErrors();
});

test('deleting a stage removes it from the stages filter', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    createIdeasBoardIdea($workspace, $user, null, 0, 'Loose');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index', ['view' => 'gallery', 'stages' => [$stages['todo']->id, $stages['done']->id]]));
    waitForCreateIdeasBoardTestId($page, 'ideas-view-board');
    expect($page->script('decodeURIComponent(location.search)'))->toContain($stages['todo']->id);

    $page->click('@ideas-view-board');
    waitForCreateIdeasBoardTestId($page, "idea-column-menu-{$stages['todo']->id}");
    $page->click("@idea-column-menu-{$stages['todo']->id}");
    waitForCreateIdeasBoardTestId($page, "idea-stage-delete-{$stages['todo']->id}");
    $page->click("@idea-stage-delete-{$stages['todo']->id}");
    waitForCreateIdeasBoardTestId($page, 'confirm-delete-input');
    $page->type('@confirm-delete-input', __('common.confirm_modal.delete_keyword'))
        ->click('@confirm-delete-action');
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"idea-column-{$stages['todo']->id}\"]')");

    $page->click('@ideas-view-gallery');
    waitForCreateIdeasBoardCondition($page, "location.search.includes('view=gallery')");

    $search = $page->script('decodeURIComponent(location.search)');
    expect($search)->not->toContain($stages['todo']->id)
        ->and($search)->toContain($stages['done']->id);
    $page->assertNoJavaScriptErrors();
});

test('move to stage works from the gallery card menu', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index', ['view' => 'gallery']));
    waitForCreateIdeasBoardTestId($page, "idea-card-menu-{$a->id}");
    $page->click("@idea-card-menu-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-move-{$a->id}");
    $page->click("@idea-move-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-move-to-{$a->id}-{$stages['done']->id}");
    $page->click("@idea-move-to-{$a->id}-{$stages['done']->id}");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $a->refresh()->idea_stage_id === $stages['done']->id);

    expect($a->refresh()->idea_stage_id)->toBe($stages['done']->id);
    $page->assertVisible("@idea-card-{$a->id}")
        ->assertNoJavaScriptErrors();
});

test('move to stage works on a filtered board and appends to the end of the stage', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id]);
    $existing = createIdeasBoardIdea($workspace, $user, $stages['done'], 0, 'Already done');
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Tagged A');
    $a->labels()->attach($label->id);
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index', ['labels' => [$label->id]]));
    waitForCreateIdeasBoardTestId($page, 'ideas-drag-disabled-hint');
    waitForCreateIdeasBoardTestId($page, "idea-card-menu-{$a->id}");
    $page->click("@idea-card-menu-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-move-{$a->id}");
    $page->click("@idea-move-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-move-to-{$a->id}-{$stages['done']->id}");
    $page->click("@idea-move-to-{$a->id}-{$stages['done']->id}");
    waitForCreateIdeasBoardDatabase($page, fn (): bool => $a->refresh()->idea_stage_id === $stages['done']->id);

    expect($a->refresh()->idea_stage_id)->toBe($stages['done']->id)
        ->and($a->position)->toBeGreaterThan($existing->refresh()->position);
    waitForCreateIdeasBoardCondition($page, "!!document.querySelector('[data-testid=\"idea-column-{$stages['done']->id}\"] [data-testid=\"idea-card-{$a->id}\"]')");
    expect($page->script("!!document.querySelector('[data-testid=\"idea-column-{$stages['done']->id}\"] [data-testid=\"idea-card-{$a->id}\"]')"))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});

test('changing a filter clears the selection', function () {
    [$user, $workspace, $stages] = createIdeasBoardSetup();
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id]);
    $a = createIdeasBoardIdea($workspace, $user, $stages['todo'], 0, 'Idea A');
    $this->actingAs($user);

    $page = visit(route('app.create.ideas.index'));
    waitForCreateIdeasBoardTestId($page, "idea-card-menu-{$a->id}");
    $page->click("@idea-card-menu-{$a->id}");
    waitForCreateIdeasBoardTestId($page, "idea-select-{$a->id}");
    $page->click("@idea-select-{$a->id}");
    waitForCreateIdeasBoardTestId($page, 'ideas-bulk-bar');
    waitForCreateIdeasBoardCondition($page, "document.body.style.pointerEvents !== 'none' && !document.querySelector('[role=\"menu\"]')");

    $page->click('@ideas-filter-labels-filter');
    waitForCreateIdeasBoardTestId($page, 'ideas-filter-labels-untagged-checkbox');
    $page->click('@ideas-filter-labels-untagged-checkbox');
    waitForCreateIdeasBoardCondition($page, "!document.querySelector('[data-testid=\"ideas-bulk-bar\"]')");

    $page->assertMissing('@ideas-bulk-bar')
        ->assertNoJavaScriptErrors();
});
