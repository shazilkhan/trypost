<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;

function waitForWebhookCreateTestId(mixed $page, string $testId): void
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

function waitForWebhookCreateTestIdGone(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 150; i++) {
                if (! document.querySelector(sel)) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

function webhookCreateDialogAdmin(): User
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

test('creating a webhook happens in a centered dialog with cancel before the primary action', function () {
    $this->actingAs(webhookCreateDialogAdmin());

    $page = visit(route('app.webhooks.index'));
    waitForWebhookCreateTestId($page, 'create-webhook-button');
    $page->click('@create-webhook-button');
    waitForWebhookCreateTestId($page, 'create-webhook-dialog');

    $page->assertVisible('@create-webhook-endpoint')
        ->assertVisible('@create-webhook-events-post-created')
        ->assertScript("document.querySelector('[data-testid=\"create-webhook-dialog\"]').getAttribute('role') === 'dialog'", true)
        ->assertScript("(() => { const dialog = document.querySelector('[data-testid=\"create-webhook-dialog\"]').getBoundingClientRect(); return Math.abs((dialog.left + dialog.right) / 2 - window.innerWidth / 2) < 2; })()", true)
        ->assertScript("(() => { const cancel = document.querySelector('[data-testid=\"cancel-create-webhook\"]'); const submit = document.querySelector('[data-testid=\"create-webhook-submit\"]'); return Boolean(cancel.compareDocumentPosition(submit) & Node.DOCUMENT_POSITION_FOLLOWING) && cancel.getBoundingClientRect().left < submit.getBoundingClientRect().left; })()", true)
        ->assertScript("document.querySelector('[data-testid=\"create-webhook-dialog\"] [required]') === null", true)
        ->assertDisabled('@create-webhook-submit')
        ->click('@create-webhook-events-post-created')
        ->assertEnabled('@create-webhook-submit')
        ->click('@create-webhook-events-post-created')
        ->assertDisabled('@create-webhook-submit')
        ->assertNoJavaScriptErrors();
});

test('an invalid endpoint shows an inline error and a valid one closes the dialog and lists the webhook', function () {
    $user = webhookCreateDialogAdmin();
    $this->actingAs($user);

    $page = visit(route('app.webhooks.index'));
    waitForWebhookCreateTestId($page, 'create-webhook-button');
    $page->click('@create-webhook-button');
    waitForWebhookCreateTestId($page, 'create-webhook-endpoint');

    $page->fill('@create-webhook-endpoint', 'not-a-url')
        ->click('@create-webhook-events-post-published')
        ->click('@create-webhook-submit');

    $page->assertSee(__('validation.url', ['attribute' => __('webhooks.create.endpoint')]))
        ->assertVisible('@create-webhook-dialog');

    expect(Webhook::query()->count())->toBe(0);

    $page->fill('@create-webhook-endpoint', 'https://example.com/hooks')
        ->click('@create-webhook-submit');
    waitForWebhookCreateTestIdGone($page, 'create-webhook-dialog');

    $webhook = Webhook::query()->where('workspace_id', $user->current_workspace_id)->sole();
    waitForWebhookCreateTestId($page, "webhook-row-{$webhook->id}");

    $page->assertMissing('@create-webhook-dialog')
        ->assertUrlIs(route('app.webhooks.index'))
        ->assertVisible("@webhook-row-{$webhook->id}")
        ->assertSeeIn("@webhook-row-{$webhook->id}", 'https://example.com/hooks')
        ->assertScript("document.querySelector('[data-sonner-toast]') === null", true)
        ->assertNoJavaScriptErrors();
});

test('cancel closes the dialog without creating a webhook', function () {
    $this->actingAs(webhookCreateDialogAdmin());

    $page = visit(route('app.webhooks.index'));
    waitForWebhookCreateTestId($page, 'create-webhook-button');
    $page->click('@create-webhook-button');
    waitForWebhookCreateTestId($page, 'create-webhook-endpoint');

    $page->fill('@create-webhook-endpoint', 'https://example.com/hooks')
        ->click('@create-webhook-events-post-published')
        ->click('@cancel-create-webhook');
    waitForWebhookCreateTestIdGone($page, 'create-webhook-dialog');

    $page->assertMissing('@create-webhook-dialog')
        ->assertNoJavaScriptErrors();

    expect(Webhook::query()->count())->toBe(0);
});
