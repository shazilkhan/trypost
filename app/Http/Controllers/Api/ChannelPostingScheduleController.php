<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SocialAccount\CopyPostingSchedule;
use App\Actions\SocialAccount\RegeneratePostingSchedule;
use App\Actions\SocialAccount\UpdatePostingSchedule;
use App\Http\Requests\Api\Channel\CopyPostingScheduleRequest;
use App\Http\Requests\Api\Channel\GeneratePostingScheduleRequest;
use App\Http\Requests\Api\Channel\UpdatePostingScheduleRequest;
use App\Http\Resources\Api\ChannelPostingScheduleResource;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class ChannelPostingScheduleController extends Controller
{
    public function update(UpdatePostingScheduleRequest $request, SocialAccount $account, UpdatePostingSchedule $update): ChannelPostingScheduleResource
    {
        $this->authorizeChannel($request, $account);

        $update->handle($account, $request->validated());

        return new ChannelPostingScheduleResource($account);
    }

    public function generate(GeneratePostingScheduleRequest $request, SocialAccount $account, RegeneratePostingSchedule $regenerate): ChannelPostingScheduleResource
    {
        $this->authorizeChannel($request, $account);

        $regenerate->handle($account, $request->validated('goal') === null ? null : (int) $request->validated('goal'));

        return new ChannelPostingScheduleResource($account);
    }

    public function copy(CopyPostingScheduleRequest $request, SocialAccount $account, CopyPostingSchedule $copy): ChannelPostingScheduleResource
    {
        $this->authorizeChannel($request, $account);

        $source = $request->user()->currentWorkspace->socialAccounts()->findOrFail($request->validated('from'));

        return new ChannelPostingScheduleResource($copy->handle($account, $source));
    }

    private function authorizeChannel(Request $request, SocialAccount $account): void
    {
        $this->authorize('view', $account);
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);
    }
}
