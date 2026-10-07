<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\UsageType;
use App\Models\Account;
use App\Models\AiUsageLog;
use App\Support\BillingCycle;

/**
 * Monthly ceiling on AI video generations for an account. Every accepted
 * generation is one `AiUsageLog` row of type video, so the count for the
 * current billing cycle is the usage.
 */
final class AiVideoQuota
{
    public static function limit(): int
    {
        return max(0, (int) config('trypost.ai_video.monthly_limit'));
    }

    public static function used(Account $account): int
    {
        $cycle = BillingCycle::for($account);

        return AiUsageLog::countOfTypeBetween(
            (string) $account->id,
            UsageType::Video,
            $cycle->periodStart(),
            $cycle->periodEnd(),
        );
    }

    public static function remaining(Account $account): int
    {
        return max(0, self::limit() - self::used($account));
    }
}
