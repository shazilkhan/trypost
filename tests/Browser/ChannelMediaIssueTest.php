<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

/**
 * A post carrying a PDF with one deselected X channel. X never accepts a
 * document, so the channel has a media issue before the user touches it.
 */
function seedChannelMediaIssuePost(): PostPlatform
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $account = SocialAccount::factory()->x()->create(['workspace_id' => $workspace->id]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'hello',
        'media' => [[
            'id' => 'd1',
            'type' => 'document',
            'mime_type' => 'application/pdf',
            'path' => 'uploads/deck.pdf',
            'url' => 'https://cdn.test/deck.pdf',
            'size' => 1024,
        ]],
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => Platform::X,
        'content_type' => ContentType::XPost,
    ]);

    test()->actingAs($user);

    return $postPlatform;
}

function waitForChannelIssueTestId(mixed $page, string $testId): void
{
    waitForChannelIssueCondition($page, $testId, 'el.getBoundingClientRect().height > 0');
}

function waitForChannelIssuePressed(mixed $page, string $testId): void
{
    waitForChannelIssueCondition($page, $testId, "el.getAttribute('aria-pressed') === 'true'");
}

/**
 * Polls from the page (never sleep(): the test's HTTP server only ticks while
 * Pest awaits Playwright) until the element exists and `$condition` holds.
 */
function waitForChannelIssueCondition(mixed $page, string $testId, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector(sel);
                if (el && ({$condition})) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

test('an independent channel draft with incompatible media remains editable', function () {
    $postPlatform = seedChannelMediaIssuePost();

    $page = visit(route('app.posts.edit', $postPlatform->post));
    waitForChannelIssueTestId($page, 'post-composer-dialog');

    $page->assertVisible('@post-composer-dialog')
        ->assertVisible('@composer-customization')
        ->assertNoJavaScriptErrors();
});

test('an Instagram media warning links to the network limits', function () {
    [$post] = seedInstagramFeedImagePost(size: 9 * 1024 * 1024);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'media-rules-warning');

    expect($page->script('document.querySelector("[data-testid=media-rules-warning] a")?.href'))
        ->toBe('https://docs.trypost.it/knowledge-base/media#instagram');
    $page->assertNoJavaScriptErrors();
});

/**
 * @param  array<string, string>  $platformMeta
 * @param  array<string, int>  $imageMeta
 * @return array{Post, PostPlatform}
 */
function seedInstagramFeedImagePost(array $platformMeta = [], array $imageMeta = ['width' => 1080, 'height' => 1440], int $size = 1024, int $imageCount = 5): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $account = SocialAccount::factory()->instagram()->create(['workspace_id' => $workspace->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'A five-image carousel',
        'media' => array_map(fn (int $index): array => [
            'id' => "image-{$index}",
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'path' => "uploads/image-{$index}.jpg",
            'url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'size' => $size,
            'meta' => $imageMeta,
        ], range(1, $imageCount)),
    ]);
    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => Platform::Instagram,
        'content_type' => ContentType::InstagramFeed,
        'meta' => $platformMeta,
    ]);

    test()->actingAs($user);

    return [$post, $postPlatform];
}

test('Instagram feed has no global aspect control and asks to adjust unsupported images', function () {
    [$post] = seedInstagramFeedImagePost(['aspect_ratio' => '1:1']);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'post-composer-dialog');

    $page->assertMissing('@instagram-aspect-original')
        ->assertMissing('@instagram-aspect-1-1')
        ->assertVisible('@instagram-image-aspect-issue-0')
        ->assertDisabled('@composer-submit')
        ->assertVisible('@instagram-edit-image-0');

    waitForChannelIssueTestId($page, 'instagram-feed-media');
    $heightToWidth = $page->script('(() => { const frame = document.querySelector("[data-testid=instagram-feed-media]"); const rect = frame.getBoundingClientRect(); return rect.height / rect.width; })()');
    expect(abs($heightToWidth - 4 / 3))->toBeLessThan(0.01);

    $page->assertNoJavaScriptErrors();
});

test('Instagram feed without a saved aspect ratio uses the original image', function () {
    [$post, $postPlatform] = seedInstagramFeedImagePost([]);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, "channel-{$postPlatform->id}");

    $page->assertMissing('@instagram-aspect-original')
        ->assertVisible('@instagram-image-aspect-issue-0')
        ->assertDisabled('@composer-submit')
        ->assertNoJavaScriptErrors();

    waitForChannelIssueTestId($page, 'instagram-feed-media');

    $heightToWidth = $page->script('(() => { const frame = document.querySelector("[data-testid=instagram-feed-media]"); const rect = frame.getBoundingClientRect(); return rect.height / rect.width; })()');

    expect(abs($heightToWidth - 4 / 3))->toBeLessThan(0.01);
});

test('saving an Instagram post removes its legacy global aspect ratio', function () {
    [$post, $postPlatform] = seedInstagramFeedImagePost(['aspect_ratio' => '1:1'], ['width' => 1080, 'height' => 1350], imageCount: 1);
    $asset = Media::factory()->assets()->for($post->workspace, 'mediable')->create([
        'meta' => ['width' => 1080, 'height' => 1350],
    ]);
    $post->update(['media' => [MediaItem::fromMedia($asset)->toArray()]]);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'composer-save-draft');

    $page->assertMissing('@instagram-aspect-original')
        ->assertMissing('@instagram-image-aspect-issue-0')
        ->click('@composer-save-draft')
        ->assertNoJavaScriptErrors();

    $page->script(<<<'JS'
        (async () => {
            for (let attempt = 0; attempt < 80; attempt++) {
                if (!document.querySelector('[data-testid="post-composer-dialog"]')) return;
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        })();
    JS);

    expect($page->script('Boolean(document.querySelector("[data-testid=post-composer-dialog]"))'))->toBeFalse();
    expect(data_get($postPlatform->fresh()->meta, 'aspect_ratio'))->toBeNull();
});

