<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['key' => 'tiktok', 'name' => 'TikTok', 'short' => 'TT', 'open_url' => 'https://www.tiktok.com/'],
            ['key' => 'instagram', 'name' => 'Instagram', 'short' => 'IG', 'open_url' => 'https://www.instagram.com/'],
            ['key' => 'facebook', 'name' => 'Facebook', 'short' => 'FB', 'open_url' => 'https://www.facebook.com/'],
            ['key' => 'snapchat', 'name' => 'Snapchat', 'short' => 'SC', 'open_url' => 'https://www.snapchat.com/'],
        ];

        foreach ($platforms as $position => $platform) {
            Platform::updateOrCreate(['key' => $platform['key']], $platform + ['position' => $position]);
        }
    }
}
