<?php

namespace App\Livewire;

use App\Enums\VariantStatus;
use App\Models\ActivityLog;
use App\Models\PostVariant;
use App\Models\SeasonalEvent;
use App\Support\BrandContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The community manager's day, built for the phone: every platform to post today,
 * in time order, overdue first. Copy, open, post, then "Mark as posted".
 */
class Today extends Component
{
    /** Post URL typed or pasted per platform version: [variant id => url] */
    public array $urls = [];

    /** The card whose "Mark as posted" form is open. */
    public ?int $confirming = null;

    #[On('post-saved')]
    public function refresh(): void {}

    #[Computed]
    public function variants()
    {
        $today = CarbonImmutable::today();

        return $this->query()
            ->where(fn ($q) => $q->whereBetween('publish_at', [$today, $today->endOfDay()])
                ->orWhere(fn ($q) => $q->where('status', VariantStatus::Planned)->where('publish_at', '<', $today)))
            ->orderBy('publish_at')
            ->get();
    }

    #[Computed]
    public function tomorrow()
    {
        $tomorrow = CarbonImmutable::tomorrow();

        return $this->query()->whereBetween('publish_at', [$tomorrow, $tomorrow->endOfDay()])->orderBy('publish_at')->get();
    }

    #[Computed]
    public function events()
    {
        $today = CarbonImmutable::today();

        return SeasonalEvent::forBrands(BrandContext::make()->ids())->overlapping($today, $today)->get();
    }

    public function startPosting(int $variantId): void
    {
        $this->confirming = $variantId;
    }

    public function markPosted(int $variantId): void
    {
        $variant = $this->find($variantId);
        Gate::authorize('markPosted', $variant->post);

        $this->validate(["urls.$variantId" => ['nullable', 'url', 'max:2048']], [], ["urls.$variantId" => __('Post link')]);

        $variant->markPosted(auth()->user(), $this->urls[$variantId] ?? null);
        ActivityLog::record($variant->post, 'posted', ['platform' => $variant->platform->name]);

        $this->confirming = null;
        unset($this->variants);
        $this->dispatch('toast', message: __(':platform marked as posted', ['platform' => $variant->platform->name]));
    }

    public function skip(int $variantId): void
    {
        $variant = $this->find($variantId);
        Gate::authorize('markPosted', $variant->post);

        $variant->update(['status' => VariantStatus::Skipped]);
        $variant->post->syncStageFromVariants();
        ActivityLog::record($variant->post, 'skipped', ['platform' => $variant->platform->name]);

        $this->confirming = null;
        unset($this->variants);
        $this->dispatch('toast', message: __(':platform skipped', ['platform' => $variant->platform->name]));
    }

    public function undo(int $variantId): void
    {
        $variant = $this->find($variantId);
        Gate::authorize('markPosted', $variant->post);

        $variant->update(['status' => VariantStatus::Planned, 'posted_at' => null, 'posted_by' => null]);
        $variant->post->syncStageFromVariants();

        unset($this->variants);
    }

    private function query()
    {
        return PostVariant::query()
            ->with(['platform', 'post.brand.platforms', 'post.series', 'post.assets'])
            ->whereHas('post', fn ($q) => $q->inBrands(BrandContext::make()->ids()));
    }

    private function find(int $variantId): PostVariant
    {
        return $this->query()->findOrFail($variantId);
    }

    public function render()
    {
        return view('livewire.today')->title(__('Today'));
    }
}
