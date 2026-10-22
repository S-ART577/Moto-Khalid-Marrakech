@php
    $now = now();
    $variants = $this->variants;
    $overdue = $variants->filter(fn ($v) => $v->isOverdue());
    $todo = $variants->filter(fn ($v) => $v->status === \App\Enums\VariantStatus::Planned && ! $v->isOverdue());
    $done = $variants->filter(fn ($v) => $v->status !== \App\Enums\VariantStatus::Planned);
    $total = $variants->filter(fn ($v) => $v->publish_at->isToday())->count();
    $doneToday = $done->filter(fn ($v) => $v->publish_at->isToday())->count();
    $showBrand = \App\Support\BrandContext::make()->current() === null;
@endphp
<div class="max-w-2xl mx-auto">
    <header class="mb-5">
        <p class="text-muted">{{ ucfirst($now->isoFormat('dddd D MMMM')) }}</p>
        <h1 class="font-display font-bold text-3xl">{{ __('Today') }}</h1>
        @if ($total)
            <div class="mt-3">
                <div class="flex justify-between text-[15px] mb-1">
                    <span>{{ __(':done of :total posted', ['done' => $doneToday, 'total' => $total]) }}</span>
                    @if ($overdue->count())<span class="font-semibold"><x-icon name="alert" class="size-4 inline text-primary" /> {{ trans_choice(':count overdue|:count overdue', $overdue->count()) }}</span>@endif
                </div>
                <div class="h-2 rounded-full bg-raised overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $doneToday }}" aria-label="{{ __('Posted today') }}">
                    <div class="h-full bg-light rounded-full" style="width: {{ $total ? $doneToday / $total * 100 : 0 }}%"></div>
                </div>
            </div>
        @endif
    </header>

    {{-- Turn on reminders on this phone --}}
    @if (config('webpush.public_key'))
        <div x-data="{ show: 'Notification' in window && Notification.permission === 'default', state: 'idle' }" x-show="show" x-cloak class="card p-4 mb-4 flex flex-wrap items-center gap-3">
            <x-icon name="bell" class="size-6 text-accent" />
            <p class="flex-1 min-w-48">{{ __('Get a reminder on this phone before each post.') }}</p>
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="state = await window.pfEnablePush(@js(config('webpush.public_key')), @js(route('push.subscribe'))); show = state !== 'on'">{{ __('Turn on') }}</button>
        </div>
    @endif

    @foreach ($this->events as $event)
        <div wire:key="event-{{ $event->id }}" class="card p-4 mb-4 border-accent">
            <p class="font-semibold flex items-center gap-2"><x-icon name="flag" class="size-5 text-accent" /> {{ $event->name() }}</p>
            @if ($event->guidance)<p class="text-muted mt-1">{{ $event->guidance }}</p>@endif
        </div>
    @endforeach

    @if ($variants->isEmpty())
        <x-empty icon="check" :title="__('Nothing to post today')" :text="__('Enjoy the calm. Tomorrow is below.')" />
    @endif

    @foreach ([['overdue', __('Overdue'), $overdue], ['todo', __('To post'), $todo]] as [$key, $label, $list])
        @if ($list->isNotEmpty())
            <section class="mb-6" aria-labelledby="today-{{ $key }}">
                <h2 id="today-{{ $key }}" class="font-display font-semibold text-lg mb-2 flex items-center gap-2">
                    @if ($key === 'overdue')<x-icon name="alert" class="size-5 text-primary" />@endif
                    {{ $label }} <span class="text-muted font-normal">{{ $list->count() }}</span>
                </h2>
                <ol class="space-y-3">
                    @foreach ($list as $variant)
                        @php
                            $post = $variant->post;
                            $assets = $post->assets->filter(fn ($a) => $a->post_variant_id === null || $a->post_variant_id === $variant->id);
                            $late = $key === 'overdue';
                        @endphp
                        <li wire:key="v-{{ $variant->id }}">
                            <article @class(['card p-4 space-y-3', 'border-primary border-2' => $late]) aria-labelledby="v-title-{{ $variant->id }}"
                                     x-data="{ copied: null, flash(what) { this.copied = what; setTimeout(() => this.copied = null, 1600) } }">
                                <header class="flex items-start gap-3">
                                    <div class="text-center shrink-0 w-16">
                                        <p class="font-display font-bold text-2xl tabular-nums leading-none">{{ $variant->publish_at->format('H:i') }}</p>
                                        @if ($late)
                                            <p class="chip bg-primary-ink text-white mt-1 px-1.5">{{ $variant->publish_at->isToday() ? __('Late') : ucfirst($variant->publish_at->isoFormat('ddd D')) }}</p>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 id="v-title-{{ $variant->id }}" class="font-display font-semibold text-lg leading-tight">
                                            {{ $variant->platform->name }}
                                            <span class="text-muted font-sans font-normal text-[15px]">· {{ __(ucfirst($variant->placement ?? '')) }} {{ $variant->aspect_ratio }}</span>
                                        </h3>
                                        <button type="button" x-on:click="$dispatch('open-post', { id: {{ $post->id }} })" class="text-left text-muted hover:text-ink hover:underline" dir="auto">
                                            {{ $showBrand ? $post->brand->name.' · ' : '' }}{{ $post->title }}
                                        </button>
                                    </div>
                                    <x-kind-chip :post="$post" class="shrink-0 hidden sm:inline-flex" />
                                </header>

                                @if (filled($variant->caption))
                                    <p class="whitespace-pre-line line-clamp-4 rounded-[10px] bg-raised px-3 py-2" dir="auto">{{ $variant->caption }}</p>
                                @else
                                    <p class="rounded-[10px] border border-dashed border-line px-3 py-2 text-muted">{{ __('No caption yet. Write it in the post or ask the admin.') }}</p>
                                @endif
                                @if (filled($variant->hashtags))
                                    <p class="text-muted text-[15px] break-words" dir="auto">{{ $variant->hashtags }}</p>
                                @endif

                                {{-- Step 1-3: copy, get the file, open the app --}}
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" class="btn btn-secondary px-2 gap-1.5 text-[14px]" @disabled(blank($variant->caption))
                                            x-on:click="if (await window.pfCopy(@js($variant->caption))) flash('caption')">
                                        <x-icon name="copy" class="size-4" />
                                        <span x-show="copied !== 'caption'">{{ __('Copy caption') }}</span>
                                        <span x-show="copied === 'caption'" x-cloak>{{ __('Copied') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary px-2 gap-1.5 text-[14px]" @disabled(blank($variant->hashtags))
                                            x-on:click="if (await window.pfCopy(@js($variant->hashtags))) flash('tags')">
                                        <x-icon name="copy" class="size-4" />
                                        <span x-show="copied !== 'tags'">{{ __('Copy hashtags') }}</span>
                                        <span x-show="copied === 'tags'" x-cloak>{{ __('Copied') }}</span>
                                    </button>
                                    @forelse ($assets as $asset)
                                        <a href="{{ $asset->downloadUrl() ?? $asset->url }}" target="_blank" rel="noopener" class="btn btn-secondary px-2 gap-1.5 text-[14px]" title="{{ $asset->label }}">
                                            <x-icon :name="$asset->downloadUrl() ? 'download' : 'external'" class="size-4" />
                                            <span class="truncate">{{ $asset->downloadUrl() ? __('Download') : __('Open file') }}</span>
                                        </a>
                                    @empty
                                        <span class="btn btn-secondary px-2 gap-1.5 text-[14px]" aria-disabled="true"><x-icon name="link" class="size-4" /> {{ __('No file') }}</span>
                                    @endforelse
                                    <a href="{{ $variant->platform->openUrlFor($post->brand) }}" target="_blank" rel="noopener" class="btn btn-light px-2 gap-1.5 text-[14px]">
                                        <x-icon name="external" class="size-4" /> {{ __('Open :platform', ['platform' => $variant->platform->name]) }}
                                    </a>
                                </div>

                                {{-- Step 4: mark as posted --}}
                                @if ($confirming === $variant->id)
                                    <div class="rounded-[10px] border border-line p-3 space-y-2">
                                        <label class="label" for="url-{{ $variant->id }}">{{ __('Link of the post (optional)') }}</label>
                                        <div class="flex gap-2">
                                            <input id="url-{{ $variant->id }}" type="url" inputmode="url" wire:model="urls.{{ $variant->id }}" class="input" placeholder="https://..." autofocus
                                                   x-ref="url{{ $variant->id }}">
                                            <button type="button" class="btn btn-secondary shrink-0" title="{{ __('Paste') }}"
                                                    x-on:click="window.pfPaste($refs.url{{ $variant->id }})">
                                                {{ __('Paste') }}
                                            </button>
                                        </div>
                                        @error('urls.'.$variant->id)<p class="text-sm"><x-icon name="alert" class="size-4 inline text-primary" /> {{ $message }}</p>@enderror
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="markPosted({{ $variant->id }})" class="btn btn-primary flex-1"><x-icon name="check" class="size-4" /> {{ __('Confirm posted') }}</button>
                                            <button type="button" wire:click="$set('confirming', null)" class="btn btn-ghost">{{ __('Cancel') }}</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="startPosting({{ $variant->id }})" class="btn btn-primary flex-1"><x-icon name="check" class="size-4" /> {{ __('Mark as posted') }}</button>
                                        <button type="button" wire:click="skip({{ $variant->id }})" wire:confirm="{{ __('Skip :platform for this post?', ['platform' => $variant->platform->name]) }}" class="btn btn-ghost">{{ __('Skip') }}</button>
                                    </div>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @endforeach

    @if ($done->isNotEmpty())
        <section class="mb-6" aria-labelledby="today-done" x-data="{ open: false }">
            <h2 id="today-done">
                <button type="button" class="font-display font-semibold text-lg flex items-center gap-2" x-on:click="open = ! open" x-bind:aria-expanded="open">
                    <x-icon name="chevron-right" class="size-5 transition-transform" x-bind:class="open && 'rotate-90'" />
                    {{ __('Done') }} <span class="text-muted font-normal">{{ $done->count() }}</span>
                </button>
            </h2>
            <ul x-show="open" x-cloak class="mt-2 space-y-2">
                @foreach ($done as $variant)
                    <li wire:key="done-{{ $variant->id }}" class="card px-3 py-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <x-icon :name="$variant->status === \App\Enums\VariantStatus::Posted ? 'check' : 'x'" class="size-4" />
                        <span class="tabular-nums">{{ $variant->publish_at->format('H:i') }}</span>
                        <span class="font-medium">{{ $variant->platform->name }}</span>
                        <span class="text-muted flex-1 min-w-0 truncate" dir="auto">{{ $variant->post->title }}</span>
                        @if ($variant->post_url)<a href="{{ $variant->post_url }}" target="_blank" rel="noopener" class="underline text-[15px]">{{ __('See the post') }}</a>@endif
                        <button type="button" wire:click="undo({{ $variant->id }})" class="btn btn-ghost btn-sm">{{ __('Undo') }}</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="today-tomorrow">
        <h2 id="today-tomorrow" class="font-display font-semibold text-lg mb-2">{{ __('Tomorrow') }}</h2>
        @forelse ($this->tomorrow as $variant)
            <button type="button" wire:key="tomorrow-{{ $variant->id }}" x-on:click="$dispatch('open-post', { id: {{ $variant->post_id }} })"
                    class="w-full text-left card px-3 py-2 mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 hover:bg-raised">
                <span class="tabular-nums font-semibold">{{ $variant->publish_at->format('H:i') }}</span>
                <span class="font-medium">{{ $variant->platform->name }}</span>
                <span class="text-muted flex-1 min-w-0 truncate" dir="auto">{{ $variant->post->title }}</span>
                @if (blank($variant->caption))<span class="chip bg-raised text-muted">{{ __('No caption yet') }}</span>@endif
            </button>
        @empty
            <p class="text-muted">{{ __('Nothing planned tomorrow.') }}</p>
        @endforelse
    </section>
</div>
