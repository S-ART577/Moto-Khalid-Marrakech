@props(['user', 'size' => 'size-7'])
@if ($user)
    <span {{ $attributes->class([$size, 'inline-grid place-items-center rounded-full bg-light text-navy text-[12px] font-bold shrink-0']) }} title="{{ $user->name }}">
        <span aria-hidden="true">{{ $user->initials() }}</span><span class="sr-only">{{ $user->name }}</span>
    </span>
@endif
