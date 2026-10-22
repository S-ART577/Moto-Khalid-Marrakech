@php
    $canMove = auth()->user()->isAdmin();
    $brand = $this->brand;
    $showBrand = ! $brand;
    $today = \Carbon\CarbonImmutable::today();
    $anchor = $this->anchor;
    $events = $this->events;
    $options = $this->filterOptions;
    $title = $view === 'week'
        ? __('Week of :date', ['date' => $this->range[0]->isoFormat('D MMMM')])
        : ucfirst($anchor->isoFormat('MMMM'));
    $fileName = \Illuminate\Support\Str::slug(($brand?->name ?? 'postflow').'-'.$anchor->format('Y-m'));
    $hasFilters = $platform || $series || $owner || $stage;
@endphp
<div>
    {{-- Toolbar --}}
    <div class="no-print flex flex-wrap items-center gap-2 mb-4">
        <div class="flex items-center gap-1">
            <button type="button" wire:click="go('prev')" class="btn btn-secondary px-2.5" title="{{ __('Previous') }}"><x-icon name="chevron-left" /><span class="sr-only">{{ __('Previous') }}</span></button>
            <button type="button" wire:click="go('today')" class="btn btn-secondary">{{ __('Today') }}</button>
            <button type="button" wire:click="go('next')" class="btn btn-secondary px-2.5" title="{{ __('Next') }}"><x-icon name="chevron-right" /><span class="sr-only">{{ __('Next') }}</span></button>
        </div>

        <div class="flex rounded-[10px] border border-line p-0.5" role="group" aria-label="{{ __('View') }}">
            @foreach (['month' => ['grid', __('Month')], 'week' => ['columns', __('Week')], 'list' => ['list', __('List')]] as $key => [$icon, $label])
                <button type="button" wire:click="$set('view', '{{ $key }}')" aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                        @class(['btn btn-sm', 'bg-raised text-ink' => $view === $key, 'text-muted hover:text-ink' => $view !== $key])>
                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 ml-auto">
            @can('admin')
                <button type="button" wire:click="fillEmptySlots" wire:confirm="{{ __('Create a draft for every empty slot of your weekly rhythm, from today to the end of this month?') }}" class="btn btn-secondary">
                    <x-icon name="calendar-plus" class="size-4" /> {{ __('Fill empty slots') }}
                </button>
                <button type="button" x-on:click="$dispatch('new-post', { date: @js($anchor->isSameMonth($today) ? $today->toDateString() : $anchor->startOfMonth()->toDateString()) })" class="btn btn-primary">
                    <x-icon name="plus" class="size-4" /> {{ __('New post') }}
                </button>
            @endcan
            <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
                <button type="button" class="btn btn-secondary" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true">
                    <x-icon name="download" class="size-4" /> {{ __('Export') }}
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 z-30 mt-1 w-56 card p-1 shadow-xl">
                    <button type="button" class="w-full text-left rounded-[8px] px-3 py-2.5 hover:bg-light/5 flex items-center gap-2"
                            x-on:click="open = false; window.pfExportPng(document.getElementById('calendar-sheet'), @js($fileName.'.png'))">
                        <x-icon name="image" class="size-4" /> {{ __('Image (PNG)') }}
                    </button>
                    <button type="button" class="w-full text-left rounded-[8px] px-3 py-2.5 hover:bg-light/5 flex items-center gap-2" x-on:click="open = false; window.print()">
                        <x-icon name="printer" class="size-4" /> {{ __('PDF (print)') }}
                    </button>
                    @if (Route::has('export.posts'))
                        <a href="{{ route('export.posts', ['from' => $this->range[0]->toDateString(), 'to' => $this->range[1]->toDateString(), 'platform' => $platform, 'series' => $series, 'owner' => $owner, 'stage' => $stage]) }}"
                           class="w-full rounded-[8px] px-3 py-2.5 hover:bg-light/5 flex items-center gap-2"><x-icon name="list" class="size-4" /> {{ __('Spreadsheet (CSV)') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="no-print grid grid-cols-2 sm:grid-cols-4 lg:flex lg:flex-wrap items-center gap-2 mb-5" aria-label="{{ __('Filters') }}">
        <select wire:model.live="platform" class="select lg:w-44" aria-label="{{ __('Platform') }}">
            <option value="">{{ __('All platforms') }}</option>
            @foreach ($options['platforms'] as $option)<option value="{{ $option->key }}">{{ $option->name }}</option>@endforeach
        </select>
        <select wire:model.live="series" class="select lg:w-56" aria-label="{{ __('Series') }}">
            <option value="">{{ __('All series') }}</option>
            @foreach ($options['series'] as $option)<option value="{{ $option->id }}">{{ $showBrand ? $option->brand->name.' · ' : '' }}{{ $option->name }}</option>@endforeach
        </select>
        <select wire:model.live="owner" class="select lg:w-48" aria-label="{{ __('Owner') }}">
            <option value="">{{ __('Everyone') }}</option>
            @foreach ($options['owners'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
        </select>
        <select wire:model.live="stage" class="select lg:w-44" aria-label="{{ __('Stage') }}">
            <option value="">{{ __('All stages') }}</option>
            @foreach (\App\Enums\Stage::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
        </select>
        @if ($hasFilters)
            <button type="button" wire:click="clearFilters" class="btn btn-ghost btn-sm col-span-2 sm:col-span-1"><x-icon name="x" class="size-4" /> {{ __('Clear filters') }}</button>
        @endif
    </div>

    {{-- The sheet: this is what the PNG / PDF export captures. --}}
    <div id="calendar-sheet" class="print-area rounded-[12px] lg:p-6 lg:-mx-2 bg-bg">
        <header class="flex flex-wrap items-end justify-between gap-4 mb-5">
            <div>
                <p class="eyebrow text-muted">
                    {{ __('Content calendar') }} · {{ ($brand ? $brand->platforms : $options['platforms'])->pluck('name')->map(fn ($n) => mb_strtoupper($n))->implode(' · ') }}
                </p>
                <h1 @class(['font-display font-bold leading-tight mt-1', 'text-4xl lg:text-6xl' => $view !== 'week', 'text-3xl lg:text-4xl' => $view === 'week'])>
                    {{ $title }} <span class="text-primary">{{ $anchor->format('Y') }}</span>
                </h1>
                <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1">
                    @foreach (['posts' => 'post|posts', 'videos' => 'video|videos', 'static' => 'static|static', 'offers' => 'offer|offers'] as $key => $label)
                        <div class="flex items-baseline gap-1.5">
                            <dd class="font-display font-bold text-3xl tabular-nums">{{ $this->stats[$key] }}</dd>
                            <dt class="eyebrow text-muted">{{ trans_choice($label, $this->stats[$key]) }}</dt>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="text-right ml-auto">
                @if ($brand)
                    <p class="flex items-center justify-end gap-2 font-display font-bold text-xl"><x-brand-mark :brand="$brand" size="size-9" /> {{ $brand->name }}</p>
                    @if ($brand->postingSlots->count())
                        <p class="text-muted mt-1">{{ trans_choice(':count post a week|:count posts a week', $brand->postingSlots->count()) }}</p>
                        <p class="text-muted">{{ $brand->rhythmLabel() }}</p>
                    @endif
                @else
                    <p class="text-muted">{{ __('All brands') }}</p>
                @endif
            </div>
        </header>

        @if ($view === 'month')
            {{-- Month grid (tablet and desktop) --}}
            <div class="hidden md:block">
                <div class="grid grid-cols-7 gap-1.5 mb-1.5" aria-hidden="true">
                    @foreach (array_slice($this->days, 0, 7) as $day)
                        <div class="eyebrow text-muted px-1">{{ mb_strtoupper(rtrim($day->isoFormat('ddd'), '.')) }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1.5">
                    @foreach ($this->days as $day)
                        @php
                            $key = $day->toDateString();
                            $inMonth = $day->isSameMonth($anchor);
                            $dayPosts = $this->postsByDay[$key] ?? collect();
                            $dayEvents = $events->filter(fn ($event) => $event->covers($day));
                            // Event name on its first day, and again at the start of each week row.
                            $startingEvents = $dayEvents->filter(fn ($event) => $event->starts_on->isSameDay($day) || $day->isoWeekday() === 1);
                        @endphp
                        <div wire:key="day-{{ $key }}" @class(['group relative flex flex-col min-h-32 rounded-[10px] border border-line overflow-hidden', 'bg-surface' => $inMonth, 'bg-surface/40' => ! $inMonth])
                             aria-label="{{ $day->isoFormat('dddd D MMMM') }}">
                            <div class="flex items-center justify-between px-2 pt-1">
                                <span @class(['font-display font-bold text-lg tabular-nums', 'text-muted/60' => ! $inMonth, 'text-ink' => $inMonth && ! $day->isSameDay($today), 'text-accent underline decoration-2 underline-offset-4' => $day->isSameDay($today)])>
                                    {{ $day->format('d') }}<span class="sr-only">{{ $day->isSameDay($today) ? ' ('.__('Today').')' : '' }}</span>
                                </span>
                                @if ($canMove)
                                    <button type="button" x-on:click="$dispatch('new-post', { date: '{{ $key }}' })" class="no-print size-8 grid place-items-center rounded-md text-muted hover:text-ink hover:bg-light/10 opacity-0 group-hover:opacity-100 focus:opacity-100" title="{{ __('New post on :date', ['date' => $day->isoFormat('D MMM')]) }}">
                                        <x-icon name="plus" class="size-4" /><span class="sr-only">{{ __('New post on :date', ['date' => $day->isoFormat('D MMM')]) }}</span>
                                    </button>
                                @endif
                            </div>
                            <ul class="flex-1 flex flex-col gap-1 p-1.5 min-h-12"
                                @if ($canMove) wire:sort="handleSort" wire:sort:config="{ forceFallback: true, fallbackTolerance: 4 }" wire:sort:group="calendar" wire:sort:group-id="{{ $key }}" @endif>
                                @foreach ($dayPosts as $post)
                                    <li wire:key="post-{{ $post->id }}" @if ($canMove) wire:sort:item="{{ $post->id }}" @endif class="flex-1 min-h-20">
                                        <x-post-tile :post="$post" :show-brand="$showBrand" />
                                    </li>
                                @endforeach
                            </ul>
                            @foreach ($dayEvents as $event)
                                @if ($startingEvents->contains($event))
                                    <p class="px-2 pb-1 text-[12px] font-semibold text-muted truncate" title="{{ $event->name() }}">{{ $event->name() }}</p>
                                @endif
                            @endforeach
                            @if ($dayEvents->isNotEmpty())
                                <span class="absolute inset-x-0 bottom-0 h-1 bg-accent" aria-hidden="true"></span>
                                <span class="sr-only">{{ $dayEvents->map->name()->implode(', ') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Agenda (phone) --}}
            <ol class="md:hidden space-y-3">
                @php
                    // Days of the month with a post, an event, or today.
                    $agendaDays = collect($this->days)->filter(fn ($day) => $day->isSameMonth($anchor)
                        && (isset($this->postsByDay[$day->toDateString()]) || $events->contains(fn ($e) => $e->covers($day)) || $day->isSameDay($today)));
                @endphp
                @forelse ($agendaDays as $day)
                    @php
                        $dayPosts = $this->postsByDay[$day->toDateString()] ?? collect();
                    @endphp
                    <li wire:key="agenda-{{ $day->toDateString() }}" class="flex gap-3">
                        <div class="w-12 shrink-0 text-center">
                            <p class="eyebrow text-muted">{{ rtrim($day->isoFormat('ddd'), '.') }}</p>
                            <p @class(['font-display font-bold text-2xl', 'text-accent' => $day->isSameDay($today)])>{{ $day->format('d') }}</p>
                        </div>
                        <div class="flex-1 min-w-0 space-y-2 pb-3 border-b border-line">
                            @foreach ($events->filter(fn ($e) => $e->covers($day)) as $event)
                                <p class="text-sm text-muted flex items-center gap-2"><span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $event->name() }}</p>
                            @endforeach
                            @foreach ($dayPosts as $post)
                                <div wire:key="agenda-post-{{ $post->id }}" class="min-h-20"><x-post-tile :post="$post" :show-brand="$showBrand" /></div>
                            @endforeach
                            @if ($dayPosts->isEmpty())<p class="text-muted text-sm">{{ __('Nothing planned') }}</p>@endif
                        </div>
                    </li>
                @empty
                    <li><x-empty icon="calendar" :title="__('Nothing planned this month')" /></li>
                @endforelse
            </ol>
        @elseif ($view === 'week')
            <div class="grid gap-3 md:grid-cols-7">
                @foreach ($this->days as $day)
                    @php
                        $key = $day->toDateString();
                        $dayPosts = $this->postsByDay[$key] ?? collect();
                        $dayEvents = $events->filter(fn ($event) => $event->covers($day));
                    @endphp
                    <section wire:key="week-{{ $key }}" class="flex flex-col min-w-0" aria-label="{{ $day->isoFormat('dddd D MMMM') }}">
                        <div @class(['flex items-baseline gap-2 px-1 pb-2 border-b-2', 'border-accent' => $dayEvents->isNotEmpty(), 'border-transparent' => $dayEvents->isEmpty()])>
                            <span class="eyebrow text-muted">{{ rtrim($day->isoFormat('ddd'), '.') }}</span>
                            <span @class(['font-display font-bold text-xl', 'text-accent' => $day->isSameDay($today)])>{{ $day->format('d') }}</span>
                            @if ($canMove)
                                <button type="button" x-on:click="$dispatch('new-post', { date: '{{ $key }}' })" class="no-print ml-auto btn btn-ghost btn-sm px-1.5" title="{{ __('New post on :date', ['date' => $day->isoFormat('D MMM')]) }}"><x-icon name="plus" class="size-4" /><span class="sr-only">{{ __('New post on :date', ['date' => $day->isoFormat('D MMM')]) }}</span></button>
                            @endif
                        </div>
                        @foreach ($dayEvents as $event)
                            <p class="px-1 pt-1 text-sm text-muted truncate">{{ $event->name() }}</p>
                        @endforeach
                        <ul class="flex-1 flex flex-col gap-2 pt-2 min-h-24"
                            @if ($canMove) wire:sort="handleSort" wire:sort:config="{ forceFallback: true, fallbackTolerance: 4 }" wire:sort:group="calendar" wire:sort:group-id="{{ $key }}" @endif>
                            @foreach ($dayPosts as $post)
                                <li wire:key="wpost-{{ $post->id }}" @if ($canMove) wire:sort:item="{{ $post->id }}" @endif>
                                    <x-post-card :post="$post" :show-brand="$showBrand" />
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @else
            {{-- List --}}
            @if ($this->posts->isEmpty())
                <x-empty icon="list" :title="__('Nothing planned this month')" />
            @else
                <div class="card relative overflow-x-auto hidden md:block">
                    <table class="table-pf">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Date') }}</th>
                                @if ($showBrand)<th scope="col">{{ __('Brand') }}</th>@endif
                                <th scope="col">{{ __('Post') }}</th>
                                <th scope="col">{{ __('Format') }}</th>
                                <th scope="col">{{ __('Platforms') }}</th>
                                <th scope="col">{{ __('Owner') }}</th>
                                <th scope="col">{{ __('Stage') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->posts as $post)
                                <tr wire:key="row-{{ $post->id }}" class="hover:bg-light/5 cursor-pointer" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })">
                                    <td class="whitespace-nowrap">
                                        <span class="font-medium">{{ ucfirst($post->planned_at->isoFormat('ddd D MMM')) }}</span>
                                        <span class="text-muted tabular-nums">{{ $post->planned_at->format('H:i') }}</span>
                                    </td>
                                    @if ($showBrand)<td>{{ $post->brand->name }}</td>@endif
                                    <td>
                                        <button type="button" class="font-medium text-left hover:underline" dir="auto">{{ $post->title }}</button>
                                        <span class="block text-sm text-muted">{{ $post->series?->name }}{{ $post->productFamily ? ' · '.$post->productFamily->name : '' }}</span>
                                    </td>
                                    <td><x-kind-chip :post="$post" /></td>
                                    <td><span class="flex flex-wrap gap-1">@foreach ($post->variants as $variant)<x-platform-chip :platform="$variant->platform" :status="$variant->status" />@endforeach</span></td>
                                    <td class="whitespace-nowrap">{{ $post->owner?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $post->stage->label() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="md:hidden space-y-2">
                    @foreach ($this->posts as $post)
                        <li wire:key="mrow-{{ $post->id }}">
                            <p class="text-sm text-muted mb-1">{{ ucfirst($post->planned_at->isoFormat('dddd D MMMM')) }}</p>
                            <x-post-card :post="$post" :show-brand="$showBrand" />
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        {{-- Legend --}}
        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-[15px] text-muted">
            <span class="flex items-center gap-2"><span class="size-4 rounded kind-video" aria-hidden="true"></span>{{ __('Video') }}</span>
            <span class="flex items-center gap-2"><span class="size-4 rounded kind-static" aria-hidden="true"></span>{{ __('Static post') }}</span>
            <span class="flex items-center gap-2"><span class="size-4 rounded kind-offer" aria-hidden="true"></span>{{ __('Offer post') }}</span>
            @foreach ($events as $event)
                <span class="flex items-center gap-2"><span class="w-6 h-1 rounded bg-accent" aria-hidden="true"></span>{{ $event->name() }}, {{ $event->starts_on->isoFormat('D MMM') }}{{ $event->ends_on && ! $event->ends_on->isSameDay($event->starts_on) ? ' – '.$event->ends_on->isoFormat('D MMM') : '' }}</span>
            @endforeach
        </div>

        {{-- Month notes (one brand selected) --}}
        @if ($brand && $view !== 'week')
            @php
                $note = $this->monthNote;
            @endphp
            @if ($editingNotes)
                <form wire:submit="saveNotes" class="no-print mt-5 grid gap-3 lg:grid-cols-3">
                    @foreach (['theme' => __('Theme'), 'offer' => __('Offer focus'), 'dates' => __('Key dates')] as $part => $label)
                        <fieldset class="card p-4 space-y-2">
                            <legend class="eyebrow text-muted px-1">{{ $label }}</legend>
                            <input wire:model="notes.{{ $part }}_title" class="input" dir="auto" aria-label="{{ $label }}: {{ __('title') }}" placeholder="{{ __('Title') }}">
                            <textarea wire:model="notes.{{ $part }}_body" class="textarea" rows="3" dir="auto" aria-label="{{ $label }}: {{ __('text') }}"></textarea>
                        </fieldset>
                    @endforeach
                    <div class="lg:col-span-3 flex gap-2">
                        <button class="btn btn-primary">{{ __('Save') }}</button>
                        <button type="button" wire:click="$set('editingNotes', false)" class="btn btn-ghost">{{ __('Cancel') }}</button>
                    </div>
                </form>
            @else
                <div class="mt-5 grid gap-3 lg:grid-cols-3">
                    @foreach (['theme' => __('Theme'), 'offer' => __('Offer focus'), 'dates' => __('Key dates')] as $part => $label)
                        <section class="card p-4">
                            <h2 class="eyebrow text-muted">{{ $label }}</h2>
                            <p class="font-display font-semibold mt-2" dir="auto">{{ $note?->{$part.'_title'} ?: '-' }}</p>
                            <p class="text-muted mt-1" dir="auto">{{ $note?->{$part.'_body'} }}</p>
                        </section>
                    @endforeach
                </div>
                @can('admin')
                    <button type="button" wire:click="editNotes" class="no-print btn btn-ghost btn-sm mt-2"><x-icon name="pencil" class="size-4" /> {{ __('Edit the month notes') }}</button>
                @endcan
            @endif
            @if ($brand->tagline || $brand->signature)
                <footer class="mt-5 flex flex-wrap justify-between gap-2 text-[15px] font-semibold">
                    <span dir="auto">{{ $brand->tagline }}</span>
                    <span class="text-muted" dir="auto">{{ $brand->signature }}</span>
                </footer>
            @endif
        @endif
    </div>

    {{-- Ideas without a date --}}
    @if ($this->ideas->isNotEmpty() || $canMove)
        <section class="no-print mt-8" aria-labelledby="ideas-title">
            <h2 id="ideas-title" class="font-display font-semibold text-lg">{{ __('Ideas without a date') }}</h2>
            <p class="text-muted mb-3">{{ $canMove ? __('Drag an idea onto a day to schedule it, or drag a post here to unschedule it.') : __('Ideas waiting for a date.') }}</p>
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 min-h-16 rounded-[12px] border border-dashed border-line p-2"
                @if ($canMove) wire:sort="handleSort" wire:sort:config="{ forceFallback: true, fallbackTolerance: 4 }" wire:sort:group="calendar" wire:sort:group-id="ideas" @endif>
                @foreach ($this->ideas as $post)
                    <li wire:key="idea-{{ $post->id }}" @if ($canMove) wire:sort:item="{{ $post->id }}" @endif class="min-h-20">
                        <x-post-tile :post="$post" :show-brand="$showBrand" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
