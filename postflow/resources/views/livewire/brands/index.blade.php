<div>
    <x-page-header :title="__('Brands')" :subtitle="__('Each page you manage: identity, platforms, series, product families and weekly targets.')">
        <x-slot:actions>
            <a href="{{ route('brands.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New brand') }}</a>
        </x-slot:actions>
    </x-page-header>

    @if ($this->brands->isEmpty())
        <x-empty icon="layers" :title="__('No brand yet')" :text="__('Create your first brand to start planning.')" />
    @endif

    <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->brands as $brand)
            @php
                $colors = $brand->colors();
            @endphp
            <li wire:key="brand-{{ $brand->id }}" class="card overflow-hidden flex flex-col">
                {{-- Colour strip: the brand's own palette --}}
                <div class="flex h-2" aria-hidden="true">
                    @foreach (['color_bg', 'color_bg_2', 'color_primary', 'color_light', 'color_accent'] as $key)
                        <span class="flex-1" style="background: {{ $colors[$key] }}"></span>
                    @endforeach
                </div>
                <div class="p-5 flex-1 flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <x-brand-mark :brand="$brand" size="size-11" />
                        <div class="min-w-0">
                            <h2 class="font-display font-semibold text-lg truncate">{{ $brand->name }}</h2>
                            <p class="text-sm text-muted">
                                {{ trans_choice(':count post this month|:count posts this month', $brand->posts_this_month) }}
                                @unless ($brand->active) · {{ __('Archived') }} @endunless
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        @forelse ($brand->platforms as $platform)
                            <x-platform-chip :platform="$platform" />
                        @empty
                            <span class="text-sm text-muted">{{ __('No platform') }}</span>
                        @endforelse
                    </div>

                    <dl class="text-[15px] space-y-1">
                        <div class="flex gap-2"><dt class="text-muted shrink-0">{{ __('Rhythm') }}</dt><dd>{{ $brand->rhythmLabel() ?: '-' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted shrink-0">{{ __('Content series') }}</dt><dd>{{ $brand->series->pluck('name')->implode(', ') ?: '-' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted shrink-0">{{ __('Team') }}</dt><dd>{{ $brand->users->pluck('name')->implode(', ') ?: __('Admin only') }}</dd></div>
                    </dl>

                    <div class="mt-auto pt-2 flex gap-2">
                        <a href="{{ route('brands.edit', $brand) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" /> {{ __('Edit') }}</a>
                        <a href="{{ route('brand.switch', $brand->id) }}" class="btn btn-ghost btn-sm">{{ __('Switch to this brand') }}</a>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
