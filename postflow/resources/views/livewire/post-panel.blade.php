<div>
    @if ($open)
        @php
            $master = $this->canEditMaster();
            $post = $this->post;
            $brand = $this->brand;
            $isCm = ! auth()->user()->isAdmin();
            // [long label, short label for phones]
            $tabs = ['idea' => [__('Idea'), __('Idea')], 'platforms' => [__('Platforms'), __('Platforms')], 'production' => [__('Checklist and files'), __('Files')], 'discussion' => [__('Comments'), __('Comments')]];
            $ratios = ['9:16', '4:5', '1:1', '16:9'];
        @endphp
        <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="panel-title"
             x-data x-on:keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/60" x-on:click="$wire.close()" aria-hidden="true"></div>

            <div x-trap.noscroll="true" class="absolute inset-y-0 right-0 w-full max-w-2xl bg-bg border-l border-line flex flex-col shadow-2xl">
                {{-- Header --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-line">
                    <div class="flex items-start gap-3">
                        @if ($brand)<x-brand-mark :brand="$brand" size="size-9" />@endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-muted">{{ $brand?->name }} · {{ $postId ? __('Post') : __('New post') }}</p>
                            <h2 id="panel-title" class="font-display font-semibold text-lg sm:text-xl line-clamp-2 break-words" dir="auto">{{ $form['title'] ?: __('Untitled idea') }}</h2>
                        </div>
                        <button type="button" wire:click="close" class="btn btn-ghost px-2" title="{{ __('Close') }}"><x-icon name="x" /><span class="sr-only">{{ __('Close') }}</span></button>
                    </div>
                    <div role="tablist" class="mt-3 -mb-3 flex gap-1 relative overflow-x-auto [scrollbar-width:none]">
                        @foreach ($tabs as $key => [$label, $short])
                            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="tab whitespace-nowrap"
                                    @if (! $postId && in_array($key, ['production', 'discussion'])) disabled title="{{ __('Save the post first') }}" @endif>
                                <span class="hidden sm:inline">{{ $label }}</span><span class="sm:hidden">{{ $short }}</span>
                                @if ($key === 'production' && $post)
                                    @php
                                        [$done, $total] = $post->checklistProgress();
                                    @endphp
                                    <span class="text-muted">{{ $done }}/{{ $total }}</span>
                                @elseif ($key === 'discussion' && $post && $post->comments->count())
                                    <span class="text-muted">{{ $post->comments->count() }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Body --}}
                <form id="post-form" wire:submit="save" class="flex-1 overflow-y-auto px-4 sm:px-6 py-5">
                    @if ($errors->any())
                        <div class="card border-primary p-3 mb-4" role="alert">
                            <ul class="list-disc pl-5 text-[15px]">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif

                    {{-- Idea --}}
                    <div @class(['hidden' => $tab !== 'idea']) role="tabpanel">
                        @if ($isCm)
                            <p class="text-muted mb-4 flex gap-2"><x-icon name="eye" class="size-5 shrink-0" /> {{ __('Dates, owner and format are set by the admin. You can change the stage up to Review.') }}</p>
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            @if (! $postId)
                                <div class="sm:col-span-2">
                                    <label class="label" for="p-brand">{{ __('Brand') }}</label>
                                    <select id="p-brand" wire:model.live="form.brand_id" class="select">
                                        @foreach (\App\Support\BrandContext::make()->brands() as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="sm:col-span-2">
                                <label class="label" for="p-title">{{ __('Title') }}</label>
                                <input id="p-title" wire:model.blur="form.title" class="input" dir="auto" required maxlength="160" @disabled(! $master) placeholder="{{ __('e.g. Wait... same price? AirPods vs pack') }}">
                            </div>
                            <fieldset>
                                <legend class="label">{{ __('Format') }}</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (\App\Enums\Format::cases() as $format)
                                        <label @class(['chip h-11 px-3 border cursor-pointer', 'border-primary bg-raised' => $form['format'] === $format->value, 'border-line text-muted' => $form['format'] !== $format->value])>
                                            <input type="radio" wire:model.live="form.format" value="{{ $format->value }}" class="sr-only" @disabled(! $master)>
                                            <x-icon :name="$format->value === 'video' ? 'video' : 'image'" class="size-4" /> {{ $format->label() }}
                                        </label>
                                    @endforeach
                                    <label @class(['chip h-11 px-3 border cursor-pointer', 'border-primary bg-raised' => $form['is_offer'], 'border-line text-muted' => ! $form['is_offer']])>
                                        <input type="checkbox" wire:model.live="form.is_offer" class="sr-only" @disabled(! $master)>
                                        <x-icon name="tag" class="size-4" /> {{ __('Offer') }}
                                    </label>
                                </div>
                            </fieldset>
                            <div>
                                <label class="label" for="p-stage">{{ __('Stage') }}</label>
                                <select id="p-stage" wire:model="form.stage" class="select">
                                    @foreach (\App\Enums\Stage::cases() as $stage)
                                        <option value="{{ $stage->value }}" @disabled($isCm && ($stage->needsApproval() || ($post && $post->stage->needsApproval())) && $stage->value !== $form['stage'])>{{ $stage->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="p-series">{{ __('Series') }}</label>
                                <select id="p-series" wire:model="form.series_id" class="select" @disabled(! $master)>
                                    <option value="">{{ __('No series') }}</option>
                                    @foreach ($brand?->series ?? [] as $series)
                                        <option value="{{ $series->id }}">{{ $series->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="p-family">{{ __('Product family') }}</label>
                                <select id="p-family" wire:model="form.product_family_id" class="select" @disabled(! $master)>
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($brand?->productFamilies ?? [] as $family)
                                        <option value="{{ $family->id }}">{{ $family->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="p-date">{{ __('Publish date') }}</label>
                                <input id="p-date" type="date" wire:model.live.blur="form.date" class="input" @disabled(! $master)>
                                <p class="text-sm text-muted mt-1">{{ __('Empty = idea without a date.') }}</p>
                            </div>
                            <div>
                                <label class="label" for="p-time">{{ __('Time') }}</label>
                                <input id="p-time" type="time" wire:model.live.blur="form.time" class="input" @disabled(! $master)>
                            </div>
                            <div>
                                <label class="label" for="p-owner">{{ __('Owner') }}</label>
                                <select id="p-owner" wire:model="form.owner_id" class="select" @disabled(! $master)>
                                    <option value="">{{ __('Nobody yet') }}</option>
                                    @foreach ($this->people as $person)
                                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="p-due">{{ __('Production due') }}</label>
                                <input id="p-due" type="date" wire:model="form.due_on" class="input" @disabled(! $master)>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label" for="p-brief">{{ __('Brief') }}</label>
                                <textarea id="p-brief" wire:model.blur="form.brief" class="textarea" rows="5" dir="auto" @disabled(! $master)
                                          placeholder="{{ __('Hook (first 2 seconds), on-screen text, product and price, proof, CTA') }}"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Platforms --}}
                    <div @class(['hidden' => $tab !== 'platforms', 'space-y-4']) role="tabpanel">
                        @if ($isCm)
                            <p class="text-muted flex gap-2"><x-icon name="eye" class="size-5" /> {{ __('Placement, ratio and times are set by the admin. You can edit captions and hashtags.') }}</p>
                        @endif
                        @foreach ($this->platforms as $platform)
                            @php
                                $row = $variants[$platform->id];
                            @endphp
                            <section wire:key="variant-{{ $platform->id }}" class="card p-4" aria-label="{{ $platform->name }}">
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="flex items-center gap-2 font-semibold">
                                        <input type="checkbox" wire:model.live="variants.{{ $platform->id }}.enabled" class="checkbox" @disabled(! $master)>
                                        {{ $platform->name }}
                                    </label>
                                    @if ($row['status'] === 'posted')
                                        <span class="chip bg-light text-navy"><x-icon name="check" class="size-4" /> {{ __('Posted') }}</span>
                                        @if ($row['post_url'])<a href="{{ $row['post_url'] }}" target="_blank" rel="noopener" class="text-sm underline">{{ __('See the post') }}</a>@endif
                                    @elseif ($row['status'] === 'skipped')
                                        <span class="chip bg-raised text-muted">{{ __('Skipped') }}</span>
                                    @endif
                                </div>

                                @if ($row['enabled'])
                                    <div class="mt-3 grid gap-3 grid-cols-2 sm:grid-cols-4">
                                        <div>
                                            <label class="label" for="v-pl-{{ $platform->id }}">{{ __('Placement') }}</label>
                                            <select id="v-pl-{{ $platform->id }}" wire:model="variants.{{ $platform->id }}.placement" class="select" @disabled(! $master)>
                                                @foreach (\App\Enums\Format::from($form['format'])->placements($platform->key) as $placement)
                                                    <option value="{{ $placement }}">{{ __(ucfirst($placement)) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="label" for="v-ra-{{ $platform->id }}">{{ __('Ratio') }}</label>
                                            <select id="v-ra-{{ $platform->id }}" wire:model="variants.{{ $platform->id }}.aspect_ratio" class="select" @disabled(! $master)>
                                                @foreach ($ratios as $ratio)<option value="{{ $ratio }}">{{ $ratio }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="label" for="v-d-{{ $platform->id }}">{{ __('Date') }}</label>
                                            <input id="v-d-{{ $platform->id }}" type="date" wire:model="variants.{{ $platform->id }}.date" class="input" @disabled(! $master)>
                                        </div>
                                        <div>
                                            <label class="label" for="v-t-{{ $platform->id }}">{{ __('Time') }}</label>
                                            <input id="v-t-{{ $platform->id }}" type="time" wire:model="variants.{{ $platform->id }}.time" class="input" @disabled(! $master)>
                                        </div>
                                    </div>
                                    <div class="mt-3" x-data="{ text: @js($row['caption']) }">
                                        <div class="flex items-end justify-between gap-2">
                                            <label class="label" for="v-c-{{ $platform->id }}">{{ __('Caption') }}</label>
                                            <span class="text-sm text-muted mb-1" x-text="[...text].length + ' {{ __('characters') }}'"></span>
                                        </div>
                                        <textarea id="v-c-{{ $platform->id }}" wire:model="variants.{{ $platform->id }}.caption" x-model="text" class="textarea" rows="4" dir="auto"></textarea>
                                    </div>
                                    <div class="mt-3">
                                        <label class="label" for="v-h-{{ $platform->id }}">{{ __('Hashtags') }}</label>
                                        <textarea id="v-h-{{ $platform->id }}" wire:model="variants.{{ $platform->id }}.hashtags" class="textarea min-h-[76px]" rows="3" dir="auto" placeholder="#groshop #maroc"></textarea>
                                    </div>
                                    <button type="button" wire:click="copyCaptionToAll({{ $platform->id }})" class="btn btn-ghost btn-sm mt-2 whitespace-normal text-left h-auto py-2"><x-icon name="copy" class="size-4 shrink-0" /> {{ __('Use this caption on every platform') }}</button>
                                @endif
                            </section>
                        @endforeach
                    </div>

                    {{-- Checklist + files --}}
                    @if ($post)
                        <div @class(['hidden' => $tab !== 'production', 'space-y-6']) role="tabpanel">
                            <section aria-labelledby="checklist-title">
                                <h3 id="checklist-title" class="font-display font-semibold mb-2">{{ __('Checklist') }}</h3>
                                <ul class="space-y-1">
                                    @foreach ($post->checklistItems as $item)
                                        <li wire:key="check-{{ $item->id }}" class="flex items-center gap-2 rounded-[10px] hover:bg-light/5 px-2">
                                            <label class="flex items-center gap-3 flex-1 min-h-11 cursor-pointer">
                                                <input type="checkbox" class="checkbox" wire:click="toggleChecklistItem({{ $item->id }})" @checked($item->done)>
                                                <span @class(['line-through text-muted' => $item->done]) dir="auto">{{ __($item->label) }}</span>
                                            </label>
                                            <button type="button" wire:click="removeChecklistItem({{ $item->id }})" class="btn btn-ghost btn-sm px-2" title="{{ __('Remove') }}"><x-icon name="x" class="size-4" /><span class="sr-only">{{ __('Remove') }} {{ __($item->label) }}</span></button>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="mt-2 flex gap-2">
                                    <input wire:model="newChecklist" wire:keydown.enter.prevent="addChecklistItem" class="input" placeholder="{{ __('Add a step') }}" aria-label="{{ __('Add a step') }}" dir="auto">
                                    <button type="button" wire:click="addChecklistItem" class="btn btn-secondary"><x-icon name="plus" class="size-4" /><span class="sr-only">{{ __('Add a step') }}</span></button>
                                </div>
                            </section>

                            <section aria-labelledby="assets-title">
                                <h3 id="assets-title" class="font-display font-semibold mb-2">{{ __('Files (Google Drive links)') }}</h3>
                                <ul class="space-y-2">
                                    @forelse ($post->assets as $asset)
                                        <li wire:key="asset-{{ $asset->id }}" class="flex flex-wrap items-center gap-2 card px-3 py-2">
                                            <x-icon name="link" class="size-4 text-muted" />
                                            <span class="flex-1 min-w-0 truncate font-medium" dir="auto">{{ $asset->label }}</span>
                                            <a href="{{ $asset->url }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icon name="external" class="size-4" /> {{ __('Open') }}</a>
                                            @if ($asset->downloadUrl())
                                                <a href="{{ $asset->downloadUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icon name="download" class="size-4" /> {{ __('Download') }}</a>
                                            @endif
                                            <button type="button" wire:click="removeAsset({{ $asset->id }})" wire:confirm="{{ __('Remove this link?') }}" class="btn btn-ghost btn-sm px-2" title="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Remove') }}</span></button>
                                        </li>
                                    @empty
                                        <li class="text-muted">{{ __('No file yet. Paste the Google Drive link of the final video or visual.') }}</li>
                                    @endforelse
                                </ul>
                                <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <input wire:model="newAsset.label" class="input" placeholder="{{ __('Name, e.g. Final 9:16') }}" aria-label="{{ __('Name') }}" dir="auto">
                                    <input wire:model="newAsset.url" type="url" class="input" placeholder="https://drive.google.com/..." aria-label="{{ __('Link') }}">
                                    <button type="button" wire:click="addAsset" class="btn btn-secondary"><x-icon name="plus" class="size-4" /> {{ __('Add') }}</button>
                                </div>
                            </section>
                        </div>

                        {{-- Comments + history --}}
                        <div @class(['hidden' => $tab !== 'discussion', 'space-y-6']) role="tabpanel">
                            <section aria-labelledby="comments-title">
                                <h3 id="comments-title" class="font-display font-semibold mb-2">{{ __('Comments') }}</h3>
                                <div class="flex gap-2">
                                    <textarea wire:model="newComment" class="textarea min-h-0" rows="2" dir="auto" placeholder="{{ __('Write a comment') }}" aria-label="{{ __('Write a comment') }}"></textarea>
                                    <button type="button" wire:click="addComment" class="btn btn-secondary self-end">{{ __('Send') }}</button>
                                </div>
                                <ul class="mt-4 space-y-3">
                                    @foreach ($post->comments as $comment)
                                        <li wire:key="comment-{{ $comment->id }}" class="flex gap-3">
                                            <x-avatar :user="$comment->user" />
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-muted">{{ $comment->user->name }} · {{ $comment->created_at->diffForHumans() }}
                                                    @if ($comment->user_id === auth()->id() || auth()->user()->isAdmin())
                                                        · <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="{{ __('Delete this comment?') }}" class="underline">{{ __('Delete') }}</button>
                                                    @endif
                                                </p>
                                                <p class="whitespace-pre-line" dir="auto">{{ $comment->body }}</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                            <section aria-labelledby="history-title">
                                <h3 id="history-title" class="font-display font-semibold mb-2">{{ __('History') }}</h3>
                                <ul class="space-y-1 text-[15px]">
                                    @foreach ($this->history as $entry)
                                        <li wire:key="log-{{ $entry->id }}" class="text-muted">
                                            <span class="text-ink">{{ $entry->user?->name ?? 'PostFlow' }}</span>
                                            {{ $entry->description() }}
                                            · {{ $entry->created_at->isoFormat('D MMM, HH:mm') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        </div>
                    @endif
                </form>

                {{-- Footer --}}
                <div class="px-4 sm:px-6 py-3 border-t border-line flex flex-wrap items-center gap-2 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    @if (in_array($tab, ['idea', 'platforms']))
                        <button type="submit" form="post-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</button>
                    @else
                        <span class="text-sm text-muted px-2 min-h-11 inline-flex items-center">{{ __('Saved automatically') }}</span>
                    @endif
                    <button type="button" wire:click="close" class="btn btn-ghost">{{ __('Close') }}</button>
                    @if ($post && $master)
                        <div class="ml-auto flex gap-1">
                            <button type="button" wire:click="duplicate" class="btn btn-ghost btn-sm" title="{{ __('Copy to next week') }}"><x-icon name="copy" class="size-4" /><span class="hidden sm:inline">{{ __('Copy to next week') }}</span></button>
                            <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this post?') }}" class="btn btn-ghost btn-sm" title="{{ __('Delete') }}"><x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Delete') }}</span></button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
