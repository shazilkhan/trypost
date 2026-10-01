<?php

declare(strict_types=1);

namespace App\Enums\User;

enum TimeFormat: string
{
    case TwelveHour = '12h';
    case TwentyFourHour = '24h';

    /**
     * The format a user gets until they pick one: English reads a 12-hour clock,
     * every other supported language a 24-hour one.
     */
    public static function forLocale(?Locale $locale): self
    {
        return $locale === Locale::English ? self::TwelveHour : self::TwentyFourHour;
    }
}
