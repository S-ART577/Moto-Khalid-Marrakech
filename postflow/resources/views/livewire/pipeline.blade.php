@php
    $user = auth()->user();
    $showBrand = \App\Support\BrandContext::make()->current() === null;
    $options = $this->filterOptions;
    $canAdvance = function ($post) use ($user) {
        $next = \App\Enums\Stage::cases()[$post->stage->position() + 1] ?? null;

        return $next && \Illuminate\Support\Facades\Gate::forUser($user)->allows('changeStage', [$post, $next]);
    };
@endphp
<div>
    <x-page-header :title="__('Pipeline')" :subtitle="__('From brief to posted. Drag a card to another column or tap the arrow.')">
        <x-slot:actions>
            <div class="flex rounded-[10px] border border-line p-0.5" role="group" aria-label="{{ __('View') }}">
                @foreach (['board' => ['kanban', __('Board')], 'list' => ['list', __('List')]] as $key => [$icon, $label])
                    <button type="button" wire:click="$set('view', '{{ $key }}')" aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                            @class(['btn btn-sm', 'bg-raised text-ink' => $view === $key, 'text-muted hover:text-ink' => $view !== $key])>
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </button>
                @endforeach
            </div>
            <x-export-menu target="pipeline-sheet" filename="pipeline" :csv="route('export.posts', ['from' => now()->subMonths(3)->toDateString(), 'to' => now()->addMonths(3)->toDateString()])" />
            @can('admin')
                <button type="button" x-on:click="$dispatch('new-post', {})" class="btn btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New post') }}</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:flex lg:flex-wrap gap-2 mb-5" aria-label="{{ __('Filters') }}">
        <select wire:model.live="owner" class="select lg:w-48" aria-label="{{ __('Owner') }}">
            <option value="">{{ __('Everyone') }}</option>
            <option value="me">{{ __('Only mine') }}</option>
            @foreach ($options['owners'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
        </select>
        <select wire:model.live="series" class="select lg:w-56" aria-label="{{ __('Series') }}">
            <option value="">{{ __('All series') }}</option>
            @foreach ($options['series'] as $option)<option value="{{ $option->id }}">{{ $showBrand ? $option->brand->name.' · ' : '' }}{{ $option->name }}</option>@endforeach
        </select>
        <select wire:model.live="format" class="select lg:w-44" aria-label="{{ __('Format') }}">
            <option value="">{{ __('All formats') }}</option>
            <option value="video">{{ __('Video') }}</option>
            <option value="static">{{ __('Static') }}</option>
            <option value="offer">{{ __('Offer') }}</option>
        </select>
    </div>

    <div id="pipeline-sheet" class="bg-bg">
    @if ($view === 'board')
        <div class="-mx-4 lg:-mx-8 px-4 lg:px-8 relative overflow-x-auto pb-4 snap-x snap-mandatory lg:snap-none">
            <div class="grid grid-flow-col auto-cols-[minmax(260px,82vw)] sm:auto-cols-[minmax(260px,300px)] gap-3 items-start">
                @foreach (\App\Enums\Stage::cases() as $stage)
                    @php
                        $cards = $this->columns[$stage->value] ?? collect();
                    @endphp
                    <section wire:key="column-{{ $stage->value }}" class="snap-start rounded-[12px] bg-surface/50 border border-line flex flex-col max-h-[calc(100dvh-14rem)]" aria-labelledby="col-{{ $stage->value }}">
                        <header class="flex items-center gap-2 px-3 py-2.5 border-b border-line">
                            <h2 id="col-{{ $stage->value }}" class="font-display font-semibold">{{ $stage->label() }}</h2>
                            <span class="chip bg-raised text-muted tabular-nums">{{ $cards->count() }}</span>
                            @if ($stage === \App\Enums\Stage::Scheduled)
                                <span class="ml-auto text-sm text-muted" title="{{ __('Only the admin moves posts here') }}">{{ __('Approved') }}</span>
                            @endif
                        </header>
                        <ul class="flex-1 overflow-y-auto p-2 space-y-2 min-h-24"
                            wire:sort="handleSort" wire:sort:config="{ forceFallback: true, fallbackTolerance: 4 }" wire:sort:group="pipeline" wire:sort:group-id="{{ $stage->value }}">
                            @foreach ($cards as $post)
                                <li wire:key="card-{{ $post->id }}" wire:sort:item="{{ $post->id }}">
                                    <x-pipeline-card :post="$post" :show-brand="$showBrand" :can-advance="$canAdvance($post)" />
                                </li>
                            @endforeach
                        </ul>
                        @if ($stage === \App\Enums\Stage::Posted)
                            <p class="px-3 py-2 text-sm text-muted border-t border-line">{{ __('Last :count days', ['count' => \App\Livewire\Pipeline::POSTED_DAYS]) }}</p>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    @else
        @if ($this->posts->isEmpty())
            <x-empty icon="kanban" :title="__('Nothing in production')" />
        @else
            <div class="card relative overflow-x-auto">
                <table class="table-pf min-w-[760px]">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Post') }}</th>
                            <th scope="col">{{ __('Publish date') }}</th>
                            <th scope="col">{{ __('Owner') }}</th>
                            <th scope="col">{{ __('Production due') }}</th>
                            <th scope="col">{{ __('Checklist') }}</th>
                            <th scope="col">{{ __('Stage') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->posts as $post)
                            @php
                                [$done, $total] = $post->checklistProgress();
                                $late = $post->isProductionLate();
                            @endphp
                            <tr wire:key="prow-{{ $post->id }}" class="hover:bg-light/5">
                                <td>
                                    <button type="button" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })" class="text-left">
                                        <span class="flex items-center gap-2"><x-kind-chip :post="$post" /> <span class="font-medium hover:underline" dir="auto">{{ $post->title }}</span></span>
                                        <span class="block text-sm text-muted mt-0.5">{{ $showBrand ? $post->brand->name.' · ' : '' }}{{ $post->series?->name }}</span>
                                    </button>
                                </td>
                                <td class="whitespace-nowrap">{{ $post->planned_at ? ucfirst($post->planned_at->isoFormat('ddd D MMM, HH:mm')) : __('No date') }}</td>
                                <td class="whitespace-nowrap">{{ $post->owner?->name ?? '-' }}</td>
                                <td @class(['whitespace-nowrap', 'font-semibold' => $late])>
                                    @if ($late)<x-icon name="alert" class="size-4 inline text-primary" /> {{ __('Late') }} ·@endif
                                    {{ $post->due_on?->isoFormat('ddd D MMM') ?? '-' }}
                                </td>
                                <td class="tabular-nums">{{ $total ? $done.'/'.$total : '-' }}</td>
                                <td>
                                    <select wire:change="setStage({{ $post->id }}, $event.target.value)" class="select min-w-36" aria-label="{{ __('Stage of :title', ['title' => $post->title]) }}">
                                        @foreach (\App\Enums\Stage::cases() as $stage)
                                            <option value="{{ $stage->value }}" @selected($post->stage === $stage)
                                                @disabled($post->stage !== $stage && \Illuminate\Support\Facades\Gate::denies('changeStage', [$post, $stage]))>{{ $stage->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
    </div>
</div>
