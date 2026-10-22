<?php

use App\Http\Controllers\SessionController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
});

Route::get('/locale/{locale}', [SessionController::class, 'switchLocale'])->name('locale');

Route::middleware('auth')->group(function () {
    // Home: the admin lands on the calendar, the community manager on Today.
    Route::get('/', function () {
        $target = auth()->user()->isAdmin() ? 'calendar' : 'today';

        return redirect()->route(Route::has($target) ? $target : 'settings');
    })->name('home');

    Route::get('/brand/{brand}', [SessionController::class, 'switchBrand'])->name('brand.switch');
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');

    Route::livewire('/calendar', Livewire\Calendar::class)->name('calendar');
    Route::livewire('/settings', Livewire\Settings::class)->name('settings');

    Route::middleware('can:admin')->group(function () {
        Route::livewire('/brands', Livewire\Brands\Index::class)->name('brands.index');
        Route::livewire('/brands/create', Livewire\Brands\Edit::class)->name('brands.create');
        Route::livewire('/brands/{brand}', Livewire\Brands\Edit::class)->name('brands.edit');
    });
});
