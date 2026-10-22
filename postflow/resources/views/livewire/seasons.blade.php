@php
    $today = \Carbon\CarbonImmutable::today();
    $groups = $this->groups;
    $sections = ['soon' => __('Next 90 days'), 'later' => __('Later'), 'past' => __('Past')];
    // Deadline display: passed, within a week, or later.
    $deadline = function ($date) use ($today) {
        $days = (int) $today->diffInDays($date, false);

        return [
            'text' => ucfirst($date->isoFormat('ddd D MMM')),
            'when' => $days < 0 ? __('passed') : ($days === 0 ? __('today') : trans_choice('in :count day|in :count days', $days)),
            'state' => $days < 0 ? 'passed' : ($days <= 7 ? 'soon' : 'ok'),
        ];
    };
@endphp
<div class="max-w-5xl">
    <x-page-header :title="__('Seasons')" :subtitle="__('Moroccan and commercial dates, with the date to shoot and to design for each one.')">
        <x-slot:actions>
            @can('admin')
                <button type="button" wire:click="create" class="btn btn-primary"><x-icon name="plus" class="size-4" /> {{ __('Add a date') }}</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($editingId !== null)
        <form wire:submit="save" class="card p-5 mb-6 grid gap-4 sm:grid-cols-2" aria-labelledby="season-form-title">
            <h2 id="season-form-title" class="sm:col-span-2 font-display font-semibold text-lg">{{ $editingId ? __('Edit the date') : __('Add a date') }}</h2>
            @if ($errors->any())
                <ul class="sm:col-span-2 card border-primary p-3 list-disc pl-6" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
            <div>
                <label class="label" for="s-fr">{{ __('Name (French)') }}</label>
                <input id="s-fr" wire:model="form.name_fr" class="input" required>
            </div>
            <div>
                <label class="label" for="s-en">{{ __('Name (English)') }}</label>
                <input id="s-en" wire:model="form.name_en" class="input">
            </div>
            <div>
                <label class="label" for="s-start">{{ __('Start') }}</label>
                <input id="s-start" type="date" wire:model="form.starts_on" class="input" required>
            </div>
            <div>
                <label class="label" for="s-end">{{ __('End (optional)') }}</label>
                <input id="s-end" type="date" wire:model="form.ends_on" class="input">
            </div>
            <div>
                <label class="label" for="s-cat">{{ __('Type') }}</label>
                <select id="s-cat" wire:model="form.category" class="select">
                    @foreach (\App\Enums\EventCategory::cases() as $category)<option value="{{ $category->value }}">{{ $category->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="s-brand">{{ __('For') }}</label>
                <select id="s-brand" wire:model="form.brand_id" class="select">
                    <option value="">{{ __('All brands') }}</option>
                    @foreach (\App\Support\BrandContext::make()->brands() as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="s-shoot">{{ __('Shoot this many days before') }}</label>
                <input id="s-shoot" type="number" min="0" max="180" wire:model="form.shoot_lead_days" class="input">
            </div>
            <div>
                <label class="label" for="s-design">{{ __('Design this many days before') }}</label>
                <input id="s-design" type="number" min="0" max="180" wire:model="form.design_lead_days" class="input">
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="s-guidance">{{ __('What to do') }}</label>
                <textarea id="s-guidance" wire:model="form.guidance" class="textarea" rows="2" dir="auto" placeholder="{{ __('e.g. A greeting at most, keep offers light.') }}"></textarea>
            </div>
            <label class="sm:col-span-2 flex items-center gap-2"><input type="checkbox" wire:model="form.is_approximate" class="checkbox"> {{ __('Date to confirm (moon sighting)') }}</label>
            <div class="sm:col-span-2 flex flex-wrap gap-2">
                <button class="btn btn-primary">{{ __('Save') }}</button>
                <button type="button" wire:click="$set('editingId', null)" class="btn btn-ghost">{{ __('Cancel') }}</button>
                @if ($editingId)
                    <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="{{ __('Delete this date?') }}" class="btn btn-ghost ml-auto"><x-icon name="trash" class="size-4" /> {{ __('Delete') }}</button>
                @endif
            </div>
        </form>
    @endif

    @foreach ($sections as $key => $label)
        @continue($groups[$key]->isEmpty())
        <section class="mb-8" aria-labelledby="seasons-{{ $key }}" @if ($key === 'past') x-data="{ open: false }" @endif>
            <h2 id="seasons-{{ $key }}" class="font-display font-semibold text-xl mb-3">
                @if ($key === 'past')
                    <button type="button" class="flex items-center gap-2" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <x-icon name="chevron-right" class="size-5 transition-transform" x-bind:class="open && 'rotate-90'" /> {{ $label }}
                        <span class="text-muted font-normal">{{ $groups[$key]->count() }}</span>
                    </button>
                @else
                    {{ $label }}
                @endif
            </h2>
            <ul class="grid gap-3 md:grid-cols-2" @if ($key === 'past') x-show="open" x-cloak @endif>
                @foreach ($groups[$key] as $event)
                    @php
                        $days = $event->daysUntil();
                        $running = $event->covers($today);
                        $shoot = $deadline($event->shootBy());
                        $design = $deadline($event->designBy());
                    @endphp
                    <li wire:key="event-{{ $event->id }}" @class(['card p-4 flex gap-4', 'border-accent' => $running])>
                        <div class="w-20 shrink-0 text-center">
                            @if ($running)
                                <p class="font-display font-bold text-xl text-accent leading-tight">{{ __('Happening now') }}</p>
                            @elseif ($days >= 0)
                                <p class="font-display font-bold text-4xl tabular-nums leading-none">{{ $days }}</p>
                                <p class="text-sm text-muted">{{ trans_choice('day|days', $days) }}</p>
                            @else
                                <p class="text-sm text-muted">{{ __('Past') }}</p>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-display font-semibold text-lg">{{ $event->name() }}</h3>
                                <span class="chip bg-raised text-muted">{{ $event->category->label() }}</span>
                                @if ($event->is_approximate)<span class="chip border border-line text-muted" title="{{ __('Depends on the moon sighting') }}">{{ __('Date to confirm') }}</span>@endif
                                @if ($event->brand)<span class="chip bg-raised">{{ $event->brand->name }}</span>@endif
                            </div>
                            <p class="text-muted">
                                {{ ucfirst($event->starts_on->isoFormat('dddd D MMMM YYYY')) }}{{ $event->ends_on && ! $event->ends_on->isSameDay($event->starts_on) ? ' – '.$event->ends_on->isoFormat('D MMMM') : '' }}
                            </p>
                            @if ($key !== 'past')
                                <dl class="mt-2 grid grid-cols-2 gap-2 text-[15px]">
                                    @foreach ([[__('Shoot by'), $shoot], [__('Design by'), $design]] as [$what, $info])
                                        <div @class(['rounded-[10px] px-3 py-2', 'bg-raised' => $info['state'] === 'ok', 'border-2 border-primary' => $info['state'] === 'soon', 'border border-line text-muted line-through decoration-1' => $info['state'] === 'passed'])>
                                            <dt class="text-sm text-muted no-underline">{{ $what }}</dt>
                                            <dd class="font-semibold">{{ $info['text'] }} <span class="font-normal text-muted">· {{ $info['when'] }}</span></dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                            @if ($event->guidance)<p class="mt-2" dir="auto">{{ $event->guidance }}</p>@endif
                            @can('admin')
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @if ($key !== 'past')
                                        <button type="button" x-on:click="$dispatch('new-post', { date: '{{ $event->starts_on->toDateString() }}' })" class="btn btn-ghost btn-sm"><x-icon name="plus" class="size-4" /> {{ __('Plan a post') }}</button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $event->id }})" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> {{ __('Edit') }}</button>
                                </div>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
