@php
    $report = $this->report;
    $warnings = $report->warnings();
    $month = $this->monthStart;
    $weeks = $report->weeksOf($month);
    $showBrand = $this->brands->count() > 1;
    $levels = [
        'urgent' => ['label' => __('Now'), 'class' => 'bg-primary-ink text-white'],
        'soon' => ['label' => __('This week'), 'class' => 'bg-light text-navy'],
        'gap' => ['label' => __('Gap'), 'class' => 'bg-raised text-ink'],
        'info' => ['label' => __('Season'), 'class' => 'bg-raised text-ink'],
    ];
@endphp
<div>
    <x-page-header :title="__('Radar')" :subtitle="__('What is missing, compared with the targets you set in Brands.')">
        <x-slot:actions>
            <x-export-menu target="radar-sheet" :filename="'radar-'.$month->format('Y-m')" />
        </x-slot:actions>
    </x-page-header>
    <div id="radar-sheet" class="bg-bg">

    {{-- Needs attention --}}
    <section class="mb-8" aria-labelledby="attention-title">
        <h2 id="attention-title" class="font-display font-semibold text-xl mb-3">{{ __('Needs attention') }}</h2>
        @if (empty($warnings))
            <x-empty icon="check" :title="__('Nothing missing')" :text="__('Every target of the coming weeks is covered.')" />
        @else
            <ul class="space-y-2">
                @foreach ($warnings as $i => $warning)
                    <li wire:key="warning-{{ $i }}" @class(['card flex flex-wrap sm:flex-nowrap items-start gap-x-3 gap-y-2 p-3', 'border-primary' => $warning['level'] === 'urgent'])>
                        <span class="chip shrink-0 {{ $levels[$warning['level']]['class'] }}">
                            <x-icon :name="$warning['icon']" class="size-4" /> {{ $levels[$warning['level']]['label'] }}
                        </span>
                        <p class="basis-full sm:basis-auto order-last sm:order-none flex-1 min-w-0 pt-0.5" dir="auto">
                            @if ($showBrand && $warning['brand'])<span class="font-semibold">{{ $warning['brand']->name }}</span> · @endif{{ $warning['text'] }}
                        </p>
                        @if ($warning['post_id'])
                            <button type="button" x-on:click="$dispatch('open-post', { id: {{ $warning['post_id'] }} })" class="btn btn-ghost btn-sm shrink-0 ml-auto sm:ml-0">{{ __('Open') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Month navigation --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <button type="button" wire:click="go(-1)" class="btn btn-secondary px-2.5" title="{{ __('Previous') }}"><x-icon name="chevron-left" /><span class="sr-only">{{ __('Previous') }}</span></button>
        <h2 class="font-display font-semibold text-xl px-2 min-w-40 text-center">{{ ucfirst($month->isoFormat('MMMM YYYY')) }}</h2>
        <button type="button" wire:click="go(1)" class="btn btn-secondary px-2.5" title="{{ __('Next') }}"><x-icon name="chevron-right" /><span class="sr-only">{{ __('Next') }}</span></button>
    </div>

    <div class="space-y-6">
        @foreach ($this->brands as $brand)
            @php
                $grid = $report->weekGrid($brand, $month);
                $mix = $report->mix($brand, $month);
                $max = max(1, collect($mix)->max(fn ($row) => max($row['planned'], $row['target'] ?? 0)));
            @endphp
            <section wire:key="radar-brand-{{ $brand->id }}" class="card p-4 lg:p-6" aria-labelledby="radar-{{ $brand->id }}">
                <h3 id="radar-{{ $brand->id }}" class="font-display font-semibold text-lg flex items-center gap-2 mb-4">
                    <x-brand-mark :brand="$brand" size="size-8" /> {{ $brand->name }}
                </h3>

                <div class="grid gap-8 2xl:grid-cols-[3fr_2fr]">
                    {{-- Week by week --}}
                    <div class="min-w-0">
                        <h4 class="font-semibold mb-2">{{ __('Planned vs target, week by week') }}</h4>
                        @if (empty($grid))
                            <p class="text-muted">{{ __('No weekly target yet.') }} <a href="{{ route('brands.edit', $brand) }}#rhythm" class="underline">{{ __('Set targets') }}</a></p>
                        @else
                            {{-- Opens scrolled to the current week on small screens. --}}
                            <div class="relative overflow-x-auto" x-data x-init="let cell = $el.querySelector('[data-current]'); if (cell) $el.scrollLeft = cell.offsetLeft - 140">
                                <table class="table-pf min-w-[560px]">
                                    <caption class="sr-only">{{ __('Planned vs target, week by week') }} · {{ $brand->name }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="sticky left-0 bg-surface z-10">{{ __('Target per week') }}</th>
                                            @foreach ($weeks as $week)
                                                @php
                                                    $isCurrent = $week->isSameDay(now()->startOfWeek(\Carbon\CarbonInterface::MONDAY));
                                                @endphp
                                                <th scope="col" @if ($isCurrent) data-current aria-current="date" @endif @class(['text-center', 'text-accent' => $isCurrent])>{{ $week->isoFormat('D MMM') }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($grid as $row)
                                            <tr>
                                                <th scope="row" class="sticky left-0 bg-surface z-10 font-medium text-ink text-[15px]">{{ $row['label'] }}</th>
                                                @foreach ($row['cells'] as $cell)
                                                    @php
                                                        $short = $cell['target'] - $cell['planned'];
                                                    @endphp
                                                    <td class="text-center">
                                                        <span @class([
                                                            'inline-flex items-center justify-center gap-1 min-w-16 rounded-md px-2 py-1 tabular-nums font-semibold',
                                                            'bg-light text-navy' => $short <= 0,
                                                            'border-2 border-primary text-ink' => $short > 0 && ! $cell['past'],
                                                            'border border-line text-muted' => $short > 0 && $cell['past'],
                                                        ]) title="{{ __(':planned planned, target :target', ['planned' => $cell['planned'], 'target' => $cell['target']]) }}">
                                                            @if ($short <= 0)<x-icon name="check" class="size-3.5" />@endif
                                                            {{ $cell['planned'] }}/{{ $cell['target'] }}
                                                        </span>
                                                        @if ($short > 0)<span class="sr-only">{{ $cell['past'] ? __('missed') : __('missing') }}</span>@endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-sm text-muted flex flex-wrap gap-x-4 gap-y-1">
                                <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-light" aria-hidden="true"></span>{{ __('Target met') }}</span>
                                <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm border-2 border-primary" aria-hidden="true"></span>{{ __('Still missing') }}</span>
                                <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm border border-line" aria-hidden="true"></span>{{ __('Past week, missed') }}</span>
                            </p>
                        @endif
                    </div>

                    {{-- Content mix vs targets (bullet bars) --}}
                    <div class="min-w-0">
                        <h4 class="font-semibold">{{ __('Content mix this month') }}</h4>
                        <p class="text-sm text-muted mb-3 flex flex-wrap gap-x-4">
                            <span class="flex items-center gap-1.5"><span class="w-4 h-2.5 rounded-r bg-light" aria-hidden="true"></span>{{ __('Planned') }}</span>
                            <span class="flex items-center gap-1.5"><span class="w-0.5 h-3.5 bg-accent" aria-hidden="true"></span>{{ __('Target') }}</span>
                        </p>
                        <dl class="space-y-2.5">
                            @foreach ($mix as $row)
                                @php
                                    $plannedPct = $row['planned'] / $max * 100;
                                    $targetPct = $row['target'] !== null ? $row['target'] / $max * 100 : null;
                                    $tooltip = $row['target'] !== null
                                        ? __(':planned planned, target :target', ['planned' => $row['planned'], 'target' => $row['target']])
                                        : __(':planned planned, no target', ['planned' => $row['planned']]);
                                @endphp
                                @if ($loop->first || $mix[$loop->index - 1]['group'] !== $row['group'])
                                    <dt class="eyebrow text-muted pt-2">{{ $row['group'] }}</dt>
                                @endif
                                <dd class="grid grid-cols-[minmax(0,9rem)_1fr_3.5rem] items-center gap-3" title="{{ $tooltip }}">
                                    <span class="truncate text-[15px]">{{ $row['label'] }}</span>
                                    <span class="relative h-5 flex items-center" aria-hidden="true">
                                        <span class="absolute inset-x-0 h-px bg-line"></span>
                                        <span class="relative h-3 rounded-r-[4px] bg-light" style="width: {{ $plannedPct }}%"></span>
                                        @if ($targetPct !== null)
                                            <span class="absolute top-0 bottom-0 w-0.5 bg-accent" style="left: calc({{ $targetPct }}% - 1px)"></span>
                                        @endif
                                    </span>
                                    <span class="text-right tabular-nums text-[15px]">
                                        {{ $row['planned'] }}<span class="text-muted">{{ $row['target'] !== null ? '/'.$row['target'] : '' }}</span>
                                        <span class="sr-only">{{ $tooltip }}</span>
                                    </span>
                                </dd>
                            @endforeach
                        </dl>
                        <p class="text-sm text-muted mt-3">{{ __('Weekly targets are converted to the month (target × days ÷ 7).') }}</p>
                    </div>
                </div>
            </section>
        @endforeach
    </div>
    </div>
</div>
