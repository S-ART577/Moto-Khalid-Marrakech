<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Creates the key pair browsers need for push notifications. Run once per installation. */
class GenerateVapidKeys extends Command
{
    protected $signature = 'postflow:vapid {--write : Save the keys in .env}';

    protected $description = 'Generate the VAPID keys for browser / phone notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $lines = 'VAPID_PUBLIC_KEY='.$keys['publicKey']."\nVAPID_PRIVATE_KEY=".$keys['privateKey'];

        if (! $this->option('write')) {
            $this->line('Add these lines to .env:');
            $this->line($lines);

            return self::SUCCESS;
        }

        $path = base_path('.env');
        $env = file_get_contents($path);
        $env = preg_replace('/^VAPID_(PUBLIC|PRIVATE)_KEY=.*\n?/m', '', $env);
        file_put_contents($path, rtrim($env)."\n".$lines."\n");
        $this->info('Keys saved in .env. Run php artisan config:clear if you cache the config.');

        return self::SUCCESS;
    }
}
