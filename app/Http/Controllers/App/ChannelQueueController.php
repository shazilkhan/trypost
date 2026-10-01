<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\Queue\ReorderChannelQueue;
use App\Http\Controllers\App\Concerns\EnsuresChannelInCurrentWorkspace;
use App\Http\Requests\App\Channel\ReorderChannelQueueRequest;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;

class ChannelQueueController extends Controller
{
    use EnsuresChannelInCurrentWorkspace;

    public function reorder(ReorderChannelQueueRequest $request, SocialAccount $account): RedirectResponse
    {
        $this->ensureCurrentWorkspace($request, $account);
        $this->authorize('createPost', $request->user()->currentWorkspace);

        ReorderChannelQueue::handle($account, $request->validated('post_ids'));

        return back();
    }
}
