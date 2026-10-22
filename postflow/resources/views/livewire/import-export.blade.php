@php
    $labels = \App\Support\CalendarImporter::labels();
    $preview = $this->preview;
    $ok = collect($preview)->filter(fn ($line) => empty($line['errors']))->count();
    $withErrors = count($preview) - $ok;
@endphp
<div class="max-w-5xl">
    <x-page-header :title="__('Import / export')" :subtitle="__('Bring your Google Sheet calendar in, take any list out to a spreadsheet.')" />

    @can('admin')
        <section class="card p-5 lg:p-6 mb-6" aria-labelledby="import-title">
            <h2 id="import-title" class="font-display font-semibold text-xl">{{ __('Import your Google Sheet calendar') }}</h2>
            <ol class="list-decimal pl-5 mt-2 space-y-1 text-[15px] text-muted">
                <li>{{ __('In Google Sheets: File > Download > Comma-separated values (.csv).') }}</li>
                <li>{{ __('Upload it here and check which column is which.') }}</li>
                <li>{{ __('Import. Posts already in PostFlow (same brand, day and title) are skipped.') }}</li>
            </ol>
            <p class="mt-2 text-[15px]">
                {{ __('One line per post (with a Platforms column like "TT, IG, FB") or one line per platform both work.') }}
                <a href="{{ route('export.template') }}" class="underline">{{ __('Download an example file') }}</a>
            </p>

            @if (! $file)
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <label class="btn btn-primary cursor-pointer">
                        <x-icon name="upload" class="size-4" /> {{ __('Choose the CSV file') }}
                        <input type="file" wire:model="file" accept=".csv,text/csv" class="sr-only">
                    </label>
                    <span wire:loading wire:target="file" class="text-muted">{{ __('Reading the file...') }}</span>
                </div>
                @error('file')<p class="mt-2"><x-icon name="alert" class="size-4 inline text-primary" /> {{ $message }}</p>@enderror
            @else
                {{-- Step 2: columns --}}
                <h3 class="font-semibold mt-6 mb-2">{{ __('Which column is which?') }}</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($labels as $field => $label)
                        <div>
                            <label class="label" for="map-{{ $field }}">{{ $label }}@if (in_array($field, ['date', 'title'])) *@endif</label>
                            <select id="map-{{ $field }}" wire:model.live="mapping.{{ $field }}" class="select">
                                <option value="">{{ __('Not in the file') }}</option>
                                @foreach ($columnOptions as $key => $header)<option value="{{ $key }}">{{ $header }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="import-brand">{{ __('Brand for lines without a brand') }}</label>
                        <select id="import-brand" wire:model.live="brandId" class="select">
                            <option value="">{{ __('None: the file has a Brand column') }}</option>
                            @foreach (\App\Support\BrandContext::make()->brands() as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 self-end min-h-11"><input type="checkbox" wire:model="createMissing" class="checkbox"> {{ __('Create series and product families that do not exist yet') }}</label>
                </div>

                {{-- Step 3: preview --}}
                <h3 class="font-semibold mt-6 mb-1">{{ __('Preview') }}</h3>
                <p class="text-muted mb-2">
                    {{ trans_choice(':count line ready|:count lines ready', $ok) }}
                    @if ($withErrors) · <span class="text-ink font-semibold">{{ trans_choice(':count line with a problem (it will be skipped)|:count lines with a problem (they will be skipped)', $withErrors) }}</span>@endif
                </p>
                <div class="card relative overflow-x-auto max-h-[28rem] overflow-y-auto">
                    <table class="table-pf min-w-[760px]">
                        <thead class="sticky top-0 bg-surface">
                            <tr>
                                <th scope="col">{{ __('Line') }}</th>
                                <th scope="col">{{ __('Date') }}</th>
                                <th scope="col">{{ __('Brand') }}</th>
                                <th scope="col">{{ __('Title') }}</th>
                                <th scope="col">{{ __('Format') }}</th>
                                <th scope="col">{{ __('Platforms') }}</th>
                                <th scope="col">{{ __('Problem') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview, 0, 200) as $line)
                                <tr wire:key="pre-{{ $line['line'] }}" @class(['bg-primary-ink/10' => $line['errors']])>
                                    <td class="tabular-nums text-muted">{{ $line['line'] }}</td>
                                    <td class="whitespace-nowrap">{{ $line['date'] ? ucfirst($line['date']->isoFormat('ddd D MMM YYYY, HH:mm')) : __('No date') }}</td>
                                    <td>{{ $line['brand']?->name ?? '-' }}</td>
                                    <td dir="auto">{{ $line['title'] }}</td>
                                    <td class="whitespace-nowrap">{{ $line['format']->label() }}{{ $line['offer'] ? ' · '.__('Offer') : '' }}</td>
                                    <td>{{ $line['platforms'] ? collect($line['platforms'])->map(fn ($key) => ucfirst($key))->implode(', ') : __('all') }}</td>
                                    <td>@if ($line['errors'])<x-icon name="alert" class="size-4 inline text-primary" /> {{ implode(', ', $line['errors']) }}@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="import" class="btn btn-primary" @disabled($ok === 0) wire:loading.attr="disabled" wire:target="import">
                        <x-icon name="upload" class="size-4" /> {{ trans_choice('Import :count line|Import :count lines', $ok) }}
                    </button>
                    <button type="button" wire:click="cancel" class="btn btn-ghost">{{ __('Cancel') }}</button>
                </div>
            @endif

            @if ($report)
                <div class="mt-4 card p-4" role="status">
                    <p class="font-semibold">{{ trans_choice(':count post imported|:count posts imported', $report['created']) }}</p>
                    @if ($report['duplicates'])<p class="text-muted">{{ trans_choice(':count already in PostFlow, skipped|:count already in PostFlow, skipped', $report['duplicates']) }}</p>@endif
                    @if ($report['skipped'])<p class="text-muted">{{ trans_choice(':count line with a problem, skipped|:count lines with a problem, skipped', $report['skipped']) }}</p>@endif
                    <a href="{{ route('calendar') }}" class="btn btn-secondary btn-sm mt-2">{{ __('See the calendar') }}</a>
                </div>
            @endif
        </section>
    @endcan

    <section class="card p-5 lg:p-6" aria-labelledby="export-title">
        <h2 id="export-title" class="font-display font-semibold text-xl">{{ __('Export') }}</h2>
        <p class="text-muted mt-1">{{ __('Exports follow the brand selected at the top (or all brands).') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] items-end">
            <div>
                <label class="label" for="ex-from">{{ __('From') }}</label>
                <input id="ex-from" type="date" wire:model.live="from" class="input">
            </div>
            <div>
                <label class="label" for="ex-to">{{ __('To') }}</label>
                <input id="ex-to" type="date" wire:model.live="to" class="input">
            </div>
            <a href="{{ route('export.posts', ['from' => $from, 'to' => $to]) }}" class="btn btn-primary"><x-icon name="download" class="size-4" /> {{ __('Posts (CSV)') }}</a>
        </div>
        <ul class="mt-5 space-y-2 text-[15px]">
            <li class="flex flex-wrap items-center gap-2">
                <a href="{{ route('export.metrics', ['month' => $exportFrom->format('Y-m')]) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4" /> {{ __('Numbers of :month (CSV)', ['month' => $exportFrom->isoFormat('MMMM YYYY')]) }}</a>
            </li>
            <li class="text-muted">{{ __('PDF and image: use the Export button on the Calendar, Radar, Pipeline, Seasons and Performance screens.') }}</li>
        </ul>
    </section>
</div>
