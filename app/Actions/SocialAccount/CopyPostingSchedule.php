<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Models\SocialAccount;
use Illuminate\Validation\ValidationException;

class CopyPostingSchedule
{
    public function handle(SocialAccount $account, SocialAccount $source): SocialAccount
    {
        if ($source->posting_schedule === null) {
            throw ValidationException::withMessages(['from' => trans('validation.exists', ['attribute' => 'from'])]);
        }

        $account->update(['posting_schedule' => $source->posting_schedule]);

        ReflowChannelQueue::afterCommit($account->id);

        return $account;
    }
}
