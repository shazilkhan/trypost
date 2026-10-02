<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<int, array<string, mixed>>  $media
 */
function seedChannelSettingsPost(Platform $platform, ContentType $contentType, array $media = []): PostPlatform
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => $platform,
        'username' => 'trypostit',
        'access_token' => 'channel-token',
        'token_expires_at' => now()->addDays(20),
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'Channel settings',
        'media' => $media,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => $platform,
        'content_type' => $contentType,
        'meta' => [],
    ]);

    test()->actingAs($user);

    return $postPlatform;
}

function waitForChannelSettingsCondition(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let i = 0; i < 100; i++) {
                if ({$condition}) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForChannelSettingsTestId(mixed $page, string $testId): void
{
    waitForChannelSettingsCondition(
        $page,
        "document.querySelector('[data-testid=\"{$testId}\"]')?.getBoundingClientRect().height > 0",
    );
}

function fakeChannelSettingsApis(): void
{
    $boards = [
        ['id' => 'board_1', 'name' => 'Disney', 'media' => ['image_cover_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==']],
        ['id' => 'board_2', 'name' => 'Social'],
    ];
    $listCalls = 0;

    Http::fake([
        config('trypost.platforms.pinterest.api').'/boards*' => function (Request $request) use (&$listCalls, $boards) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'board_new', 'name' => data_get($request->data(), 'name')], 201);
            }

            $listCalls++;

            return Http::response([
                'items' => $listCalls === 1 ? $boards : [...$boards, ['id' => 'board_3', 'name' => 'Universal']],
            ]);
        },
        '*' => Http::response([], 200),
    ]);
}

test('every network with settings renders them as label and control rows under the editor', function (Platform $platform, ContentType $contentType, array $media) {
    fakeChannelSettingsApis();
    $postPlatform = seedChannelSettingsPost($platform, $contentType, $media);

    $page = visit(route('app.posts.edit', $postPlatform->post))->resize(1280, 900);
    waitForChannelSettingsTestId($page, 'channel-settings-rows');

    $layout = $page->script(<<<'JS'
        (() => {
            const card = document.querySelector('[data-testid=composer-customization]');
            const section = card.querySelector('[data-testid=channel-settings-rows]');
            const row = section.querySelector('[data-testid=channel-settings-row]');
            const label = row.firstElementChild;
            const caption = card.querySelector('textarea');
            return {
                insideCard: card.contains(section),
                belowEditor: !!(caption.compareDocumentPosition(section) & Node.DOCUMENT_POSITION_FOLLOWING),
                columns: getComputedStyle(row).gridTemplateColumns.split(' ')[0],
                labelSize: getComputedStyle(label).fontSize,
                labelWeight: getComputedStyle(label).fontWeight,
                divider: getComputedStyle(section).borderTopWidth,
                noOverflow: document.documentElement.scrollWidth <= window.innerWidth,
            };
        })();
    JS);

    expect($layout)->toEqual([
        'insideCard' => true,
        'belowEditor' => true,
        'columns' => '130px',
        'labelSize' => '13px',
        'labelWeight' => '500',
        'divider' => '1px',
        'noOverflow' => true,
    ]);
    $page->assertMissing('@facebook-settings-toggle')
        ->assertMissing('@tiktok-settings-toggle')
        ->assertNoJavaScriptErrors();
})->with([
    'facebook' => [Platform::Facebook, ContentType::FacebookPost, []],
    'tiktok' => [Platform::TikTok, ContentType::TikTokVideo, []],
    'pinterest' => [Platform::Pinterest, ContentType::PinterestPin, []],
    'youtube' => [Platform::YouTube, ContentType::YouTubeShort, []],
    'google business' => [Platform::GoogleBusiness, ContentType::GoogleBusinessPost, []],
    'discord' => [Platform::Discord, ContentType::DiscordMessage, []],
    'linkedin document' => [Platform::LinkedIn, ContentType::LinkedInPost, [[
        'id' => 'd1',
        'type' => 'document',
        'mime_type' => 'application/pdf',
        'path' => 'uploads/deck.pdf',
        'url' => 'https://cdn.test/deck.pdf',
        'size' => 1024,
        'original_filename' => 'deck.pdf',
    ]]],
]);

test('the instagram channel card picks its content type from a radio row above the editor', function () {
    fakeChannelSettingsApis();
    $postPlatform = seedChannelSettingsPost(Platform::Instagram, ContentType::InstagramFeed);
    $id = $postPlatform->social_account_id;

    $page = visit(route('app.posts.edit', $postPlatform->post))->resize(1280, 900);
    waitForChannelSettingsTestId($page, "composer-type-{$id}-instagram_feed");

    $page->assertAttribute("@composer-type-{$id}-instagram_feed", 'aria-checked', 'true')
        ->assertAttribute("@composer-type-{$id}-instagram_reel", 'aria-checked', 'false')
        ->assertPresent("@composer-type-{$id}-instagram_story")
        ->assertMissing('@instagram-settings-toggle');

    expect($page->script(<<<JS
        (() => {
            const radios = document.querySelector('[data-testid="composer-type-{$id}"]');
            const caption = document.querySelector('[data-testid="composer-caption-{$id}"]');
            return !!(radios.compareDocumentPosition(caption) & Node.DOCUMENT_POSITION_FOLLOWING);
        })();
    JS))->toBeTrue();

    $page->click("@composer-type-{$id}-instagram_reel");
    waitForChannelSettingsCondition($page, "document.querySelector('[data-testid=\"composer-type-{$id}-instagram_reel\"]')?.getAttribute('aria-checked') === 'true'");
    $page->assertAttribute("@composer-type-{$id}-instagram_reel", 'aria-checked', 'true')
        ->assertNoJavaScriptErrors();

    $page->click('@composer-save-draft')->assertMissing('@post-composer-dialog');
    expect($postPlatform->fresh()->content_type)->toBe(ContentType::InstagramReel);
});

