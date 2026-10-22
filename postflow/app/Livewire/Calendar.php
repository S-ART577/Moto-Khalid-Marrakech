<?php

namespace App\Livewire;

use App\Enums\Stage;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\MonthNote;
use App\Models\Platform;
use App\Models\Post;
use App\Models\SeasonalEvent;
use App\Models\Series;
use App\Models\User;
use App\Support\BrandContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Month / week / list calendar. Drag a post to another day to move it
 * (every platform keeps its own time). Admin only for moving.
 */
class Calendar extends Component
{
    #[Url]
    public string $view = 'month';

    #[Url]
    public string $date = '';

    #[Url]
    public string $platform = '';

    #[Url]
    public string $series = '';

    #[Url]
    public string $owner = '';

    #[Url]
    public string $stage = '';

    public bool $editingNotes = false;

    public array $notes = [];

    public function mount(): void
    {
        if (! in_array($this->view, ['month', 'week', 'list'], true)) {
            $this->view = 'month';
        }
    }

    #[On('post-saved')]
    public function refresh(): void
    {
        // Re-render with fresh data.
    }

    #[Computed]
    public function anchor(): CarbonImmutable
    {
        try {
            return $this->date ? CarbonImmutable::parse($this->date) : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day shown */
    #[Computed]
    public function range(): array
    {
        $anchor = $this->anchor;

        return match ($this->view) {
            'week' => [$anchor->startOfWeek(CarbonInterface::MONDAY), $anchor->endOfWeek(CarbonInterface::SUNDAY)->startOfDay()],
            'list' => [$anchor->startOfMonth(), $anchor->endOfMonth()->startOfDay()],
            default => [
                $anchor->startOfMonth()->startOfWeek(CarbonInterface::MONDAY),
                $anchor->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay(),
            ],
        };
    }

    /** @return array<int, CarbonImmutable> */
    #[Computed]
    public function days(): array
    {
        [$from, $to] = $this->range;
        $days = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    #[Computed]
    public function posts()
    {
        [$from, $to] = $this->range;

        return $this->filtered(Post::query())
            ->whereBetween('planned_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('planned_at')
            ->get();
    }

    /** Ideas without a date: drag them onto a day to schedule them. */
    #[Computed]
    public function ideas()
    {
        return $this->filtered(Post::query())->whereNull('planned_at')->latest()->limit(40)->get();
    }

    #[Computed]
    public function postsByDay()
    {
        return $this->posts->groupBy(fn (Post $post) => $post->planned_at->toDateString());
    }

    #[Computed]
    public function events()
    {
        [$from, $to] = $this->range;

        return SeasonalEvent::forBrands(BrandContext::make()->ids())->overlapping($from, $to)->orderBy('starts_on')->get();
    }

    /** Counts for the month header: 6 posts, 4 videos, 2 static, 1 offer. */
    #[Computed]
    public function stats(): array
    {
        $month = $this->anchor->format('Y-m');
        $posts = $this->posts->filter(fn (Post $post) => $post->planned_at->format('Y-m') === $month || $this->view === 'week');

        return [
            'posts' => $posts->count(),
            'videos' => $posts->where('format', \App\Enums\Format::Video)->count(),
            'static' => $posts->where('format', \App\Enums\Format::Static)->count(),
            'offers' => $posts->where('is_offer', true)->count(),
        ];
    }

    #[Computed]
    public function brand(): ?Brand
    {
        return BrandContext::make()->current()?->load('postingSlots');
    }

    #[Computed]
    public function monthNote(): ?MonthNote
    {
        return $this->brand?->monthNotes()->whereDate('month', $this->anchor->startOfMonth())->first();
    }

    #[Computed]
    public function filterOptions(): array
    {
        $ids = BrandContext::make()->ids();

        return [
            'platforms' => Platform::orderBy('position')->get(),
            'series' => Series::whereIn('brand_id', $ids)->with('brand')->orderBy('brand_id')->orderBy('position')->get(),
            'owners' => User::orderBy('name')->get(),
        ];
    }

    public function go(string $direction): void
    {
        $week = $this->view === 'week';

        $this->date = match ($direction) {
            'prev' => ($week ? $this->anchor->subWeek() : $this->anchor->startOfMonth()->subMonth())->toDateString(),
            'next' => ($week ? $this->anchor->addWeek() : $this->anchor->startOfMonth()->addMonth())->toDateString(),
            default => CarbonImmutable::today()->toDateString(),
        };
    }

    public function clearFilters(): void
    {
        $this->reset('platform', 'series', 'owner', 'stage');
    }

    /** Drop handler: $target is a date (Y-m-d) or "ideas". */
    public function handleSort($postId, $position, $target = null): void
    {
        $post = Post::inBrands(BrandContext::make()->ids())->with('variants')->findOrFail($postId);
        Gate::authorize('update', $post);

        if ($target === 'ideas' || $target === null) {
            if ($post->planned_at === null) {
                return;
            }
            $post->update(['planned_at' => null]);
            $post->variants()->update(['publish_at' => null]);
            ActivityLog::record($post, 'moved', ['to' => __('Ideas')]);
            $this->dispatch('toast', message: __('Moved to ideas'));

            return;
        }

        $day = CarbonImmutable::parse($target);
        if ($post->planned_at?->isSameDay($day)) {
            return;
        }

        $time = $post->planned_at?->format('H:i')
            ?? $post->brand->postingSlots()->where('weekday', $day->isoWeekday())->value('time')
            ?? '20:00';

        $post->moveTo($day->setTimeFromTimeString($time));
        ActivityLog::record($post, 'moved', ['to' => $day->isoFormat('ddd D MMM')]);

        $this->dispatch('post-saved');
        $this->dispatch('toast', message: __('Moved to :date', ['date' => $day->isoFormat('dddd D MMMM')]));
    }

    /**
     * Create a draft for every slot of the weekly rhythm that has no post yet,
     * from today to the end of the month shown.
     */
    public function fillEmptySlots(): void
    {
        Gate::authorize('admin');

        $context = BrandContext::make();
        $brands = $context->brands()->whereIn('id', $context->ids())->load(['postingSlots', 'platforms']);
        $from = CarbonImmutable::today()->max($this->anchor->startOfMonth());
        $to = $this->anchor->endOfMonth()->startOfDay();
        $created = 0;

        foreach ($brands as $brand) {
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                foreach ($brand->postingSlots->where('weekday', $day->isoWeekday()) as $slot) {
                    $taken = $brand->posts()
                        ->whereBetween('planned_at', [$day->startOfDay(), $day->endOfDay()])
                        ->where('format', $slot->format)
                        ->exists();

                    if (! $taken) {
                        Post::draft($brand, $slot->format, $day->setTimeFromTimeString($slot->time));
                        $created++;
                    }
                }
            }
        }

        $this->dispatch('toast', message: $created
            ? trans_choice(':count draft created from your weekly rhythm|:count drafts created from your weekly rhythm', $created)
            : __('Every slot of the month already has a post.'));
    }

    public function editNotes(): void
    {
        Gate::authorize('admin');
        abort_unless($this->brand, 400);

        $note = $this->monthNote;
        $this->notes = [
            'theme_title' => $note?->theme_title, 'theme_body' => $note?->theme_body,
            'offer_title' => $note?->offer_title, 'offer_body' => $note?->offer_body,
            'dates_title' => $note?->dates_title, 'dates_body' => $note?->dates_body,
        ];
        $this->editingNotes = true;
    }

    public function saveNotes(): void
    {
        Gate::authorize('admin');
        abort_unless($this->brand, 400);

        $data = $this->validate([
            'notes.theme_title' => 'nullable|string|max:120',
            'notes.theme_body' => 'nullable|string|max:600',
            'notes.offer_title' => 'nullable|string|max:120',
            'notes.offer_body' => 'nullable|string|max:600',
            'notes.dates_title' => 'nullable|string|max:120',
            'notes.dates_body' => 'nullable|string|max:600',
        ])['notes'];

        MonthNote::updateOrCreate(
            ['brand_id' => $this->brand->id, 'month' => $this->anchor->startOfMonth()->toDateString()],
            $data
        );

        $this->editingNotes = false;
        unset($this->monthNote);
        $this->dispatch('toast', message: __('Saved'));
    }

    private function filtered(Builder $query): Builder
    {
        return $query->inBrands(BrandContext::make()->ids())
            ->with(['brand', 'series', 'productFamily', 'owner', 'variants.platform', 'checklistItems'])
            ->when($this->platform, fn ($q) => $q->whereHas('variants.platform', fn ($q) => $q->where('key', $this->platform)))
            ->when($this->series, fn ($q) => $q->where('series_id', $this->series))
            ->when($this->owner, fn ($q) => $q->where('owner_id', $this->owner))
            ->when($this->stage && Stage::tryFrom($this->stage), fn ($q) => $q->where('stage', $this->stage));
    }

    public function render()
    {
        return view('livewire.calendar')->title(__('Calendar'));
    }
}
