<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Vite;

function seedYouTubeDescriptionEditor(): array
{
    app(Vite::class)->useHotFile(storage_path('framework/testing-youtube.hot'));
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $asset = Media::factory()->assets()->video()->for($workspace, 'mediable')->create([
        'size' => filesize(base_path('tests/fixtures/sample.mp4')),
    ]);

    $platforms = collect(range(1, 2))->map(function (int $number) use ($workspace, $user, $asset) {
        $account = SocialAccount::factory()->youtube()->create([
            'workspace_id' => $workspace->id,
            'username' => "ytchannel{$number}",
        ]);
        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Short title',
            'status' => Status::Draft,
            'media' => [MediaItem::fromMedia($asset)->toArray()],
        ]);

        return PostPlatform::factory()->youtube()->create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'enabled' => true,
            'meta' => ['description' => "Channel {$number}"],
        ]);
    });
    test()->actingAs($user);

    return [$platforms[0]->post, $platforms];
}

function waitForYouTubeElement(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector('[data-testid="{$testId}"]');
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise(resolve => setTimeout(resolve, 50));
            }
        })();
    JS);
}

test('youtube description editor counts UTF-8 bytes and saves only its independent post', function (int $width, int $height) {
    [$post, $platforms] = seedYouTubeDescriptionEditor();
    $page = visit(route('app.posts.edit', $post))->resize($width, $height);
    waitForYouTubeElement($page, 'youtube-settings-toggle-0');
    $page->click('@youtube-settings-toggle-0')
        ->assertValue('@youtube-description-0', 'Channel 1');

    $page->fill('@youtube-description-0', str_repeat('é', 2500))
        ->assertAttributeMissing('@youtube-description-0', 'aria-invalid');
    $page->fill('@youtube-description-0', str_repeat('é', 2501))
        ->assertAttribute('@youtube-description-0', 'aria-invalid', 'true');

    $description = "First channel description 😀 ação\nhttps://example.com\nif (a < b && c > d) {}";
    $page->fill('@youtube-description-0', $description)
        ->assertAttributeMissing('@youtube-description-0', 'aria-invalid');

    if ($width < 1024) {
        $page->click('@composer-preview-toggle');
    }
    $page->click('details > summary')
        ->assertSeeIn('@youtube-preview-description', 'if (a < b && c > d) {}')
        ->assertNoJavaScriptErrors();
    if ($width < 1024) {
        $page->click('@composer-mobile-compose');
    }

    $page->click('@composer-save-draft')->assertMissing('@post-composer-dialog');
    expect(data_get($platforms[0]->fresh()->meta, 'description'))->toBe($description)
        ->and(data_get($platforms[1]->fresh()->meta, 'description'))->toBe('Channel 2')
        ->and($post->fresh()->content)->toBe('Short title');
})->with([[1280, 900], [375, 812]]);

test('youtube description clearing restores content fallback without changing another post', function (string $description) {
    [$post, $platforms] = seedYouTubeDescriptionEditor();
    $page = visit(route('app.posts.edit', $post))->resize(375, 812);
    waitForYouTubeElement($page, 'youtube-settings-toggle-0');
    $page->click('@youtube-settings-toggle-0')
        ->fill('@youtube-description-0', $description)
        ->click('@composer-save-draft')
        ->assertMissing('@post-composer-dialog');

    expect(data_get($platforms[0]->fresh()->meta, 'description'))->toBeNull()
        ->and(data_get($platforms[1]->fresh()->meta, 'description'))->toBe('Channel 2');

    $page = visit(route('app.posts.edit', $post))->resize(375, 812);
    $page->click('@composer-preview-toggle')
        ->click('details > summary')
        ->assertSeeIn('@youtube-preview-description', 'Short title')
        ->assertNoJavaScriptErrors();
})->with([
    'empty description' => [''],
    'whitespace description' => [" \t\n\u{00A0}"],
]);

test('youtube description long preview stays above the phone navigation', function (int $width, int $height) {
    [$post, $platforms] = seedYouTubeDescriptionEditor();
    $description = "Full description\n".str_repeat('https://example.com/'.str_repeat('a', 100)."\n", 35);
    $platforms[0]->update(['meta' => ['description' => $description]]);
    $page = visit(route('app.posts.edit', $post))->resize($width, $height);
    if ($width < 1024) {
        $page->click('@composer-preview-toggle');
    }
    $page->click('details > summary')->assertSeeIn('@youtube-preview-description', 'Full description');

    $layout = $page->script(<<<'JS'
        (() => {
            const description = document.querySelector('[data-testid=youtube-preview-description]');
            const navigation = description.parentElement.parentElement.parentElement.lastElementChild;
            return {
                scrolls: description.scrollHeight > description.clientHeight,
                bounded: description.clientHeight <= 128,
                aboveNavigation: description.getBoundingClientRect().bottom <= navigation.getBoundingClientRect().top,
                noOverflow: document.documentElement.scrollWidth <= window.innerWidth,
            };
        })();
    JS);
    expect($layout)->toEqual(['scrolls' => true, 'bounded' => true, 'aboveNavigation' => true, 'noOverflow' => true]);
    $page->assertNoJavaScriptErrors();
})->with([[1280, 900], [375, 812]]);
