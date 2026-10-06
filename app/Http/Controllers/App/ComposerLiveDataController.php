<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\Queue\ListTakenSlots;
use App\Actions\SocialAccount\ListPinterestBoards;
use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Post\ShowComposerLiveDataRequest;
use App\Http\Resources\App\ComposerLiveDataResource;
use App\Http\Resources\App\HandleInertiaRequests\ComposerResource;
use App\Models\SocialAccount;
use App\Services\Social\TikTokCreatorInfo;

class ComposerLiveDataController extends Controller
{
    public function __invoke(ShowComposerLiveDataRequest $request, TikTokCreatorInfo $tikTokCreatorInfo): ComposerLiveDataResource
    {
        $workspace = $request->user()->currentWorkspace;
        $accounts = $workspace->socialAccounts()->get();
        $composerKey = ComposerResource::key($workspace);
        $isStale = $request->validated('key') !== $composerKey;

        return new ComposerLiveDataResource([
            ...($isStale ? ['composer' => ComposerResource::make($workspace), 'composerKey' => $composerKey] : []),
            'takenSlots' => ListTakenSlots::handle($accounts->modelKeys(), now()),
            'pinterestBoards' => $accounts->where('platform', Platform::Pinterest)->mapWithKeys(fn (SocialAccount $account): array => [
                $account->id => rescue(fn (): array => ListPinterestBoards::cached($account), ['boards' => [], 'truncated' => false], report: false),
            ])->all(),
            'tiktokCreatorInfos' => $accounts->where('platform', Platform::TikTok)->mapWithKeys(fn (SocialAccount $account): array => [
                $account->id => rescue(fn (): array => $tikTokCreatorInfo->fetch($account), null, report: false),
            ])->filter()->all(),
        ]);
    }
}
