<?php

namespace App\Enums;

enum VariantStatus: string
{
    case Planned = 'planned';
    case Posted = 'posted';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::Posted => __('Posted'),
            self::Skipped => __('Skipped'),
        };
    }
}
