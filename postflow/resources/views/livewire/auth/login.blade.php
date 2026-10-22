<div>
    <div class="flex items-center justify-center gap-2 mb-8">
        <span class="size-10 rounded-xl bg-primary-ink grid place-items-center font-display font-bold text-xl text-white" aria-hidden="true">P</span>
        <span class="font-display font-bold text-3xl">PostFlow</span>
    </div>

    <form wire:submit="login" class="card p-6 space-y-4">
        <h1 class="font-display font-semibold text-xl">{{ __('Log in') }}</h1>

        <div>
            <label for="email" class="label">{{ __('Email') }}</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" required autofocus class="input" aria-describedby="email-error">
            @error('email')<p id="email-error" class="mt-1 text-sm text-ink"><x-icon name="alert" class="size-4 inline text-primary" /> {{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="label">{{ __('Password') }}</label>
            <input wire:model="password" id="password" type="password" autocomplete="current-password" required class="input">
        </div>

        <label class="flex items-center gap-2 text-[15px]">
            <input wire:model="remember" type="checkbox" class="checkbox"> {{ __('Remember me') }}
        </label>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">{{ __('Log in') }}</button>
    </form>

    <p class="mt-6 text-center text-muted">
        @foreach (\App\Http\Middleware\SetLocale::LOCALES as $locale)
            <a href="{{ route('locale', $locale) }}" lang="{{ $locale }}" @class(['px-2 uppercase font-semibold', 'text-ink underline' => app()->getLocale() === $locale])>{{ $locale }}</a>
        @endforeach
    </p>
</div>
