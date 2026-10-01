<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Support\Timezone;
use Carbon\CarbonInterface;

class CountPostsSentThisWeek
{
    public static function handle(SocialAccount $channel): int
    {
        $now = now(Timezone::normalize($channel->timezone));

        return PostPlatform::query()
            ->where('social_account_id', $channel->id)
            ->enabled()
            ->published()
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [
                $now->startOfWeek(CarbonInterface::MONDAY)->utc(),
                $now->endOfWeek(CarbonInterface::SUNDAY)->utc(),
            ])
            ->count();
    }
}
