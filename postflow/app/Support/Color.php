<?php

namespace App\Support;

/** WCAG contrast helpers, used to warn when a brand palette is hard to read. */
class Color
{
    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(fn ($c) => hexdec($c) / 255, str_split(strlen($hex) === 3 ? preg_replace('/(.)/', '$1$1', $hex) : $hex, 2));

        [$r, $g, $b] = array_map(
            fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Pairs the app relies on, with their contrast ratio.
     *
     * @return array<string, float>
     */
    public static function brandChecks(array $colors): array
    {
        return [
            __('Light text on background') => self::contrast($colors['color_light'], $colors['color_bg']),
            __('Light text on cards') => self::contrast($colors['color_light'], $colors['color_bg_2']),
            __('White text on red tiles') => self::contrast('#ffffff', $colors['color_primary_text']),
        ];
    }
}
