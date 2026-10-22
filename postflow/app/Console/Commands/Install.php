<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\SeasonalEventSeeder;
use Illuminate\Console\Command;

/**
 * First start on a server: adds the platforms, the seasonal dates and the
 * GROSHOP plan with the two logins. Does nothing once someone exists, so it
 * is safe to run on every deploy.
 */
class Install extends Command
{
    protected $signature = 'postflow:install';

    protected $description = 'Fill an empty database with the starting data (safe to run again)';

    public function handle(): int
    {
        if (User::exists()) {
            $this->info('Already installed, nothing to do.');

            return self::SUCCESS;
        }

        foreach ([PlatformSeeder::class, SeasonalEventSeeder::class, DemoSeeder::class] as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $this->info('Installed. Log in with admin@postflow.test / password and change it in Settings.');

        return self::SUCCESS;
    }
}
