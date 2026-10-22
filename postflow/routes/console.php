<?php

use Illuminate\Support\Facades\Schedule;

// Reminders need ONE cron entry on the server:
// * * * * * cd /path/to/postflow && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('postflow:remind')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('postflow:digest')->dailyAt('08:30');
