<?php

namespace App\Livewire;

use App\Enums\Stage;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\Series;
use App\Models\User;
use App\Support\BrandContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Production pipeline: Brief -> Shoot -> Edit -> Design -> Review -> Scheduled -> Posted.
 * Drag a card to another column, or tap the arrow to send it to the next stage.
 */
class Pipeline extends Component
{
    #[Url]
    public string $view = 'board';

    #[Url]
    public string $owner = '';

    #[Url]
    public string $series = '';

    #[Url]
    public string $format = '';

    /** Posted cards older than this many days are hidden to keep the board short. */
    public const POSTED_DAYS = 14;

    #[On('post-saved')]
    public function refresh(): void {}

    #[Computed]
    public function posts()
    {
        return Post::inBrands(BrandContext::make()->ids())
            ->with(['brand', 'series', 'owner', 'variants.platform', 'checklistItems'])
            ->withCount(['assets', 'comments'])
            ->where(fn ($q) => $q->where('stage', '!=', Stage::Posted)->orWhere('planned_at', '>=', now()->subDays(self::POSTED_DAYS)))
            ->when($this->owner === 'me', fn ($q) => $q->where('owner_id', auth()->id()))
            ->when($this->owner && $this->owner !== 'me', fn ($q) => $q->where('owner_id', $this->owner))
            ->when($this->series, fn ($q) => $q->where('series_id', $this->series))
            ->when(in_array($this->format, ['video', 'static']), fn ($q) => $q->where('format', $this->format))
            ->when($this->format === 'offer', fn ($q) => $q->where('is_offer', true))
            // Dated posts first, soonest first; ideas without a date last.
            ->orderByRaw('planned_at is null')
            ->orderBy('planned_at')
            ->get();
    }

    #[Computed]
    public function columns()
    {
        return $this->posts->groupBy(fn (Post $post) => $post->stage->value);
    }

    #[Computed]
    public function filterOptions(): array
    {
        return [
            'owners' => User::orderBy('name')->get(),
            'series' => Series::whereIn('brand_id', BrandContext::make()->ids())->with('brand')->orderBy('brand_id')->orderBy('position')->get(),
        ];
    }

    /** Drop handler: $stage is the column the card was dropped in. */
    public function handleSort($postId, $position, $stage = null): void
    {
        $this->moveTo((int) $postId, Stage::tryFrom((string) $stage));
    }

    /** The arrow button on a card (handy on a phone). */
    public function advance(int $postId): void
    {
        $post = Post::inBrands(BrandContext::make()->ids())->findOrFail($postId);
        $next = Stage::cases()[$post->stage->position() + 1] ?? null;

        $this->moveTo($postId, $next);
    }

    public function setStage(int $postId, string $stage): void
    {
        $this->moveTo($postId, Stage::tryFrom($stage));
    }

    private function moveTo(int $postId, ?Stage $stage): void
    {
        $post = Post::inBrands(BrandContext::make()->ids())->findOrFail($postId);

        if (! $stage || $post->stage === $stage) {
            return;
        }

        if (Gate::denies('changeStage', [$post, $stage])) {
            unset($this->posts, $this->columns);
            $this->dispatch('toast', message: __('Only the admin can move a post to :stage.', ['stage' => $stage->label()]));

            return;
        }

        $post->update(['stage' => $stage]);
        ActivityLog::record($post, 'stage', ['to' => $stage->value]);

        unset($this->posts, $this->columns);
        $this->dispatch('post-saved');
        $this->dispatch('toast', message: __(':title moved to :stage', ['title' => $post->title, 'stage' => $stage->label()]));
    }

    public function render()
    {
        return view('livewire.pipeline')->title(__('Pipeline'));
    }
}
