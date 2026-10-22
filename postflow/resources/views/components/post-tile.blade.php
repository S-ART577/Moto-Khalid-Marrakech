@props(['post', 'showBrand' => false])
@php
    $late = $post->variants->contains(fn ($variant) => $variant->isOverdue());
    $posted = $post->stage === \App\Enums\Stage::Posted;
    $meta = collect([
        $showBrand ? $post->brand->initials() : null,
        $post->planned_at?->format('H:i'),
        $post->variants->map(fn ($v) => $v->platform->short)->implode(' ') ?: null,
        $posted ? __('Posted') : $post->stage->label(),
    ])->filter()->implode(' · ');
@endphp
{{-- A post on the calendar, coloured like the printed plan: video = red, static = light, offer = white. --}}
<button type="button" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })"
        {{ $attributes->class(['kind-'.$post->kind(), 'w-full h-full text-left rounded-[8px] px-2 py-1.5 flex flex-col gap-0.5 hover:brightness-105', 'opacity-75' => $posted]) }}
        title="{{ $post->title }}{{ $post->series ? ' · '.$post->series->name : '' }} · {{ $post->stage->label() }}">
    <span class="eyebrow flex items-center gap-1 truncate">
        @if ($posted)
            <x-icon name="check" class="size-3.5 shrink-0" />
        @elseif ($late)
            <x-icon name="alert" class="size-3.5 shrink-0" />
        @else
            <x-icon :name="$post->is_offer ? 'tag' : ($post->format->value === 'video' ? 'video' : 'image')" class="size-3.5 shrink-0" />
        @endif
        {{ $post->kindLabel() }}
    </span>
    <span class="font-display font-semibold text-[14px] leading-snug line-clamp-3" dir="auto">{{ $post->title }}</span>
    <span class="mt-auto pt-0.5 text-[12px] font-semibold opacity-85 tabular-nums">{{ $meta }}</span>
    @if ($late)<span class="sr-only">{{ __('Overdue') }}</span>@endif
</button>
