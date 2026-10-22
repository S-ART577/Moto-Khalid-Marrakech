@php
    $metrics = [
        'views' => __('Views'),
        'engagement' => __('Engagement'),
        'saves' => __('Saves'),
        'orders' => __('WhatsApp orders'),
    ];
    $fieldLabels = [
        'views' => __('Views'), 'likes' => __('Likes'), 'comments' => __('Comments'),
        'saves' => __('Saves'), 'shares' => __('Shares'), 'whatsapp_orders' => __('WhatsApp orders'),
    ];
    $showBrand = \App\Support\BrandContext::make()->current() === null;
@endphp
<div>
    <x-page-header :title="__('Performance')" :subtitle="__('Enter the numbers after posting, see what works, plan better next month.')">
        <x-slot:actions>
            <x-export-menu target="performance-sheet" filename="performance" :csv="route('export.metrics', ['month' => $month])" />
        </x-slot:actions>
    </x-page-header>

    <div role="tablist" class="flex gap-1 border-b border-line mb-6">
        <button type="button" role="tab" wire:click="$set('tab', 'leaderboard')" aria-selected="{{ $tab === 'leaderboard' ? 'true' : 'false' }}" class="tab">{{ __('What works') }}</button>
        <button type="button" role="tab" wire:click="$set('tab', 'enter')" aria-selected="{{ $tab === 'enter' ? 'true' : 'false' }}" class="tab">{{ __('Enter numbers') }}</button>
    </div>

    <div id="performance-sheet">
    @if ($tab === 'leaderboard')
        @php
            $board = $this->leaderboard;
            $groups = $board->groups($sort);
        @endphp
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <select wire:model.live="period" class="select w-auto" aria-label="{{ __('Period') }}">
                <option value="30">{{ __('Last 30 days') }}</option>
                <option value="90">{{ __('Last 90 days') }}</option>
                <option value="365">{{ __('Last 12 months') }}</option>
                <option value="all">{{ __('All time') }}</option>
            </select>
            <div class="flex flex-wrap rounded-[10px] border border-line p-0.5" role="group" aria-label="{{ __('Rank by') }}">
                @foreach ($metrics as $key => $label)
                    <button type="button" wire:click="$set('sort', '{{ $key }}')" aria-pressed="{{ $sort === $key ? 'true' : 'false' }}"
                            @class(['btn btn-sm', 'bg-raised text-ink' => $sort === $key, 'text-muted hover:text-ink' => $sort !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <span class="text-muted ml-auto">{{ trans_choice(':count platform post with numbers|:count platform posts with numbers', $board->count()) }}</span>
        </div>

        @if ($board->count() === 0)
            <x-empty icon="chart" :title="__('No numbers yet')" :text="__('Open “Enter numbers” after posting, or import a CSV.')" />
        @else
            {{-- The winner of each category --}}
            <ul class="grid gap-3 grid-cols-2 lg:grid-cols-5 mb-8">
                @foreach ($groups as $group)
                    @if ($group['rows'])
                        <li class="card p-4">
                            <p class="eyebrow text-muted">{{ $group['best'] }}</p>
                            <p class="font-display font-semibold text-lg mt-1 leading-tight" dir="auto">{{ $group['rows'][0]['label'] }}</p>
                            <p class="text-muted mt-1">{{ $metrics[$sort] }}: <span class="text-ink font-semibold">{{ \App\Support\Leaderboard::format($sort, $group['rows'][0]) }}</span></p>
                        </li>
                    @endif
                @endforeach
            </ul>

            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($groups as $group)
                    @php
                        $title = $group['title'];
                        $rows = $group['rows'];
                        $max = max(0.0001, collect($rows)->max(fn ($row) => \App\Support\Leaderboard::value($sort, $row)));
                    @endphp
                    <section class="card p-4 lg:p-5 min-w-0" aria-labelledby="lb-{{ $loop->index }}">
                        <h2 id="lb-{{ $loop->index }}" class="font-display font-semibold text-lg mb-3">{{ $title }}</h2>
                        <div class="relative overflow-x-auto">
                            <table class="table-pf min-w-[420px]">
                                <caption class="sr-only">{{ $title }} · {{ $metrics[$sort] }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ $title }}</th>
                                        <th scope="col" class="w-[45%]">{{ $sort === 'views' ? __('Average views') : $metrics[$sort] }}</th>
                                        <th scope="col" class="text-right">{{ __('Posts') }}</th>
                                        <th scope="col" class="text-right">{{ __('Orders') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <th scope="row" class="font-medium text-ink text-[15px]" dir="auto">{{ $row['label'] }}</th>
                                            <td>
                                                <span class="flex items-center gap-2">
                                                    <span class="flex-1 h-3" aria-hidden="true">
                                                        <span class="block h-full rounded-r-[4px] bg-light" style="width: {{ \App\Support\Leaderboard::value($sort, $row) / $max * 100 }}%"></span>
                                                    </span>
                                                    <span class="w-16 text-right tabular-nums whitespace-nowrap">{{ \App\Support\Leaderboard::format($sort, $row) }}</span>
                                                </span>
                                            </td>
                                            <td class="text-right tabular-nums">{{ $row['posts'] }}</td>
                                            <td class="text-right tabular-nums">{{ $row['orders'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach

                <section class="card p-4 lg:p-5 min-w-0" aria-labelledby="lb-top">
                    <h2 id="lb-top" class="font-display font-semibold text-lg mb-3">{{ __('Top posts') }}</h2>
                    <ol class="space-y-2">
                        @foreach ($board->topPosts($sort) as $variant)
                            <li class="grid grid-cols-[1.5rem_minmax(0,1fr)_auto] gap-x-3 items-start">
                                <span class="font-display font-bold tabular-nums">{{ $loop->iteration }}</span>
                                <span class="min-w-0">
                                    <button type="button" x-on:click="$dispatch('open-post', { id: {{ $variant->post_id }} })" class="text-left hover:underline line-clamp-2" dir="auto" title="{{ $variant->post->title }}">{{ $variant->post->title }}</button>
                                    <span class="block text-muted text-sm">{{ $showBrand ? $variant->post->brand->name.' · ' : '' }}{{ $variant->platform->name }} · {{ $variant->publish_at->isoFormat('D MMM') }}</span>
                                </span>
                                <span class="tabular-nums font-semibold whitespace-nowrap">{{ \App\Support\Leaderboard::format($sort, $board->single($variant)) }} <span class="font-normal text-muted">{{ mb_strtolower($metrics[$sort]) }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>
            <p class="text-muted text-sm mt-4">{{ __('Engagement = (likes + comments + saves + shares) ÷ views. WhatsApp orders are shown per post.') }}</p>
        @endif
    @else
        {{-- Enter numbers --}}
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button type="button" wire:click="goMonth(-1)" class="btn btn-secondary px-2.5" title="{{ __('Previous') }}"><x-icon name="chevron-left" /><span class="sr-only">{{ __('Previous') }}</span></button>
            <h2 class="font-display font-semibold text-xl px-2 min-w-40 text-center">{{ ucfirst($this->monthStart->isoFormat('MMMM YYYY')) }}</h2>
            <button type="button" wire:click="goMonth(1)" class="btn btn-secondary px-2.5" title="{{ __('Next') }}"><x-icon name="chevron-right" /><span class="sr-only">{{ __('Next') }}</span></button>
            @if (Route::has('export.metrics'))
                <a href="{{ route('export.metrics', ['month' => $month]) }}" class="btn btn-secondary ml-auto"><x-icon name="download" class="size-4" /> {{ __('Download this month\'s sheet') }}</a>
            @endif
        </div>

        @if ($this->rows->isEmpty())
            <x-empty icon="chart" :title="__('Nothing posted this month')" :text="__('Numbers can be entered once a platform is marked as posted.')" />
        @else
            <form wire:submit="saveMetrics">
                <div class="card relative overflow-x-auto">
                    <table class="table-pf min-w-[980px]">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Post') }}</th>
                                @foreach ($fieldLabels as $label)<th scope="col" class="text-right">{{ $label }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $variant)
                                <tr wire:key="m-{{ $variant->id }}">
                                    <td class="min-w-64">
                                        <span class="font-medium" dir="auto">{{ $variant->post->title }}</span>
                                        <span class="block text-sm text-muted">
                                            {{ ucfirst($variant->publish_at->isoFormat('ddd D MMM')) }} · {{ $variant->platform->name }}{{ $showBrand ? ' · '.$variant->post->brand->name : '' }}
                                            @if ($variant->post_url) · <a href="{{ $variant->post_url }}" target="_blank" rel="noopener" class="underline">{{ __('See the post') }}</a>@endif
                                        </span>
                                    </td>
                                    @foreach ($fieldLabels as $field => $label)
                                        <td class="text-right">
                                            <input type="number" min="0" inputmode="numeric" wire:model="values.{{ $variant->id }}.{{ $field }}"
                                                   class="input w-24 text-right tabular-nums px-2" aria-label="{{ $label }}, {{ $variant->platform->name }}, {{ $variant->post->title }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('values.*.*')<p class="mt-2"><x-icon name="alert" class="size-4 inline text-primary" /> {{ $message }}</p>@enderror
                <button class="btn btn-primary mt-3">{{ __('Save the numbers') }}</button>
            </form>
        @endif

        <section class="card p-5 mt-8 max-w-3xl" aria-labelledby="import-metrics-title">
            <h2 id="import-metrics-title" class="font-display font-semibold text-lg">{{ __('Import from a spreadsheet') }}</h2>
            <ol class="list-decimal pl-5 mt-2 space-y-1 text-[15px]">
                <li>{{ __('Download this month\'s sheet (button above) and open it in Google Sheets.') }}</li>
                <li>{{ __('Fill in the numbers. Keep the post_url column, or the date + platform + title columns.') }}</li>
                <li>{{ __('File > Download > CSV, then import it here.') }}</li>
            </ol>
            <form wire:submit="importCsv" class="mt-4 flex flex-wrap items-center gap-3">
                <label class="btn btn-secondary cursor-pointer">
                    <x-icon name="upload" class="size-4" /> {{ __('Choose the CSV file') }}
                    <input type="file" wire:model="file" accept=".csv,text/csv" class="sr-only">
                </label>
                @if ($file)<span class="text-muted">{{ $file->getClientOriginalName() }}</span>@endif
                <button class="btn btn-primary" wire:loading.attr="disabled" wire:target="file,importCsv"><x-icon name="upload" class="size-4" /> {{ __('Import') }}</button>
            </form>
            @error('file')<p class="mt-2"><x-icon name="alert" class="size-4 inline text-primary" /> {{ $message }}</p>@enderror
            @if ($importReport)
                <div class="mt-4" role="status">
                    <p class="font-semibold">{{ trans_choice(':count line imported|:count lines imported', $importReport['matched']) }}</p>
                    @if ($importReport['unmatched'])
                        <p class="text-muted mt-1">{{ trans_choice(':count line not found (check the link or the date + platform):|:count lines not found (check the link or the date + platform):', count($importReport['unmatched'])) }}</p>
                        <ul class="list-disc pl-5 text-muted text-[15px]">@foreach ($importReport['unmatched'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                    @endif
                </div>
            @endif
        </section>
    @endif
    </div>
</div>