test('a pinterest channel offers photo, video and carousel pins', function () {
    fakeChannelSettingsApis();
    $postPlatform = seedChannelSettingsPost(Platform::Pinterest, ContentType::PinterestPin);
    $id = $postPlatform->social_account_id;

    $page = visit(route('app.posts.edit', $postPlatform->post))->resize(1280, 900);
    waitForChannelSettingsTestId($page, "composer-type-{$id}-pinterest_pin");

    $page->assertSeeIn("@composer-type-{$id}", 'Photo')
        ->assertSeeIn("@composer-type-{$id}", 'Video')
        ->assertSeeIn("@composer-type-{$id}", 'Carousel')
        ->assertAttribute("@composer-type-{$id}-pinterest_pin", 'aria-checked', 'true')
        ->click("@composer-type-{$id}-pinterest_carousel");
    waitForChannelSettingsCondition($page, "document.querySelector('[data-testid=\"composer-type-{$id}-pinterest_carousel\"]')?.getAttribute('aria-checked') === 'true'");

    $page->assertAttribute("@composer-type-{$id}-pinterest_carousel", 'aria-checked', 'true')
        ->assertNoJavaScriptErrors();
});

test('the pinterest board picker searches, refreshes and creates boards', function () {
    fakeChannelSettingsApis();
    $postPlatform = seedChannelSettingsPost(Platform::Pinterest, ContentType::PinterestPin);

    $page = visit(route('app.posts.edit', $postPlatform->post))->resize(1280, 900);
    waitForChannelSettingsTestId($page, 'pinterest-board-trigger');

    $page->click('@pinterest-board-trigger');
    waitForChannelSettingsTestId($page, 'pinterest-board-option-board_1');
    $page->assertSeeIn('@pinterest-board-picker', '@trypostit')
        ->assertVisible('@pinterest-board-option-board_2');
    expect($page->script("!!document.querySelector('[data-testid=\"pinterest-board-option-board_1\"] img')"))->toBeTrue();

    $page->fill('@pinterest-board-search', 'dis');
    waitForChannelSettingsCondition($page, "!document.querySelector('[data-testid=\"pinterest-board-option-board_2\"]')");
    $page->assertMissing('@pinterest-board-option-board_2')
        ->assertVisible('@pinterest-board-option-board_1')
        ->fill('@pinterest-board-search', '');

    $page->click('@pinterest-boards-refresh');
    waitForChannelSettingsTestId($page, 'pinterest-board-option-board_3');
    $page->assertSeeIn('@pinterest-board-option-board_3', 'Universal');

    $page->fill('@pinterest-board-new-name', 'Recipes')
        ->click('@pinterest-board-create');
    waitForChannelSettingsTestId($page, 'pinterest-board-selected');
    $page->assertSeeIn('@pinterest-board-selected', 'Recipes')
        ->assertMissing('@pinterest-board-picker')
        ->assertNoJavaScriptErrors();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === config('trypost.platforms.pinterest.api').'/boards'
        && $request->data() === ['name' => 'Recipes']);

    $page->click('@pinterest-board-trigger');
    waitForChannelSettingsTestId($page, 'pinterest-board-new-name');
    expect($page->script("document.querySelector('[data-testid=\"pinterest-board-new-name\"]').value"))->toBe('');

    $page->click('@pinterest-boards-refresh');
    waitForChannelSettingsCondition($page, "!document.querySelector('[data-testid=\"pinterest-board-option-board_new\"]')");
    $page->assertMissing('@pinterest-board-option-board_new')
        ->assertVisible('@pinterest-board-option-board_3');
    $page->click('@pinterest-board-option-board_3');
    waitForChannelSettingsCondition($page, "!document.querySelector('[data-testid=\"pinterest-board-picker\"]')");

    $page->click('@composer-save-draft')->assertMissing('@post-composer-dialog');
    expect(data_get($postPlatform->fresh()->meta, 'board_id'))->toBe('board_3');
});

test('the pinterest board picker shows the reconnect error when pinterest refuses the board', function () {
    Http::fake([
        config('trypost.platforms.pinterest.api').'/boards*' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response(['code' => 3, 'message' => 'Not authorized'], 403)
            : Http::response(['items' => [['id' => 'board_1', 'name' => 'Disney']]]),
        '*' => Http::response([], 200),
    ]);
    $postPlatform = seedChannelSettingsPost(Platform::Pinterest, ContentType::PinterestPin);

    $page = visit(route('app.posts.edit', $postPlatform->post))->resize(1280, 900);
    waitForChannelSettingsTestId($page, 'pinterest-board-trigger');
    $page->click('@pinterest-board-trigger');
    waitForChannelSettingsTestId($page, 'pinterest-board-new-name');

    $page->fill('@pinterest-board-new-name', 'Recipes')
        ->click('@pinterest-board-create');
    waitForChannelSettingsTestId($page, 'pinterest-board-create-error');

    $page->assertSeeIn('@pinterest-board-create-error', __('posts.form.pinterest.boards_reconnect'))
        ->assertMissing('@pinterest-board-selected')
        ->assertNoJavaScriptErrors();
});
