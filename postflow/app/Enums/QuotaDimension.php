<?php

namespace App\Enums;

enum QuotaDimension: string
{
    case Total = 'total';
    case Format = 'format';
    case Offer = 'offer';
    case Platform = 'platform';
    case Series = 'series';
    case ProductFamily = 'product_family';

    public function label(): string
    {
        return match ($this) {
            self::Total => __('All posts'),
            self::Format => __('Format'),
            self::Offer => __('Offers'),
            self::Platform => __('Platform'),
            self::Series => __('Series'),
            self::ProductFamily => __('Product family'),
        };
    }

    /** Dimensions that need a reference (which format, which platform...). */
    public function needsRef(): bool
    {
        return in_array($this, [self::Format, self::Platform, self::Series, self::ProductFamily], true);
    }
}
