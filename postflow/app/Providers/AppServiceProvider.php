<?php

namespace App\Providers;

use App\Models\User;
use App\Support\BrandContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BrandContext::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Dates follow the interface language (lundi / Monday).
        $this->app['events']->listen(\Illuminate\Foundation\Events\LocaleUpdated::class, function ($event) {
            Carbon::setLocale($event->locale);
            CarbonImmutable::setLocale($event->locale);
        });
    }
}
