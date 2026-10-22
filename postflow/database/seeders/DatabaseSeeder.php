<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate:fresh --seed
     *
     * Creates the four platforms, the Moroccan seasonal dates and the demo
     * workspace (GROSHOP October 2026 plan + a second brand, two users).
     */
    public function run(): void
    {
        $this->call([
            PlatformSeeder::class,
            SeasonalEventSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
