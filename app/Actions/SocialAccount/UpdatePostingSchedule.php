<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Models\SocialAccount;
use App\Support\PostingSchedule;

class UpdatePostingSchedule
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SocialAccount $account, array $data): SocialAccount
    {
        $schedule = data_get($data, 'posting_schedule');
        $previousTimezone = $account->timezone;

        $account->update([
            'timezone' => data_get($data, 'timezone'),
            'posting_goal' => data_get($data, 'posting_goal'),
            'posting_schedule' => $schedule === null ? null : PostingSchedule::fromArray($schedule),
        ]);

        ReflowChannelQueue::afterCommit($account->id, $previousTimezone);

        return $account;
    }
}
