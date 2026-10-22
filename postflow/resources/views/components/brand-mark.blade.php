@props(['brand', 'size' => 'size-8'])
{{-- Brand logo, or its initials on its own red when there is no logo. --}}
@if ($brand->logoUrl())
    <img src="{{ $brand->logoUrl() }}" alt="" class="{{ $size }} rounded-md object-contain bg-white/5 shrink-0">
@else
    <span class="{{ $size }} rounded-md shrink-0 inline-grid place-items-center font-display font-bold text-[13px] text-white"
          style="background: {{ $brand->colors()['color_primary_text'] }}" aria-hidden="true">{{ $brand->initials() }}</span>
@endif
