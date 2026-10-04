<?php

declare(strict_types=1);

use App\Enums\User\ReferralSource;
use App\Models\User;
use App\Models\Workspace;

function waitForReferralSurveyCondition(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 200; attempt++) {
                if ({$condition}) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

test('a user who has not said where they found us answers from the in-app card', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['referral_source' => null]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $this->actingAs($user->fresh());

    $page = visit(route('app.calendar'))->resize(1280, 900);
    waitForReferralSurveyCondition($page, "document.querySelector('[data-testid=\"referral-source-survey\"]')");

    expect($page->script("document.querySelector('[data-testid=\"referral-source-submit\"]').disabled"))->toBeTrue();

    $page->click('@referral-source-option-'.ReferralSource::ProductHunt->value)
        ->click('@referral-source-submit');
    waitForReferralSurveyCondition($page, "!document.querySelector('[data-testid=\"referral-source-survey\"]')");

    $page->assertMissing('@referral-source-survey')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->referral_source)->toBe(ReferralSource::ProductHunt);
});

test('a user who already answered never sees the card', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['referral_source' => ReferralSource::Google]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $this->actingAs($user->fresh());

    $page = visit(route('app.calendar'))->resize(1280, 900);
    waitForReferralSurveyCondition($page, "document.querySelector('[data-testid=\"app-content-shell\"]')");

    $page->assertMissing('@referral-source-survey')
        ->assertNoJavaScriptErrors();
});
