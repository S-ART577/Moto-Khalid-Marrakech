<div class="max-w-4xl">
    <x-page-header :title="__('Settings')" />

    <section class="card p-5 lg:p-6" aria-labelledby="profile-title">
        <h2 id="profile-title" class="font-display font-semibold text-lg mb-4">{{ __('My profile') }}</h2>
        <form wire:submit="saveProfile" class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="name">{{ __('Name') }}</label>
                <input id="name" wire:model="name" class="input" required>
                @error('name')<p class="mt-1 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="locale">{{ __('Language') }}</label>
                <select id="locale" wire:model="locale" class="select">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </select>
            </div>
            <fieldset class="sm:col-span-2">
                <legend class="label">{{ __('Reminders') }}</legend>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="notify_email" class="checkbox"> {{ __('Email') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="notify_push" class="checkbox"> {{ __('Browser / phone notification') }}</label>
                    <label class="flex items-center gap-2">
                        <input type="number" min="5" max="720" wire:model="reminder_minutes" class="input w-24"> {{ __('minutes before the publish time') }}
                    </label>
                </div>
                @if (config('webpush.public_key'))
                    <div class="mt-3" x-data="{ state: 'idle' }">
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="state = 'busy'; state = await window.pfEnablePush(@js(config('webpush.public_key')), @js(route('push.subscribe')))" x-bind:disabled="state === 'busy'">
                            <x-icon name="bell" class="size-4" /> {{ __('Enable notifications on this device') }}
                        </button>
                        <span class="ml-2 text-sm text-muted" x-show="state === 'on'" x-cloak>{{ __('Notifications are on for this device.') }}</span>
                        <span class="ml-2 text-sm text-muted" x-show="state === 'denied'" x-cloak>{{ __('Notifications are blocked in this browser\'s settings.') }}</span>
                        <span class="ml-2 text-sm text-muted" x-show="state === 'unsupported'" x-cloak>{{ __('This browser does not support notifications. On iPhone, first add PostFlow to the home screen.') }}</span>
                    </div>
                @endif
            </fieldset>
            <div>
                <label class="label" for="new_password">{{ __('New password') }}</label>
                <input id="new_password" type="password" wire:model="new_password" class="input" autocomplete="new-password" placeholder="{{ __('Leave empty to keep it') }}">
                @error('new_password')<p class="mt-1 text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <button class="btn btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </section>

    @can('admin')
        <section class="card p-5 lg:p-6 mt-6" aria-labelledby="team-title">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 id="team-title" class="font-display font-semibold text-lg">{{ __('Team') }}</h2>
                <button type="button" wire:click="newMember" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> {{ __('Add a person') }}</button>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($this->team as $person)
                    <li wire:key="user-{{ $person->id }}" class="py-3 flex flex-wrap items-center gap-3">
                        <x-avatar :user="$person" size="size-9" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $person->name }} <span class="text-muted font-normal">· {{ $person->role->label() }}</span></p>
                            <p class="text-sm text-muted truncate">{{ $person->email }} · {{ $person->isAdmin() ? __('All brands') : ($person->brands->pluck('name')->implode(', ') ?: __('No brand yet')) }}</p>
                        </div>
                        <button type="button" wire:click="editMember({{ $person->id }})" class="btn btn-ghost btn-sm">{{ __('Edit') }}</button>
                        @if ($person->id !== auth()->id())
                            <button type="button" wire:click="deleteMember({{ $person->id }})" wire:confirm="{{ __('Remove :name from the team?', ['name' => $person->name]) }}" class="btn btn-ghost btn-sm" title="{{ __('Remove') }}">
                                <x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Remove') }}</span>
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($editingUserId !== null)
                <form wire:submit="saveMember" class="mt-4 border-t border-line pt-4 grid gap-4 sm:grid-cols-2">
                    <h3 class="sm:col-span-2 font-display font-semibold">{{ $editingUserId ? __('Edit person') : __('Add a person') }}</h3>
                    <div>
                        <label class="label" for="m-name">{{ __('Name') }}</label>
                        <input id="m-name" wire:model="member.name" class="input" required>
                        @error('member.name')<p class="mt-1 text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="m-email">{{ __('Email') }}</label>
                        <input id="m-email" type="email" wire:model="member.email" class="input" required>
                        @error('member.email')<p class="mt-1 text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="m-role">{{ __('Role') }}</label>
                        <select id="m-role" wire:model="member.role" class="select">
                            @foreach (\App\Enums\Role::cases() as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @error('member.role')<p class="mt-1 text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="m-password">{{ __('Password') }}</label>
                        <input id="m-password" type="password" wire:model="member.password" class="input" autocomplete="new-password" @if ($editingUserId) placeholder="{{ __('Leave empty to keep it') }}" @endif>
                        @error('member.password')<p class="mt-1 text-sm">{{ $message }}</p>@enderror
                    </div>
                    <fieldset class="sm:col-span-2">
                        <legend class="label">{{ __('Brands this person works on') }}</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            @forelse ($this->brands as $brand)
                                <label class="flex items-center gap-2"><input type="checkbox" value="{{ $brand->id }}" wire:model="member.brands" class="checkbox"> {{ $brand->name }}</label>
                            @empty
                                <p class="text-muted">{{ __('No brand yet') }}</p>
                            @endforelse
                        </div>
                    </fieldset>
                    <div class="sm:col-span-2 flex gap-2">
                        <button class="btn btn-primary">{{ __('Save') }}</button>
                        <button type="button" wire:click="$set('editingUserId', null)" class="btn btn-ghost">{{ __('Cancel') }}</button>
                    </div>
                </form>
            @endif
        </section>
    @endcan
</div>
