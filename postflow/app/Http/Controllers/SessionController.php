<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\BrandContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Small session actions: switch brand, switch language, log out. */
class SessionController extends Controller
{
    public function switchBrand(string $brand): RedirectResponse
    {
        BrandContext::make()->select($brand === 'all' ? null : (int) $brand);

        return back();
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::LOCALES, true), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return back();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
