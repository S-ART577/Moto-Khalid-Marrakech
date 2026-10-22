@php
    $context = \App\Support\BrandContext::make();
    $colors = $context->colors();
    $currentBrand = $context->current();
    $user = auth()->user();

    // Main navigation. A screen only appears once its route exists.
    $nav = collect([
        ['route' => 'today', 'label' => __('Today'), 'icon' => 'today'],
        ['route' => 'calendar', 'label' => __('Calendar'), 'icon' => 'calendar'],
        ['route' => 'pipeline', 'label' => __('Pipeline'), 'icon' => 'kanban'],
        ['route' => 'radar', 'label' => __('Radar'), 'icon' => 'radar'],
        ['route' => 'seasons', 'label' => __('Seasons'), 'icon' => 'flag'],
        ['route' => 'performance', 'label' => __('Performance'), 'icon' => 'chart'],
        ['route' => 'import-export', 'label' => __('Import / export'), 'icon' => 'arrows'],
        ['route' => 'brands.index', 'label' => __('Brands'), 'icon' => 'layers', 'admin' => true],
        ['route' => 'settings', 'label' => __('Settings'), 'icon' => 'settings'],
    ])->filter(fn ($item) => Route::has($item['route']) && (! ($item['admin'] ?? false) || $user->isAdmin()));

    $mobileMain = $nav->take(4);
    $mobileMore = $nav->slice(4);
    $isActive = fn ($route) => request()->routeIs($route) || request()->routeIs($route.'.*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $colors['color_bg'] }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}PostFlow</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <style>
        :root {
            --pf-bg: {{ $colors['color_bg'] }};
            --pf-bg-2: {{ $colors['color_bg_2'] }};
            --pf-primary: {{ $colors['color_primary'] }};
            --pf-primary-text: {{ $colors['color_primary_text'] }};
            --pf-light: {{ $colors['color_light'] }};
            --pf-accent: {{ $colors['color_accent'] }};
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 btn btn-light">{{ __('Skip to content') }}</a>

<div class="lg:flex">
    {{-- Desktop sidebar --}}
    <aside class="no-print hidden lg:flex lg:flex-col w-60 shrink-0 h-dvh sticky top-0 border-r border-line px-3 py-5">
        <a href="{{ url('/') }}" class="flex items-center gap-2 px-3 mb-6">
            <span class="size-8 rounded-lg bg-primary-ink grid place-items-center font-display font-bold text-white" aria-hidden="true">P</span>
            <span class="font-display font-bold text-xl">PostFlow</span>
        </a>
        <nav aria-label="{{ __('Main') }}" class="flex-1 space-y-1">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   @class(['flex items-center gap-3 rounded-[10px] px-3 py-2.5 font-medium', 'bg-raised text-ink' => $isActive($item['route']), 'text-muted hover:text-ink hover:bg-light/5' => ! $isActive($item['route'])])
                   @if ($isActive($item['route'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-line pt-4 px-1 flex items-center gap-3">
            <x-avatar :user="$user" size="size-9" />
            <div class="min-w-0 flex-1">
                <p class="font-medium truncate">{{ $user->name }}</p>
                <p class="text-sm text-muted truncate">{{ $user->role->label() }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-ghost btn-sm px-2" title="{{ __('Log out') }}"><x-icon name="logout" /><span class="sr-only">{{ __('Log out') }}</span></button>
            </form>
        </div>
    </aside>

    <div class="flex-1 min-w-0">
        {{-- Top bar: brand switcher + language --}}
        <header class="no-print sticky top-0 z-30 border-b border-line bg-bg/95 backdrop-blur">
            <div class="flex items-center gap-2 px-3 lg:px-8 h-16">
                <a href="{{ url('/') }}" class="lg:hidden size-9 rounded-lg bg-primary-ink grid place-items-center font-display font-bold text-white shrink-0" aria-label="PostFlow">P</a>
                <nav aria-label="{{ __('Brands') }}" class="flex-1 min-w-0 relative overflow-x-auto [scrollbar-width:none]">
                    <ul class="flex items-center gap-1.5 w-max">
                        @if ($context->brands()->count() > 1)
                            <li>
                                <a href="{{ route('brand.switch', ['brand' => 'all']) }}"
                                   @class(['chip h-10 px-3 border', 'border-primary bg-raised text-ink' => ! $currentBrand, 'border-line text-muted hover:text-ink' => $currentBrand])
                                   @if (! $currentBrand) aria-current="true" @endif><span class="hidden sm:inline">{{ __('All brands') }}</span><span class="sm:hidden">{{ __('All') }}</span></a>
                            </li>
                        @endif
                        @foreach ($context->brands() as $brand)
                            <li>
                                <a href="{{ route('brand.switch', ['brand' => $brand->id]) }}"
                                   @class(['chip h-10 pl-1.5 pr-1.5 sm:pr-3 border', 'border-primary bg-raised text-ink' => $currentBrand?->is($brand), 'border-line text-muted hover:text-ink' => ! $currentBrand?->is($brand)])
                                   @if ($currentBrand?->is($brand)) aria-current="true" @endif title="{{ $brand->name }}">
                                    <x-brand-mark :brand="$brand" size="size-7" />
                                    {{-- On a phone only the logo shows, so every brand fits. --}}
                                    <span class="hidden sm:inline">{{ $brand->name }}</span><span class="sr-only sm:hidden">{{ $brand->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
                <div class="flex items-center gap-1 shrink-0">
                    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $locale)
                        <a href="{{ route('locale', $locale) }}" lang="{{ $locale }}"
                           @class(['btn btn-sm px-2 uppercase', 'btn-secondary' => app()->getLocale() === $locale, 'btn-ghost text-muted' => app()->getLocale() !== $locale])
                           @if (app()->getLocale() === $locale) aria-current="true" @endif>{{ $locale }}</a>
                    @endforeach
                </div>
            </div>
        </header>

        <main id="main" class="px-4 lg:px-8 pt-5 lg:pt-8 pb-28 lg:pb-10 max-w-[1500px]">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Mobile bottom navigation --}}
<nav aria-label="{{ __('Main') }}" class="no-print lg:hidden fixed bottom-0 inset-x-0 z-40 border-t border-line bg-bg pb-[env(safe-area-inset-bottom)]"
     x-data="{ more: false }" x-on:keydown.escape.window="more = false">
    <ul class="grid grid-cols-5">
        @foreach ($mobileMain as $item)
            <li>
                <a href="{{ route($item['route']) }}" @class(['flex flex-col items-center gap-0.5 py-2 text-[13px] font-medium', 'text-ink' => $isActive($item['route']), 'text-muted' => ! $isActive($item['route'])])
                   @if ($isActive($item['route'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" class="size-6" />
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
        <li>
            <button type="button" class="w-full flex flex-col items-center gap-0.5 py-2 text-[13px] font-medium text-muted" x-on:click="more = ! more" x-bind:aria-expanded="more" aria-controls="more-menu">
                <x-icon name="more" class="size-6" />
                {{ __('More') }}
            </button>
        </li>
    </ul>
    <div x-show="more" x-cloak class="fixed inset-0 -z-10 bg-navy/70" x-on:click="more = false" aria-hidden="true"></div>
    <div id="more-menu" x-show="more" x-cloak x-on:click.outside="more = false" class="absolute bottom-full inset-x-3 mb-2 card p-2 shadow-2xl">
        @foreach ($mobileMore as $item)
            <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-[10px] px-3 py-3 font-medium hover:bg-light/5">
                <x-icon :name="$item['icon']" /> {{ $item['label'] }}
            </a>
        @endforeach
        <form method="POST" action="{{ route('logout') }}" class="border-t border-line mt-1 pt-1">
            @csrf
            <button class="w-full flex items-center gap-3 rounded-[10px] px-3 py-3 font-medium hover:bg-light/5"><x-icon name="logout" /> {{ __('Log out') }} ({{ $user->name }})</button>
        </form>
    </div>
</nav>

@if (class_exists(\App\Livewire\PostPanel::class))
    <livewire:post-panel />
@endif

{{-- Toast messages: $this->dispatch('toast', message: '...') from any component --}}
<div class="no-print fixed z-50 bottom-24 lg:bottom-6 inset-x-0 flex flex-col items-center gap-2 pointer-events-none px-4"
     x-data="{
        toasts: [],
        add(message) {
            if (! message) return
            let id = Date.now() + Math.random()
            this.toasts.push({ id, message })
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 3500)
        },
     }"
     @if (session('toast')) x-init="add(@js(session('toast')))" @endif
     x-on:toast.window="add($event.detail.message)"
     role="status" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto rounded-[10px] bg-light text-navy font-medium px-4 py-3 shadow-xl" x-text="toast.message"></div>
    </template>
</div>

@livewireScripts
</body>
</html>
