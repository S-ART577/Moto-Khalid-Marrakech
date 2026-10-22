@props(['post', 'showBrand' => false])
@php
    [$done, $total] = $post->checklistProgress();
    $late = $post->variants->contains(fn ($variant) => $variant->isOverdue());
@endphp
{{-- Bigger post card for the week view and lists. --}}
<button type="button" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })"
        {{ $attributes->class(['card w-full text-left p-3 flex flex-col gap-2 hover:bg-raised', 'border-primary' => $late]) }}>
    <span class="flex items-center gap-x-2 gap-y-1 flex-wrap">
        <x-kind-chip :post="$post" />
        <span class="tabular-nums font-semibold">{{ $post->planned_at?->format('H:i') }}</span>
        @if ($late)<span class="chip bg-primary-ink text-white"><x-icon name="alert" class="size-3.5" /> {{ __('Overdue') }}</span>@endif
    </span>
    <span class="font-display font-semibold leading-snug" dir="auto">{{ $post->title }}</span>
    <span class="text-sm text-muted">
        @if ($showBrand){{ $post->brand->name }} · @endif{{ $post->series?->name ?? __('No series') }}
    </span>
    <span class="flex flex-wrap gap-1">
        @foreach ($post->variants as $variant)
            <x-platform-chip :platform="$variant->platform" :status="$variant->status" />
        @endforeach
    </span>
    <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
        <x-avatar :user="$post->owner" size="size-6" />
        <span>{{ $post->stage->label() }}</span>
        @if ($total)<span class="tabular-nums" title="{{ __('Checklist') }}">{{ $done }}/{{ $total }}</span>@endif
    </span>
</button>
