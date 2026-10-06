<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Models\SocialAccount;

class RegeneratePostingSchedule
{
    public const DEFAULT_GOAL = 3;

    public function __construct(private readonly GeneratePostingSchedule $generate) {}

    public function handle(SocialAccount $account, ?int $goal = null): SocialAccount
    {
        $goal ??= $account->posting_goal ?? self::DEFAULT_GOAL;

        $account->update([
            'posting_goal' => $goal,
            'posting_schedule' => $this->generate->handle($account->platform, $goal),
        ]);

        ReflowChannelQueue::afterCommit($account->id);

        return $account;
    }
}
