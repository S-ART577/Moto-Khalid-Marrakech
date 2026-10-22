@props(['target', 'filename' => 'postflow', 'csv' => null])
{{-- Export the element #{target} as an image, print it to PDF, or download a CSV. --}}
<div class="no-print relative" x-data="{ open: false, busy: false }" x-on:keydown.escape="open = false">
    <button type="button" class="btn btn-secondary" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true">
        <x-icon name="download" class="size-4" /> {{ __('Export') }}
    </button>
    <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 z-30 mt-1 w-56 card p-1 shadow-xl">
        <button type="button" class="w-full text-left rounded-[8px] px-3 py-2.5 hover:bg-light/5 flex items-center gap-2" x-bind:disabled="busy"
                x-on:click="busy = true; await window.pfExportPng(document.getElementById(@js($target)), @js($filename.'.png')); busy = false; open = false">
            <x-icon name="image" class="size-4" /> <span x-text="busy ? @js(__('Preparing...')) : @js(__('Image (PNG)'))"></span>
        </button>
        <button type="button" class="w-full text-left rounded-[8px] px-3 py-2.5 hover:bg-light/5 flex items-center gap-2" x-on:click="open = false; $nextTick(() => window.print())">
            <x-icon name="printer" class="size-4" /> {{ __('PDF (print)') }}
        </button>
        @if ($csv)
            <a href="{{ $csv }}" class="w-full rounded-[8px] px-3 py-2.5 hover:bg-light/5 flex items-center gap-2"><x-icon name="list" class="size-4" /> {{ __('Spreadsheet (CSV)') }}</a>
        @endif
    </div>
</div>
