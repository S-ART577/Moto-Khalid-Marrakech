@props(['post', 'showBrand' => false, 'canAdvance' => false])
@php
    [$done, $total] = $post->checklistProgress();
    $late = $post->isProductionLate();
@endphp
{{-- A card on the production board. --}}
<div {{ $attributes->class(['card p-3 flex flex-col gap-2 hover:bg-raised', 'border-primary' => $late]) }}>
    <button type="button" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })" class="text-left flex flex-col gap-1.5">
        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <x-kind-chip :post="$post" />
            <span class="text-sm text-muted">{{ $post->planned_at ? ucfirst($post->planned_at->isoFormat('ddd D MMM')) : __('No date') }}</span>
            @if ($post->variants->contains(fn ($v) => $v->isOverdue()))
                <span class="chip bg-primary-ink text-white"><x-icon name="alert" class="size-3.5" /> {{ __('Overdue') }}</span>
            @endif
        </span>
        <span class="font-display font-semibold leading-snug" dir="auto">{{ $post->title }}</span>
        <span class="text-sm text-muted">{{ $showBrand ? $post->brand->name.' · ' : '' }}{{ $post->series?->name ?? __('No series') }}</span>
    </button>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
        <x-avatar :user="$post->owner" size="size-6" />
        @if ($post->due_on)
            <span @class(['flex items-center gap-1', 'text-ink font-semibold' => $late]) title="{{ __('Production due') }}">
                <span class="sr-only">{{ __('Production due') }}</span>
                <x-icon :name="$late ? 'alert' : 'clock'" @class(['size-4', 'text-primary' => $late]) />
                {{ $late ? __('Late') . ' · ' : '' }}{{ $post->due_on->isoFormat('D MMM') }}
            </span>
        @endif
        @if ($total)<span class="flex items-center gap-1" title="{{ __('Checklist') }}"><x-icon name="check" class="size-4" />{{ $done }}/{{ $total }}</span>@endif
        @if ($post->assets_count)<span class="flex items-center gap-1" title="{{ __('Files') }}"><x-icon name="link" class="size-4" />{{ $post->assets_count }}</span>@endif
        @if ($post->comments_count)<span class="flex items-center gap-1" title="{{ __('Comments') }}"><x-icon name="message" class="size-4" />{{ $post->comments_count }}</span>@endif
        @if ($canAdvance)
            <button type="button" wire:click="advance({{ $post->id }})" wire:sort:ignore class="ml-auto btn btn-ghost btn-sm px-2" title="{{ __('Next stage') }}">
                <x-icon name="chevron-right" class="size-4" /><span class="sr-only">{{ __('Move :title to the next stage', ['title' => $post->title]) }}</span>
            </button>
        @endif
    </div>
</div>