test('Instagram original preview falls back to square when image dimensions are unavailable', function () {
    [$post] = seedInstagramFeedImagePost(imageMeta: []);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'instagram-feed-media');

    $heightToWidth = $page->script('(() => { const frame = document.querySelector("[data-testid=instagram-feed-media]"); const rect = frame.getBoundingClientRect(); return rect.height / rect.width; })()');

    expect(abs($heightToWidth - 1.0))->toBeLessThan(0.01);
});

test('Instagram feed still enforces image size limits', function () {
    [$post] = seedInstagramFeedImagePost(size: 9 * 1024 * 1024);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'media-rules-warning');

    $page->assertPresent('@media-rules-warning')
        ->assertNoJavaScriptErrors();
});

test('supported Instagram original image keeps its own aspect and can publish', function () {
    [$post] = seedInstagramFeedImagePost(imageMeta: ['width' => 1080, 'height' => 1350]);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'post-composer-dialog');

    $page->assertMissing('@instagram-image-aspect-issue-0')
        ->assertEnabled('@composer-submit')
        ->assertNoJavaScriptErrors();
});

test('Instagram image adjustment offers supported crop presets and clears the original warning', function () {
    [$post] = seedInstagramFeedImagePost(['aspect_ratio' => '4:5'], imageCount: 1);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'instagram-edit-image-0');

    $page->click('@instagram-edit-image-0')
        ->assertVisible('@crop-aspect-4-5')
        ->assertVisible('@crop-aspect-1-1')
        ->assertVisible('@crop-aspect-16-9')
        ->click('@crop-aspect-1-1')
        ->click('@crop-save');

    $page->script(<<<'JS'
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (!document.querySelector('[data-testid="instagram-image-aspect-issue-0"]')
                    && !document.querySelector('[data-testid="crop-save"]')) return;
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        })();
    JS);

    $page->assertMissing('@instagram-image-aspect-issue-0')
        ->assertEnabled('@composer-submit')
        ->assertNoJavaScriptErrors();
});

test('Instagram image editor saves alt text on the selected slide', function () {
    [$post] = seedInstagramFeedImagePost(imageCount: 1);
    $asset = Media::factory()->assets()->for($post->workspace, 'mediable')->create([
        'meta' => ['width' => 1080, 'height' => 1440],
    ]);
    $post->update(['media' => [MediaItem::fromMedia($asset)->toArray()]]);

    $page = visit(route('app.posts.edit', $post));
    waitForChannelIssueTestId($page, 'instagram-edit-image-0');

    $page->click('@instagram-edit-image-0')
        ->click('@media-editor-alt-tab')
        ->fill('@media-editor-alt-text', 'A bright room with a wooden table')
        ->click('@crop-save')
        ->click('@composer-save-draft')
        ->assertNoJavaScriptErrors();

    $page->script(<<<'JS'
        (async () => {
            for (let attempt = 0; attempt < 80; attempt++) {
                if (!document.querySelector('[data-testid="post-composer-dialog"]')) return;
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        })();
    JS);

    expect($page->script('Boolean(document.querySelector("[data-testid=post-composer-dialog]"))'))->toBeFalse();
    expect(data_get($post->fresh()->media, '0.meta.alt_text'))
        ->toBe('A bright room with a wooden table');
});

test('appearance filter saves a new image without cropping its original dimensions', function () {
    [$post, $postPlatform] = seedInstagramFeedImagePost(imageMeta: ['width' => 200, 'height' => 200], imageCount: 1);
    $base64 = base64_encode((string) file_get_contents(base_path('tests/fixtures/crop-quadrants.png')));
    $media = $post->media;
    $media[0]['url'] = "data:image/png;base64,{$base64}";
    $post->update(['media' => $media]);

    $page = visit(route('app.posts.edit', $post));

    $page->click("@composer-crop-{$postPlatform->social_account_id}-0")
        ->click('@media-editor-appearance-tab')
        ->click('@media-filter-mono');

    $page->script(<<<'JS'
        const slider = document.querySelector('[data-testid="media-adjust-brightness"]');
        slider.value = '50';
        slider.dispatchEvent(new Event('input', { bubbles: true }));
    JS);

    $page->assertSee('50%')
        ->assertEnabled('@crop-save')
        ->click('@crop-save');

    $page->script(<<<'JS'
        (async () => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (!document.querySelector('[data-testid="crop-save"]')
                    && !document.querySelector('[data-testid="composer-save-draft"]')?.disabled) return;
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        })();
    JS);

    $newAsset = Media::where('mediable_id', $post->workspace_id)->where('collection', 'assets')->sole();
    $image = imagecreatefromstring(Storage::get($newAsset->path));
    expect($image)->toBeInstanceOf(GdImage::class)
        ->and(imagesx($image))->toBe(200)
        ->and(imagesy($image))->toBe(200);

    $pixel = imagecolorsforindex($image, imagecolorat($image, 50, 50));
    expect(abs($pixel['red'] - $pixel['green']))->toBeLessThan(5)
        ->and(abs($pixel['green'] - $pixel['blue']))->toBeLessThan(5)
        ->and($pixel['red'])->toBeGreaterThan(70);
});
