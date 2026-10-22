<?php

namespace App\Livewire;

use App\Enums\Format;
use App\Enums\Stage;
use App\Enums\VariantStatus;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use App\Support\BrandContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The slide-over editor used everywhere: one master idea + one version per platform,
 * production checklist, Drive links, comments and history.
 *
 * Open it from any screen with: $dispatch('open-post', { id: 12 })
 * or for a new post:           $dispatch('new-post', { date: '2026-10-27' })
 */
class PostPanel extends Component
{
    public bool $open = false;

    public ?int $postId = null;

    public string $tab = 'idea';

    public array $form = [];

    /** [platform id => [enabled, id, placement, aspect_ratio, caption, hashtags, date, time, status, post_url]] */
    public array $variants = [];

    public string $newChecklist = '';

    public array $newAsset = ['label' => '', 'url' => ''];

    public string $newComment = '';

    #[On('open-post')]
    public function openPost(int $id): void
    {
        $post = Post::with('variants')->findOrFail($id);
        Gate::authorize('view', $post);

        $this->resetValidation();
        $this->postId = $post->id;
        $this->tab = 'idea';
        $this->fillFrom($post);
        $this->open = true;
    }

    #[On('new-post')]
    public function newPost(?string $date = null, ?int $brandId = null): void
    {
        Gate::authorize('create', Post::class);

        $context = BrandContext::make();
        $brand = ($brandId ? $context->brands()->firstWhere('id', $brandId) : null)
            ?? $context->current()
            ?? $context->brands()->first();
        abort_unless($brand, 404);

        $day = $date ? CarbonImmutable::parse($date) : CarbonImmutable::today();
        $slot = $brand->postingSlots->firstWhere('weekday', $day->isoWeekday());
        $format = $slot?->format->value ?? Format::Video->value;
        $time = $slot?->time ?? '20:00';

        $this->resetValidation();
        $this->postId = null;
        $this->tab = 'idea';
        $this->form = [
            'brand_id' => $brand->id,
            'title' => '',
            'brief' => '',
            'format' => $format,
            'is_offer' => false,
            'series_id' => null,
            'product_family_id' => null,
            'date' => $day->toDateString(),
            'time' => $time,
            'owner_id' => auth()->id(),
            'due_on' => null,
            'stage' => Stage::Brief->value,
        ];
        $this->variants = $this->defaultVariants($brand, $format, $day->toDateString(), $time);
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    #[Computed]
    public function post(): ?Post
    {
        return $this->postId
            ? Post::with(['brand', 'checklistItems', 'assets', 'comments.user'])->find($this->postId)
            : null;
    }

    #[Computed]
    public function brand(): ?Brand
    {
        return isset($this->form['brand_id'])
            ? Brand::with(['platforms', 'series', 'productFamilies', 'users'])->find($this->form['brand_id'])
            : null;
    }

    #[Computed]
    public function platforms()
    {
        return Platform::whereIn('id', array_keys($this->variants))->orderBy('position')->get();
    }

    /** People who can own a post of this brand: admins + its community managers. */
    #[Computed]
    public function people()
    {
        $brand = $this->brand;

        return User::where('role', 'admin')
            ->when($brand, fn ($q) => $q->orWhereIn('id', $brand->users->pluck('id')))
            ->orderBy('name')->get();
    }

    #[Computed]
    public function history()
    {
        return $this->postId
            ? ActivityLog::with('user')->where('subject_type', (new Post)->getMorphClass())->where('subject_id', $this->postId)->latest('id')->limit(30)->get()
            : collect();
    }

    public function canEditMaster(): bool
    {
        return $this->post ? Gate::allows('update', $this->post) : Gate::allows('create', Post::class);
    }

    /** Changing the master date or time moves every platform by the same amount. */
    public function updatingForm($value, $key): void
    {
        if (! in_array($key, ['date', 'time'], true)) {
            return;
        }

        $old = $this->combine($this->form['date'] ?? null, $this->form['time'] ?? null);
        $next = array_merge($this->form, [$key => $value]);
        $new = $this->combine($next['date'] ?? null, $next['time'] ?? null);

        if (! $old || ! $new) {
            return;
        }

        $delta = $new->getTimestamp() - $old->getTimestamp();
        foreach ($this->variants as $platformId => $variant) {
            $current = $this->combine($variant['date'], $variant['time']) ?? $old;
            $shifted = $current->addSeconds($delta);
            $this->variants[$platformId]['date'] = $shifted->toDateString();
            $this->variants[$platformId]['time'] = $shifted->format('H:i');
        }
    }

    public function updatedFormFormat(): void
    {
        foreach ($this->variants as $platformId => $variant) {
            $key = Platform::find($platformId)->key;
            $options = Format::from($this->form['format'])->placements($key);
            if (! in_array($variant['placement'], $options, true)) {
                $this->variants[$platformId]['placement'] = $options[0];
                $this->variants[$platformId]['aspect_ratio'] = Format::from($this->form['format'])->defaultRatio($options[0]);
            }
        }
    }

    public function updatedFormBrandId(): void
    {
        if ($this->postId || ! $this->brand) {
            return;
        }

        $this->form['series_id'] = null;
        $this->form['product_family_id'] = null;
        $this->variants = $this->defaultVariants($this->brand, $this->form['format'], $this->form['date'], $this->form['time']);
    }

    /** Copy one platform's caption and hashtags to every other platform of this post. */
    public function copyCaptionToAll(int $fromPlatformId): void
    {
        $source = $this->variants[$fromPlatformId] ?? null;
        abort_unless($source, 404);

        foreach ($this->variants as $platformId => $variant) {
            if ($variant['enabled']) {
                $this->variants[$platformId]['caption'] = $source['caption'];
                $this->variants[$platformId]['hashtags'] = $source['hashtags'];
            }
        }

        $this->dispatch('toast', message: __('Caption copied to every platform. Save to keep it.'));
    }

    public function save(): void
    {
        $post = $this->postId ? Post::findOrFail($this->postId) : null;
        $post ? Gate::authorize('editCopy', $post) : Gate::authorize('create', Post::class);
        $master = $this->canEditMaster();

        $this->validate($this->rules($master));

        $newStage = Stage::from($this->form['stage']);
        if ($post && $newStage !== $post->stage) {
            Gate::authorize('changeStage', [$post, $newStage]);
        }

        $post = DB::transaction(function () use ($post, $master, $newStage) {
            $post ??= new Post(['created_by' => auth()->id()]);
            $original = $post->exists ? $post->only(['title', 'planned_at', 'stage', 'owner_id', 'format']) : null;

            if ($master) {
                $post->fill([
                    'brand_id' => $this->form['brand_id'],
                    'title' => $this->form['title'],
                    'brief' => $this->form['brief'] ?: null,
                    'format' => $this->form['format'],
                    'is_offer' => (bool) $this->form['is_offer'],
                    'series_id' => $this->form['series_id'] ?: null,
                    'product_family_id' => $this->form['product_family_id'] ?: null,
                    'planned_at' => $this->combine($this->form['date'], $this->form['time']),
                    'owner_id' => $this->form['owner_id'] ?: null,
                    'due_on' => $this->form['due_on'] ?: null,
                ]);
            }
            $post->stage = $newStage;
            $isNew = ! $post->exists;
            $post->save();

            if ($isNew) {
                foreach (Post::CHECKLIST[$post->format->value] ?? [] as $position => $label) {
                    $post->checklistItems()->create(['label' => __($label), 'position' => $position]);
                }
            }

            foreach ($this->variants as $platformId => $row) {
                $variant = $row['id'] ? $post->variants()->find($row['id']) : null;

                if (! $row['enabled']) {
                    if ($master && $variant) {
                        $variant->delete();
                    }

                    continue;
                }

                if (! $variant && ! $master) {
                    continue;
                }

                $variant ??= $post->variants()->make(['platform_id' => $platformId]);
                $variant->caption = $row['caption'] ?: null;
                $variant->hashtags = $row['hashtags'] ?: null;

                if ($master) {
                    $publishAt = $this->combine($row['date'], $row['time']);
                    if ($variant->publish_at?->ne($publishAt)) {
                        $variant->reminded_at = null;
                        $variant->overdue_notified_at = null;
                    }
                    $variant->placement = $row['placement'];
                    $variant->aspect_ratio = $row['aspect_ratio'];
                    $variant->publish_at = $publishAt;
                }
                $variant->save();
            }

            // Which of the important fields changed, for the history tab.
            $changes = [];
            foreach ($original ?? [] as $field => $before) {
                $after = $post->{$field};
                $asText = fn ($value) => (string) ($value instanceof \BackedEnum ? $value->value : $value);
                if ($asText($before) !== $asText($after)) {
                    $changes[] = $field;
                }
            }
            ActivityLog::record($post, $isNew ? 'created' : 'updated', $changes ?: null);

            return $post;
        });

        $post->syncStageFromVariants();

        $this->postId = $post->id;
        $this->fillFrom($post->fresh('variants'));
        unset($this->post, $this->history);

        $this->dispatch('post-saved');
        $this->dispatch('toast', message: __('Post saved'));
    }

    public function duplicate(): void
    {
        $post = Post::with(['variants', 'checklistItems'])->findOrFail($this->postId);
        Gate::authorize('create', Post::class);

        $copy = DB::transaction(function () use ($post) {
            $copy = $post->replicate(['stage', 'created_by']);
            $copy->title = $post->title;
            $copy->stage = Stage::Brief;
            $copy->created_by = auth()->id();
            $copy->planned_at = $post->planned_at?->copy()->addWeek();
            $copy->due_on = $post->due_on?->copy()->addWeek();
            $copy->save();

            foreach ($post->variants as $variant) {
                $copy->variants()->create([
                    'platform_id' => $variant->platform_id,
                    'placement' => $variant->placement,
                    'aspect_ratio' => $variant->aspect_ratio,
                    'caption' => $variant->caption,
                    'hashtags' => $variant->hashtags,
                    'publish_at' => $variant->publish_at?->copy()->addWeek(),
                ]);
            }
            foreach ($post->checklistItems as $item) {
                $copy->checklistItems()->create(['label' => $item->label, 'position' => $item->position]);
            }
            ActivityLog::record($copy, 'duplicated', ['from' => $post->id]);

            return $copy;
        });

        $this->openPost($copy->id);
        $this->dispatch('post-saved');
        $this->dispatch('toast', message: __('Copied to the same day next week'));
    }

    public function delete(): void
    {
        $post = Post::findOrFail($this->postId);
        Gate::authorize('delete', $post);

        ActivityLog::record($post, 'deleted');
        $post->delete();

        $this->open = false;
        $this->postId = null;
        $this->dispatch('post-saved');
        $this->dispatch('toast', message: __('Post deleted'));
    }

    // Checklist -------------------------------------------------------------

    public function addChecklistItem(): void
    {
        Gate::authorize('editCopy', $this->post);
        $this->validate(['newChecklist' => 'required|string|max:120']);

        $this->post->checklistItems()->create([
            'label' => $this->newChecklist,
            'position' => (int) $this->post->checklistItems->max('position') + 1,
        ]);
        $this->newChecklist = '';
        unset($this->post);
        $this->dispatch('post-saved');
    }

    public function toggleChecklistItem(int $id): void
    {
        Gate::authorize('editCopy', $this->post);

        $item = $this->post->checklistItems()->findOrFail($id);
        $item->update(['done' => ! $item->done, 'done_by' => $item->done ? null : auth()->id()]);
        unset($this->post);
        $this->dispatch('post-saved');
    }

    public function removeChecklistItem(int $id): void
    {
        Gate::authorize('editCopy', $this->post);

        $this->post->checklistItems()->whereKey($id)->delete();
        unset($this->post);
        $this->dispatch('post-saved');
    }

    // Assets (Google Drive links) ------------------------------------------

    public function addAsset(): void
    {
        Gate::authorize('editCopy', $this->post);
        $data = $this->validate([
            'newAsset.label' => 'nullable|string|max:80',
            'newAsset.url' => 'required|url|max:2048',
        ])['newAsset'];

        $this->post->assets()->create(['label' => $data['label'] ?: __('Drive file'), 'url' => $data['url']]);
        ActivityLog::record($this->post, 'asset_added');
        $this->newAsset = ['label' => '', 'url' => ''];
        unset($this->post, $this->history);
    }

    public function removeAsset(int $id): void
    {
        Gate::authorize('editCopy', $this->post);

        $this->post->assets()->whereKey($id)->delete();
        unset($this->post);
    }

    // Comments ----------------------------------------------------------------

    public function addComment(): void
    {
        Gate::authorize('editCopy', $this->post);
        $this->validate(['newComment' => 'required|string|max:2000']);

        $this->post->comments()->create(['user_id' => auth()->id(), 'body' => $this->newComment]);
        $this->newComment = '';
        unset($this->post);
    }

    public function deleteComment(int $id): void
    {
        $comment = $this->post->comments()->findOrFail($id);
        abort_unless($comment->user_id === auth()->id() || auth()->user()->isAdmin(), 403);

        $comment->delete();
        unset($this->post);
    }

    // Helpers -----------------------------------------------------------------

    private function rules(bool $master): array
    {
        $rules = [
            'form.stage' => ['required', Rule::enum(Stage::class)],
            'variants.*.caption' => ['nullable', 'string', 'max:5000'],
            'variants.*.hashtags' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $master) {
            return $rules;
        }

        return $rules + [
            'form.brand_id' => ['required', Rule::in(BrandContext::make()->brands()->pluck('id'))],
            'form.title' => ['required', 'string', 'max:160'],
            'form.brief' => ['nullable', 'string', 'max:5000'],
            'form.format' => ['required', Rule::enum(Format::class)],
            'form.is_offer' => ['boolean'],
            'form.series_id' => ['nullable', Rule::exists('series', 'id')->where('brand_id', $this->form['brand_id'] ?? 0)],
            'form.product_family_id' => ['nullable', Rule::exists('product_families', 'id')->where('brand_id', $this->form['brand_id'] ?? 0)],
            'form.date' => ['nullable', 'date'],
            'form.time' => ['nullable', 'date_format:H:i'],
            'form.owner_id' => ['nullable', 'exists:users,id'],
            'form.due_on' => ['nullable', 'date'],
            'variants.*.placement' => ['nullable', 'string', 'max:20'],
            'variants.*.aspect_ratio' => ['nullable', 'in:9:16,4:5,1:1,16:9'],
            'variants.*.date' => ['nullable', 'date'],
            'variants.*.time' => ['nullable', 'date_format:H:i'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.title' => __('Title'),
            'form.brand_id' => __('Brand'),
            'newAsset.url' => __('Link'),
            'newChecklist' => __('Checklist item'),
            'newComment' => __('Comment'),
        ];
    }

    private function fillFrom(Post $post): void
    {
        $this->form = [
            'brand_id' => $post->brand_id,
            'title' => $post->title,
            'brief' => $post->brief ?? '',
            'format' => $post->format->value,
            'is_offer' => $post->is_offer,
            'series_id' => $post->series_id,
            'product_family_id' => $post->product_family_id,
            'date' => $post->planned_at?->toDateString(),
            'time' => $post->planned_at?->format('H:i'),
            'owner_id' => $post->owner_id,
            'due_on' => $post->due_on?->toDateString(),
            'stage' => $post->stage->value,
        ];

        $brand = Brand::with('platforms')->find($post->brand_id);
        $this->variants = $this->defaultVariants($brand, $post->format->value, $this->form['date'], $this->form['time'], enabled: false);

        foreach ($post->variants as $variant) {
            $this->variants[$variant->platform_id] = [
                'enabled' => true,
                'id' => $variant->id,
                'placement' => $variant->placement,
                'aspect_ratio' => $variant->aspect_ratio,
                'caption' => $variant->caption ?? '',
                'hashtags' => $variant->hashtags ?? '',
                'date' => $variant->publish_at?->toDateString(),
                'time' => $variant->publish_at?->format('H:i'),
                'status' => $variant->status->value,
                'post_url' => $variant->post_url,
            ];
        }
        ksort($this->variants);
    }

    private function defaultVariants(Brand $brand, string $format, ?string $date, ?string $time, bool $enabled = true): array
    {
        $variants = [];

        foreach ($brand->platforms as $platform) {
            $placement = Format::from($format)->placements($platform->key)[0];
            $variants[$platform->id] = [
                'enabled' => $enabled,
                'id' => null,
                'placement' => $placement,
                'aspect_ratio' => Format::from($format)->defaultRatio($placement),
                'caption' => '',
                'hashtags' => '',
                'date' => $date,
                'time' => $time,
                'status' => VariantStatus::Planned->value,
                'post_url' => null,
            ];
        }

        return $variants;
    }

    private function combine(?string $date, ?string $time): ?CarbonImmutable
    {
        return $date ? CarbonImmutable::parse($date.' '.($time ?: '20:00')) : null;
    }

    public function render()
    {
        return view('livewire.post-panel');
    }
}
