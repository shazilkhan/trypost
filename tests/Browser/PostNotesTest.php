<?php

declare(strict_types=1);

use App\Mail\PostNoteAdded;
use App\Models\Post;
use App\Models\PostNote;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

/**
 * Poll browser-side until the testid element has laid out (width/height > 0).
 * These Pest browser assertions do not auto-wait, so settle async UI first.
 */
function waitForPostNotesTestId(mixed $page, string $testId): void
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

test('adding a note from the popover shows it and emails the other members', function () {
    Mail::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $teammate = User::factory()->create();
    $workspace->members()->attach($teammate->id, membershipPivot('member'));

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'hello team',
    ]);

    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));

    waitForPostNotesTestId($page, "post-notes-trigger-{$post->id}");
    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'note-input');

    $page->fill('@note-input', 'Please check the @image before Friday');
    $page->click('@note-send');
    waitForPostNotesTestId($page, 'note-body');

    $page->assertSeeIn('@note-body', 'Please check the @image before Friday')
        ->assertNoJavaScriptErrors();

    expect(PostNote::query()->where('post_id', $post->id)->value('body'))
        ->toBe('Please check the @image before Friday');

    Mail::assertQueued(PostNoteAdded::class, fn (PostNoteAdded $mail) => $mail->hasTo($teammate->email));
    Mail::assertNotQueued(PostNoteAdded::class, fn (PostNoteAdded $mail) => $mail->hasTo($user->email));
});
