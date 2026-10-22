@props(['title', 'subtitle' => null])
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div class="min-w-0">
        <h1 class="font-display font-bold text-2xl lg:text-3xl">{{ $title }}</h1>
        @if ($subtitle)<p class="text-muted mt-1">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
