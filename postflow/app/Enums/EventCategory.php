<?php

namespace App\Enums;

enum EventCategory: string
{
    case Religious = 'religious';
    case Commercial = 'commercial';
    case National = 'national';
    case School = 'school';
    case Season = 'season';

    public function label(): string
    {
        return match ($this) {
            self::Religious => __('Religious'),
            self::Commercial => __('Commercial'),
            self::National => __('National'),
            self::School => __('School'),
            self::Season => __('Season'),
        };
    }
}
