<?php

declare(strict_types=1);

use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Analytics\BootstrapAccountAnalytics;
use App\Jobs\Analytics\CollectAccountDailySnapshot;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Pest\Browser\Execution;

beforeEach(function () {
    Queue::fake([BootstrapAccountAnalytics::class, CollectAccountDailySnapshot::class]);
});

/**
 * @param  array<int, array<string, mixed>>  $media
 * @return array{0: Post, 1: SocialAccount}
 */
function seedLinkCardPreviewPost(
    Platform $platform,
    string $content = 'Draft without a link',
    array $media = [],
): array {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => $platform,
        'scopes' => $platform->requiredPublishScopes(),
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => $content,
        'media' => $media,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => $platform,
        'enabled' => true,
        'content_type' => match ($platform) {
            Platform::Facebook => ContentType::FacebookPost,
            Platform::LinkedIn => ContentType::LinkedInPost,
            Platform::Mastodon => ContentType::MastodonPost,
            default => throw new LogicException('Unsupported link-card browser test platform.'),
        },
    ]);

    test()->actingAs($user);

    return [$post, $account];
}

function waitForLinkCardPreviewTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                const dialog = document.querySelector('[data-testid="post-composer-dialog"]');
                if (dialog?.getAttribute('data-state') === 'open'
                    && dialog.getAnimations().every((animation) => animation.playState !== 'running')
                    && document.querySelector('[data-testid="{$testId}"]')?.getBoundingClientRect().height > 0) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForLinkCardPreviewText(mixed $page, string $text): void
{
    $encoded = json_encode($text);

    $page->script(<<<JS
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (document.querySelector('[data-testid="link-card-title"]')?.textContent.includes({$encoded})) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForLinkCardPreviewGone(mixed $page): void
{
    $page->script(<<<'JS'
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (!document.querySelector('[data-testid="link-card"]')) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

test('facebook linkedin and mastodon previews render fetched link cards', function (Platform $platform) {
    $url = "https://93.184.216.34/{$platform->value}";
    Http::fake([$url => Http::response('<meta property="og:title" content="Article card">')]);

    [$post] = seedLinkCardPreviewPost($platform, "Read {$url}");

    $page = visit(route('app.posts.edit', $post));
    waitForLinkCardPreviewTestId($page, 'composer-preview-frame');
    waitForLinkCardPreviewText($page, 'Article card');

    $page->assertSeeIn('@link-card-title', 'Article card')
        ->assertPresent('@link-card')
        ->assertNoJavaScriptErrors();

    Http::assertSentCount(1);
})->with([
    'Facebook' => Platform::Facebook,
    'LinkedIn' => Platform::LinkedIn,
    'Mastodon' => Platform::Mastodon,
]);

test('removing a url removes its visible card', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response('<meta property="og:title" content="Article card">')]);
    [$post, $account] = seedLinkCardPreviewPost(Platform::LinkedIn, 'https://93.184.216.34/article');

    $page = visit(route('app.posts.edit', $post));
    waitForLinkCardPreviewTestId($page, "composer-caption-{$account->id}");
    waitForLinkCardPreviewText($page, 'Article card');

    $page->assertSeeIn('@link-card-title', 'Article card')
        ->clear("@composer-caption-{$account->id}");
    waitForLinkCardPreviewGone($page);

    $page->assertMissing('@link-card')
        ->assertNoJavaScriptErrors();
});

test('a failed fetch clears the previous card and a later url can recover', function () {
    Http::fake([
        'https://93.184.216.34/article' => Http::response('<meta property="og:title" content="Article card">'),
        'https://93.184.216.34/broken' => Http::response('', 500),
        'https://93.184.216.34/recovered' => Http::response('<meta property="og:title" content="Recovered card">'),
    ]);
    [$post, $account] = seedLinkCardPreviewPost(Platform::LinkedIn, 'https://93.184.216.34/article');

    $page = visit(route('app.posts.edit', $post));
    waitForLinkCardPreviewTestId($page, "composer-caption-{$account->id}");
    waitForLinkCardPreviewText($page, 'Article card');

    $page->assertSeeIn('@link-card-title', 'Article card')
        ->fill("@composer-caption-{$account->id}", 'https://93.184.216.34/broken');

    Execution::instance()->waitForExpectation(fn () => Http::assertSentCount(2));
    waitForLinkCardPreviewGone($page);

    $page->assertMissing('@link-card')
        ->fill("@composer-caption-{$account->id}", 'https://93.184.216.34/recovered');
    waitForLinkCardPreviewText($page, 'Recovered card');

    $page->assertSeeIn('@link-card-title', 'Recovered card')
        ->assertNoJavaScriptErrors();
});

test('a pending response cannot overwrite a newer card or revive a removed url', function (bool $removeUrl) {
    $release = new DeferredFuture;
    $started = false;
    Http::fake(function ($request) use ($release, &$started) {
        if ($request->url() === 'https://93.184.216.34/slow') {
            $started = true;
            $release->getFuture()->await(new TimeoutCancellation(20));

            return Http::response('<meta property="og:title" content="Stale card">');
        }

        return Http::response('<meta property="og:title" content="Current card">');
    });

    try {
        [$post, $account] = seedLinkCardPreviewPost(Platform::LinkedIn, 'https://93.184.216.34/slow');
        $page = visit(route('app.posts.edit', $post));
        waitForLinkCardPreviewTestId($page, "composer-caption-{$account->id}");

        Execution::instance()->waitForExpectation(function () use (&$started) {
            expect($started)->toBeTrue();
        });

        $page->fill("@composer-caption-{$account->id}", $removeUrl ? '' : 'https://93.184.216.34/current');

        if (! $removeUrl) {
            waitForLinkCardPreviewText($page, 'Current card');
            $page->assertSeeIn('@link-card-title', 'Current card');
        }
    } finally {
        if (! $release->isComplete()) {
            $release->complete();
        }
    }

    $page->page()->waitForLoadState('networkidle');
    $page->assertDontSee('Stale card');

    if ($removeUrl) {
        $page->assertMissing('@link-card');
    } else {
        $page->assertSeeIn('@link-card-title', 'Current card');
    }

    $page->assertNoJavaScriptErrors();
})->with(['newer url' => false, 'removed url' => true]);

test('attached media suppresses fetching until it is removed', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response('<meta property="og:title" content="Article card">')]);
    [$post, $account] = seedLinkCardPreviewPost(Platform::LinkedIn, 'https://93.184.216.34/article', [[
        'id' => 'image-1',
        'type' => 'image',
        'mime_type' => 'image/png',
        'path' => 'uploads/image.png',
        'url' => 'data:image/png;base64,'.base64_encode(file_get_contents(__DIR__.'/../fixtures/1x1.png')),
        'size' => 68,
    ]]);

    $page = visit(route('app.posts.edit', $post));
    waitForLinkCardPreviewTestId($page, "composer-{$account->id}-media-item");

    $page->assertPresent("@composer-{$account->id}-media-item")
        ->assertMissing('@link-card');

    Http::assertNothingSent();

    $page->click("@composer-{$account->id}-remove-0");
    waitForLinkCardPreviewText($page, 'Article card');

    $page->assertSeeIn('@link-card-title', 'Article card')
        ->assertNoJavaScriptErrors();

    Http::assertSentCount(1);
});
