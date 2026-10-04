<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

test('zz dark screenshots', function () {
    $user = User::factory()->create(['theme' => 'dark']);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::X, 'username' => 'trypostit']);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'Dark mode check']);
    PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id, 'platform' => Platform::X, 'content_type' => ContentType::XPost, 'meta' => []]);
    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(1440, 900);
    $page->script('new Promise((r) => { const t = () => document.querySelector("[data-testid=\"post-composer-dialog\"]") ? setTimeout(r, 800) : setTimeout(t, 100); t(); })');
    $page->screenshot(filename: 'zz-dark-composer');

    $page = visit(route('app.posts.index', ['tab' => 'sent']))->resize(1440, 900);
    $page->script('new Promise((r) => setTimeout(r, 1500))');
    $page->screenshot(filename: 'zz-dark-index');
});
