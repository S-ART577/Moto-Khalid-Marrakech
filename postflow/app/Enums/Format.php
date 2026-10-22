<?php

namespace App\Enums;

enum Format: string
{
    case Video = 'video';
    case Static = 'static';

    public function label(): string
    {
        return match ($this) {
            self::Video => __('Video'),
            self::Static => __('Static'),
        };
    }

    /** Placements offered for this format, per platform. */
    public function placements(string $platformKey): array
    {
        return match ($platformKey) {
            'tiktok' => $this === self::Video ? ['video'] : ['photo'],
            'instagram' => $this === self::Video ? ['reel', 'story'] : ['post', 'carousel', 'story'],
            'facebook' => $this === self::Video ? ['reel', 'video', 'story'] : ['post', 'carousel', 'story'],
            'snapchat' => $this === self::Video ? ['spotlight', 'story'] : ['story'],
            default => ['post'],
        };
    }

    public function defaultRatio(string $placement): string
    {
        return match ($placement) {
            'post', 'carousel' => '4:5',
            default => '9:16',
        };
    }
}
