<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\User\Locale;
use App\Enums\UserWorkspace\Role;
use App\Mail\PostNoteAdded;
use App\Mail\WorkspaceConnectionsDisconnected;
use App\Mail\WorkspaceInvite;
use App\Models\Account;
use App\Models\Invite;
use App\Models\Post;
use App\Models\PostNote;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Mail;

test('the workspace invite renders in the requested locale', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $invite = Invite::factory()->create([
        'account_id' => $account->id,
        'email' => 'invitee@example.com',
        'role' => Role::Member,
    ]);

    $mailable = (new WorkspaceInvite($invite))->locale(Locale::PortugueseBrazil->value);

    $mailable->assertHasSubject(__('mail.workspace_invite.subject', ['account' => 'Acme Co'], 'pt-BR'));
    $mailable->assertSeeInHtml(__('mail.workspace_invite.heading', [], 'pt-BR'));
    $mailable->assertSeeInHtml(__('mail.workspace_invite.expiry', [], 'pt-BR'));
    $mailable->assertSeeInHtml('Acme Co');
    $mailable->assertSeeInHtml(Role::Member->label());
});

test('the post note email renders the note, the post and its channels', function (Locale $locale) {
    $author = User::factory()->create(['name' => 'Ana Author']);
    $workspace = Workspace::factory()->create([
        'account_id' => $author->account_id,
        'user_id' => $author->id,
        'name' => 'Acme Workspace',
    ]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $author->id,
        'content' => '<p>Launch day is <strong>here</strong></p>',
    ]);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::LinkedIn,
        'display_name' => 'Acme Inc',
    ]);
    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => Platform::LinkedIn,
    ]);
    $note = PostNote::factory()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Can we swap the image?',
    ]);

    $mailable = (new PostNoteAdded($note, $author))->locale($locale->value);

    $mailable->assertHasSubject(__('mail.post_note_added.subject', ['author' => 'Ana Author'], $locale->value));
    $mailable->assertSeeInHtml(__('mail.post_note_added.heading', [], $locale->value));
    $mailable->assertSeeInHtml(e(__('mail.post_note_added.body', ['author' => 'Ana Author', 'workspace' => 'Acme Workspace'], $locale->value)), false);
    $mailable->assertSeeInHtml(__('mail.post_note_added.button', [], $locale->value));
    $mailable->assertSeeInHtml('Can we swap the image?');
    $mailable->assertSeeInHtml('Launch day is here');
    $mailable->assertSeeInHtml('LinkedIn');
    $mailable->assertSeeInHtml(route('app.posts.edit', ['post' => $post, 'comment' => $note->id]), false);
})->with([Locale::English, Locale::PortugueseBrazil]);

test('the post note email falls back when the post has no text', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'content' => '']);
    $note = PostNote::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

    (new PostNoteAdded($note, $author))
        ->assertSeeInHtml(__('mail.post_note_added.post_without_text'));
});

test('the disconnected-connections digest renders every account and reason', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
        'name' => 'Acme Workspace',
    ]);

    $accounts = collect([Platform::LinkedIn, Platform::X])->map(
        fn (Platform $platform) => SocialAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'platform' => $platform,
        ]),
    );

    $mailable = (new WorkspaceConnectionsDisconnected($workspace, $accounts))
        ->locale(Locale::German->value);

    $mailable->assertHasSubject(trans_choice(
        'mail.workspace_connections_disconnected.subject',
        2,
        ['count' => 2, 'workspace' => 'Acme Workspace'],
        'de',
    ));

    $mailable->assertSeeInHtml(__('mail.workspace_connections_disconnected.heading', [], 'de'));
    $mailable->assertSeeInHtml(__('mail.workspace_connections_disconnected.reason_revoked', [], 'de'));
    $mailable->assertSeeInHtml('Acme Workspace');

    foreach ($accounts as $account) {
        $mailable->assertSeeInHtml($account->platform->label());
    }
});

function sentNotificationHtml(User $user, BaseNotification $notification): string
{
    Mail::mailer()->getSymfonyTransport()->messages()->take(0);

    $user->notify($notification);

    $message = Mail::mailer()->getSymfonyTransport()->messages()->last();

    return (string) $message->getOriginalMessage()->getHtmlBody();
}

test('the verification email is sent in the user locale', function () {
    $user = User::factory()->create(['locale' => Locale::Spanish]);

    expect(sentNotificationHtml($user, new VerifyEmail))
        ->toContain(__('mail.email_verification.body', [], 'es'))
        ->toContain(__('mail.email_verification.button', [], 'es'))
        ->toContain(__('mail.layout.team', [], 'es'))
        ->not->toContain(__('mail.email_verification.body', [], 'en'));
});

test('the password reset email is sent in the user locale', function () {
    $user = User::factory()->create(['locale' => Locale::Japanese]);

    expect(sentNotificationHtml($user, new ResetPassword('token-123')))
        ->toContain(__('mail.password_reset.body', [], 'ja'))
        ->toContain(__('mail.password_reset.expiry', [], 'ja'))
        ->not->toContain(__('mail.password_reset.body', [], 'en'));
});
