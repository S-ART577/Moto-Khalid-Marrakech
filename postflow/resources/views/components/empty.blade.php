@props(['icon' => 'check', 'title', 'text' => null])
<div {{ $attributes->class(['card p-8 text-center']) }}>
    <x-icon :name="$icon" class="size-8 mx-auto text-muted" />
    <p class="mt-3 font-display font-semibold text-lg">{{ $title }}</p>
    @if ($text)<p class="mt-1 text-muted">{{ $text }}</p>@endif
    {{ $slot }}
</div>
