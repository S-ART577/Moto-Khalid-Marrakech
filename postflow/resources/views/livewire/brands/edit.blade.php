@php
    $colorFields = [
        'color_bg' => __('Background'),
        'color_bg_2' => __('Cards / second background'),
        'color_primary' => __('Accent red (bars, dots, badges)'),
        'color_primary_text' => __('Tile red (carries white text)'),
        'color_light' => __('Light (text, static tiles)'),
        'color_accent' => __('Highlight (used rarely)'),
    ];
    $weekdays = collect(range(1, 7))->mapWithKeys(fn ($d) => [$d => \Carbon\CarbonImmutable::create(2024, 1, $d)->isoFormat('dddd')]);
    $sections = ['identity' => __('Identity'), 'colors' => __('Colours'), 'platforms' => __('Platforms'), 'content' => __('Series and products'), 'rhythm' => __('Rhythm and targets'), 'team' => __('Team')];
@endphp
<form wire:submit="save" class="max-w-5xl">
    <div class="no-print sticky top-16 z-20 -mx-4 lg:-mx-8 px-4 lg:px-8 py-3 mb-4 bg-bg/95 backdrop-blur border-b border-line flex flex-wrap items-center gap-3">
        <a href="{{ route('brands.index') }}" class="btn btn-ghost btn-sm px-2" title="{{ __('Back to brands') }}"><x-icon name="chevron-left" /><span class="sr-only">{{ __('Back to brands') }}</span></a>
        <h1 class="font-display font-bold text-xl lg:text-2xl flex-1 min-w-0 truncate">{{ $brand?->name ?? __('New brand') }}</h1>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,logo">{{ __('Save brand') }}</button>
    </div>

    <nav aria-label="{{ __('Sections') }}" class="mb-6 flex flex-wrap gap-2">
        @foreach ($sections as $id => $label)
            <a href="#{{ $id }}" class="chip border border-line text-muted hover:text-ink h-9 px-3">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="card border-primary p-4 mb-6" role="alert">
            <p class="font-semibold flex items-center gap-2"><x-icon name="alert" class="text-primary" /> {{ __('Some fields need attention') }}</p>
            <ul class="mt-2 list-disc pl-6 text-[15px]">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        {{-- Identity --}}
        <section id="identity" class="card p-5 lg:p-6 scroll-mt-36" aria-labelledby="identity-title">
            <h2 id="identity-title" class="font-display font-semibold text-lg mb-4">{{ __('Identity') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">{{ __('Name') }}</label>
                    <input id="name" wire:model="form.name" class="input" required maxlength="80">
                </div>
                <div>
                    <span class="label">{{ __('Logo') }}</span>
                    <div class="flex items-center gap-3">
                        @if ($logo && method_exists($logo, 'temporaryUrl'))
                            <img src="{{ $logo->temporaryUrl() }}" alt="" class="size-12 rounded-md object-contain bg-white/5">
                        @elseif ($brand?->logoUrl() && ! $removeLogo)
                            <img src="{{ $brand->logoUrl() }}" alt="" class="size-12 rounded-md object-contain bg-white/5">
                        @endif
                        <label class="btn btn-secondary btn-sm cursor-pointer">
                            <x-icon name="upload" class="size-4" /> {{ __('Choose an image') }}
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="sr-only">
                        </label>
                        @if ($brand?->logo_path)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="removeLogo" class="checkbox"> {{ __('Remove') }}</label>
                        @endif
                    </div>
                    <p class="text-sm text-muted mt-1">{{ __('PNG, JPG or WEBP, 1 MB max. Square works best.') }}</p>
                </div>
                <div>
                    <label class="label" for="tagline">{{ __('Tagline') }}</label>
                    <input id="tagline" wire:model="form.tagline" class="input" dir="auto" placeholder="Produits vérifiés. Fiabilité garantie.">
                </div>
                <div>
                    <label class="label" for="signature">{{ __('Signature') }}</label>
                    <input id="signature" wire:model="form.signature" class="input" dir="auto" placeholder="Think GRO. Grow More.">
                </div>
                <div>
                    <label class="label" for="whatsapp">{{ __('WhatsApp number') }}</label>
                    <input id="whatsapp" wire:model="form.whatsapp" class="input" inputmode="tel" placeholder="212600000000">
                </div>
                <div class="flex items-end gap-6">
                    <label class="flex items-center gap-2 min-h-11"><input type="checkbox" wire:model="form.active" class="checkbox"> {{ __('Active') }}</label>
                    <div>
                        <label class="label" for="position">{{ __('Order in the switcher') }}</label>
                        <input id="position" type="number" min="0" wire:model="form.position" class="input w-24">
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="tone">{{ __('Tone of voice') }}</label>
                    <textarea id="tone" wire:model="form.tone_notes" class="textarea" rows="7" dir="auto" placeholder="{{ __('How this brand talks: language, words to use and avoid, proof points, CTA...') }}"></textarea>
                </div>
            </div>
        </section>

        {{-- Colours --}}
        <section id="colors" class="card p-5 lg:p-6 scroll-mt-36" aria-labelledby="colors-title">
            <h2 id="colors-title" class="font-display font-semibold text-lg mb-1">{{ __('Colours') }}</h2>
            <p class="text-muted mb-4">{{ __('PostFlow takes these colours when this brand is selected.') }}</p>
            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($colorFields as $key => $label)
                        <div>
                            <label class="label" for="{{ $key }}">{{ $label }}</label>
                            <div class="flex gap-2">
                                <input type="color" wire:model.live.debounce.250ms="form.{{ $key }}" class="h-11 w-14 rounded-[10px] border border-line bg-raised p-1 cursor-pointer" aria-label="{{ $label }}">
                                <input id="{{ $key }}" wire:model.live.debounce.400ms="form.{{ $key }}" class="input font-mono uppercase" maxlength="7" spellcheck="false">
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Live preview --}}
                <div>
                    <div class="rounded-[12px] p-4 border border-line"
                         style="background: {{ $form['color_bg'] }}; color: {{ $form['color_light'] }}">
                        <p class="font-display font-bold text-xl">{{ $form['name'] ?: __('Preview') }}</p>
                        <div class="mt-3 rounded-[12px] p-3" style="background: {{ $form['color_bg_2'] }}">
                            <p class="text-[15px]">{{ __('Card text looks like this.') }}</p>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-[13px] font-semibold uppercase">
                                <span class="rounded-md p-2 text-white" style="background: {{ $form['color_primary_text'] }}">{{ __('Video') }}</span>
                                <span class="rounded-md p-2" style="background: {{ $form['color_light'] }}; color: {{ $form['color_bg'] }}">{{ __('Static') }}</span>
                                <span class="rounded-md p-2" style="background: #fff; color: {{ $form['color_bg'] }}; box-shadow: inset 0 -3px 0 {{ $form['color_primary'] }}">{{ __('Offer') }}</span>
                            </div>
                            <div class="mt-3 h-1.5 rounded-full" style="background: {{ $form['color_accent'] }}" aria-hidden="true"></div>
                        </div>
                    </div>
                    @if ($this->contrastChecks)
                        <ul class="mt-3 space-y-1 text-[15px]" aria-label="{{ __('Readability check') }}">
                            @foreach ($this->contrastChecks as $label => $ratio)
                                <li class="flex items-center gap-2">
                                    @if ($ratio >= 4.5)
                                        <x-icon name="check" class="size-4 text-ink" /> <span>{{ $label }}: {{ number_format($ratio, 1) }}:1</span>
                                    @else
                                        <x-icon name="alert" class="size-4 text-primary" /> <span>{{ $label }}: {{ number_format($ratio, 1) }}:1 · <strong>{{ __('too low, aim for 4.5') }}</strong></span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>

        {{-- Platforms --}}
        <section id="platforms" class="card p-5 lg:p-6 scroll-mt-36" aria-labelledby="platforms-title">
            <h2 id="platforms-title" class="font-display font-semibold text-lg mb-1">{{ __('Platforms') }}</h2>
            <p class="text-muted mb-4">{{ __('The profile link is what "Open platform" opens on the Today screen.') }}</p>
            <div class="space-y-3">
                @foreach ($allPlatforms as $platform)
                    <div wire:key="platform-{{ $platform->id }}" class="grid gap-2 sm:grid-cols-[180px_1fr_1.5fr] items-center">
                        <label class="flex items-center gap-2 font-medium min-h-11">
                            <input type="checkbox" wire:model.live="platforms.{{ $platform->id }}.enabled" class="checkbox"> {{ $platform->name }}
                        </label>
                        <input wire:model="platforms.{{ $platform->id }}.handle" class="input" placeholder="@handle" aria-label="{{ $platform->name }} {{ __('handle') }}" @disabled(! $platforms[$platform->id]['enabled'])>
                        <input wire:model="platforms.{{ $platform->id }}.profile_url" class="input" type="url" placeholder="https://..." aria-label="{{ $platform->name }} {{ __('Profile link') }}" @disabled(! $platforms[$platform->id]['enabled'])>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Series + product families --}}
        <section id="content" class="card p-5 lg:p-6 scroll-mt-36" aria-labelledby="content-title">
            <h2 id="content-title" class="font-display font-semibold text-lg mb-4">{{ __('Series and products') }}</h2>
            <div class="grid gap-8 xl:grid-cols-2">
                <div>
                    <h3 class="font-semibold mb-1">{{ __('Content series') }}</h3>
                    <p class="text-muted mb-3">{{ __('Recurring formats, e.g. "Wait... same price?".') }}</p>
                    <div class="space-y-3">
                        @foreach ($series as $i => $row)
                            <div wire:key="series-{{ $row['id'] ?? 'new-'.$i }}" class="rounded-[10px] border border-line p-3 grid gap-2 grid-cols-[44px_1fr_auto] items-start">
                                <input type="color" wire:model="series.{{ $i }}.color" class="h-11 w-11 rounded-[10px] border border-line bg-raised p-1" aria-label="{{ __('Colour') }}">
                                <input wire:model="series.{{ $i }}.name" class="input" dir="auto" placeholder="{{ __('Series name') }}" aria-label="{{ __('Series name') }}">
                                <button type="button" wire:click="removeRow('series', {{ $i }})" class="btn btn-ghost px-2" title="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Remove') }}</span></button>
                                <textarea wire:model="series.{{ $i }}.description" class="textarea col-span-3" rows="3" dir="auto" placeholder="{{ __('What it is, the hook, the format') }}" aria-label="{{ __('Description') }}"></textarea>
                                <label class="col-span-3 flex items-center gap-2 text-[15px]"><input type="checkbox" wire:model="series.{{ $i }}.active" class="checkbox"> {{ __('Active') }}</label>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addRow('series')" class="btn btn-secondary btn-sm mt-3"><x-icon name="plus" class="size-4" /> {{ __('Add a series') }}</button>
                </div>

                <div>
                    <h3 class="font-semibold mb-1">{{ __('Product families') }}</h3>
                    <p class="text-muted mb-3">{{ __('The radar warns when a family has not been featured for too long.') }}</p>
                    <div class="space-y-2">
                        @foreach ($families as $i => $row)
                            <div wire:key="family-{{ $row['id'] ?? 'new-'.$i }}" class="grid gap-2 grid-cols-[1fr_auto_auto] items-center">
                                <input wire:model="families.{{ $i }}.name" class="input" dir="auto" placeholder="{{ __('Product family name') }}" aria-label="{{ __('Product family name') }}">
                                <label class="flex items-center gap-2 text-[15px] whitespace-nowrap">
                                    <span class="sr-only">{{ __('Warn after') }}</span>
                                    <input type="number" min="1" max="26" wire:model="families.{{ $i }}.max_gap_weeks" class="input w-20"> {{ __('wks') }}
                                </label>
                                <button type="button" wire:click="removeRow('families', {{ $i }})" class="btn btn-ghost px-2" title="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Remove') }}</span></button>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-muted mt-2">{{ __('"wks" = warn after this many weeks without a post.') }}</p>
                    <button type="button" wire:click="addRow('families')" class="btn btn-secondary btn-sm mt-3"><x-icon name="plus" class="size-4" /> {{ __('Add a product family') }}</button>
                </div>
            </div>
        </section>

        {{-- Rhythm + targets --}}
        <section id="rhythm" class="card p-5 lg:p-6 scroll-mt-36" aria-labelledby="rhythm-title">
            <h2 id="rhythm-title" class="font-display font-semibold text-lg mb-4">{{ __('Rhythm and targets') }}</h2>
            <div class="grid gap-8 xl:grid-cols-2">
                <div>
                    <h3 class="font-semibold mb-1">{{ __('Weekly rhythm') }}</h3>
                    <p class="text-muted mb-3">{{ __('Your usual slots. "Fill empty slots" on the calendar creates drafts from them.') }}</p>
                    <div class="space-y-2">
                        @foreach ($postingSlots as $i => $row)
                            <div wire:key="slot-{{ $row['id'] ?? 'new-'.$i }}" class="grid gap-2 grid-cols-2 sm:grid-cols-[minmax(7.5rem,1fr)_minmax(8.5rem,1fr)_7.5rem_auto] items-center">
                                <select wire:model="postingSlots.{{ $i }}.weekday" class="select" aria-label="{{ __('Day') }}">
                                    @foreach ($weekdays as $value => $day)<option value="{{ $value }}">{{ ucfirst($day) }}</option>@endforeach
                                </select>
                                <select wire:model="postingSlots.{{ $i }}.format" class="select" aria-label="{{ __('Format') }}">
                                    @foreach (\App\Enums\Format::cases() as $format)<option value="{{ $format->value }}">{{ $format->label() }}</option>@endforeach
                                </select>
                                <input type="time" wire:model="postingSlots.{{ $i }}.time" class="input" aria-label="{{ __('Time') }}">
                                <button type="button" wire:click="removeRow('postingSlots', {{ $i }})" class="btn btn-ghost px-2" title="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Remove') }}</span></button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addRow('postingSlots')" class="btn btn-secondary btn-sm mt-3"><x-icon name="plus" class="size-4" /> {{ __('Add a slot') }}</button>
                </div>

                <div>
                    <h3 class="font-semibold mb-1">{{ __('Targets') }}</h3>
                    <p class="text-muted mb-3">{{ __('What the radar compares your plan against.') }}</p>
                    <div class="space-y-2">
                        @foreach ($quotas as $i => $row)
                            <div wire:key="quota-{{ $row['id'] ?? 'new-'.$i }}" class="flex flex-wrap items-center gap-2 pb-2 sm:pb-0 border-b border-line sm:border-0">
                                <input type="number" min="1" max="200" wire:model="quotas.{{ $i }}.target" class="input w-20" aria-label="{{ __('Target') }}">
                                <select wire:model="quotas.{{ $i }}.what" class="select basis-full order-last sm:order-none sm:basis-0 sm:flex-1 min-w-0" aria-label="{{ __('What is counted') }}">
                                    @foreach ($this->quotaOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <select wire:model="quotas.{{ $i }}.period" class="select w-36" aria-label="{{ __('Period') }}">
                                    <option value="week">{{ __('per week') }}</option>
                                    <option value="month">{{ __('per month') }}</option>
                                </select>
                                <button type="button" wire:click="removeRow('quotas', {{ $i }})" class="btn btn-ghost px-2 ml-auto sm:ml-0" title="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Remove') }}</span></button>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-muted mt-2">{{ __('To target a new series or product family, save the brand first.') }}</p>
                    <button type="button" wire:click="addRow('quotas')" class="btn btn-secondary btn-sm mt-3"><x-icon name="plus" class="size-4" /> {{ __('Add a target') }}</button>
                </div>
            </div>
        </section>

        {{-- Team --}}
        <section id="team" class="card p-5 lg:p-6 scroll-mt-36" aria-labelledby="team-title">
            <h2 id="team-title" class="font-display font-semibold text-lg mb-1">{{ __('Team') }}</h2>
            <p class="text-muted mb-3">{{ __('Community managers who work on this brand. Admins see every brand.') }}</p>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                @forelse ($this->communityManagers as $person)
                    <label wire:key="cm-{{ $person->id }}" class="flex items-center gap-2"><input type="checkbox" value="{{ $person->id }}" wire:model="userIds" class="checkbox"> {{ $person->name }}</label>
                @empty
                    <p class="text-muted">{{ __('No community manager yet. Add one in Settings.') }}</p>
                @endforelse
            </div>
        </section>

        @if ($brand)
            <section class="card p-5 lg:p-6" aria-labelledby="danger-title">
                <h2 id="danger-title" class="font-display font-semibold text-lg mb-1">{{ __('Delete this brand') }}</h2>
                <p class="text-muted mb-3">{{ __('Deletes every post, metric and note of this brand. To pause a brand, untick "Active" instead.') }}</p>
                <button type="button" wire:click="delete" wire:confirm.prompt="{{ __('Delete :name and all its posts?', ['name' => $brand->name]) }}\n\n{{ __('Type DELETE to confirm') }}|DELETE" class="btn btn-secondary"><x-icon name="trash" class="size-4" /> {{ __('Delete brand') }}</button>
            </section>
        @endif
    </div>
</form>
