<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case CommunityManager = 'community_manager';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::CommunityManager => __('Community manager'),
        };
    }
}
