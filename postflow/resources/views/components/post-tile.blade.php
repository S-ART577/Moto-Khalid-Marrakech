@props(['post', 'showBrand' => false])
@php
    $late = $post->variants->contains(fn ($variant) => $variant->isOverdue());
    $posted = $post->stage === \App\Enums\Stage::Posted;
    $platforms = $post->variants->sortBy(fn ($v) => $v->platform->position)->map(fn ($v) => $v->platform->short)->implode(' ');
@endphp
{{-- A post on the calendar, coloured like the printed plan: video = red, static = light, offer = white. --}}
<button type="button" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })"
        {{ $attributes->class(['kind-'.$post->kind(), 'w-full h-full text-left rounded-[8px] px-2 py-1.5 flex flex-col gap-0.5 hover:brightness-105', 'ring-2 ring-inset ring-current/30' => $posted]) }}
        title="{{ $post->title }}{{ $post->series ? ' · '.$post->series->name : '' }} · {{ $late ? __('Overdue') : $post->stage->label() }}">
    <span class="eyebrow flex items-center gap-1 min-w-0">
        @if ($showBrand)
            <x-brand-mark :brand="$post->brand" size="size-4" />
        @endif
        @if ($posted)
            <x-icon name="check" class="size-3.5" />
        @elseif ($late)
            <x-icon name="alert" class="size-3.5" />
        @else
            <x-icon :name="$post->is_offer ? 'tag' : ($post->format->value === 'video' ? 'video' : 'image')" class="size-3.5" />
        @endif
        <span class="truncate">{{ $post->kindLabel() }}</span>
    </span>
    <span class="font-display font-semibold text-[14px] leading-snug line-clamp-3" dir="auto">{{ $post->title }}</span>
    <span class="mt-auto pt-0.5 flex flex-wrap gap-x-2 text-[13px] leading-tight font-semibold tabular-nums">
        @if ($post->planned_at)<span class="whitespace-nowrap">{{ $post->planned_at->format('H:i') }}</span>@endif
        @if ($platforms)<span class="whitespace-nowrap">{{ $platforms }}</span>@endif
        <span class="whitespace-nowrap">
            @if ($late){{ __('Overdue') }}@elseif ($posted){{ __('Posted') }}@else{{ $post->stage->label() }}@endif
        </span>
    </span>
</button>
