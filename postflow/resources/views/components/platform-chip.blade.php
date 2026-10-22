@props(['platform', 'status' => null])
{{-- TT / IG / FB / SC. A tick when this platform is posted, a line-through when skipped. --}}
<span {{ $attributes->class([
    'chip px-1.5 font-semibold tabular-nums',
    'bg-raised text-ink' => $status === null || $status === \App\Enums\VariantStatus::Planned,
    'bg-light text-navy' => $status === \App\Enums\VariantStatus::Posted,
    'bg-raised text-muted line-through' => $status === \App\Enums\VariantStatus::Skipped,
]) }} title="{{ $platform->name }}{{ $status ? ' · '.$status->label() : '' }}">
    @if ($status === \App\Enums\VariantStatus::Posted)<x-icon name="check" class="size-3.5" />@endif{{ $platform->short }}<span class="sr-only"> {{ $platform->name }}{{ $status ? ', '.$status->label() : '' }}</span>
</span>
