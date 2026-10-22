<?php

namespace App\Support;

use App\Enums\Stage;
use App\Enums\VariantStatus;
use App\Models\Brand;
use App\Models\Post;
use App\Models\Quota;
use App\Models\SeasonalEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The "calculate" part: compares what is planned with the targets of each brand
 * and writes plain warnings ("Week of 26 Oct: 1 video missing").
 *
 * Warnings are always about now and the coming weeks. The grid and the
 * content mix follow the month you are looking at.
 */
class Radar
{
    /** How far ahead the radar looks for missing posts. */
    public const WEEKS_AHEAD = 4;

    private CarbonImmutable $now;

    /** @param  Collection<int, Brand>  $brands */
    public function __construct(private Collection $brands, ?CarbonImmutable $now = null)
    {
        $this->now = $now ?? CarbonImmutable::now();
        (new \Illuminate\Database\Eloquent\Collection($this->brands->all()))->load(['quotas', 'productFamilies', 'series', 'platforms']);
    }

    // Warnings ------------------------------------------------------------------

    /**
     * @return array<int, array{level: string, icon: string, brand: ?Brand, text: string, post_id: ?int}>
     */
    public function warnings(): array
    {
        $warnings = [];

        foreach ($this->brands as $brand) {
            array_push($warnings, ...$this->overdue($brand), ...$this->readiness($brand), ...$this->weekGaps($brand), ...$this->monthGaps($brand), ...$this->familyGaps($brand));
        }

        array_push($warnings, ...$this->seasons());

        $order = ['urgent' => 0, 'soon' => 1, 'gap' => 2, 'info' => 3];
        usort($warnings, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return $warnings;
    }

    /** Platforms whose publish time has passed without being marked as posted. */
    private function overdue(Brand $brand): array
    {
        $posts = $brand->posts()->with('variants.platform')
            ->whereHas('variants', fn ($q) => $q->where('status', VariantStatus::Planned)->where('publish_at', '<', $this->now))
            ->get();

        return $posts->map(fn (Post $post) => $this->warning('urgent', 'alert', $brand, __(':platforms not marked as posted', [
            'platforms' => $post->variants->filter->isOverdue()->map(fn ($v) => $v->platform->name)->implode(', '),
        ]), $post->id, $this->subject($post)))->all();
    }

    /** What is still missing on the posts of the next 7 days: captions, files, production. */
    private function readiness(Brand $brand): array
    {
        $posts = $brand->posts()->with(['variants.platform', 'assets'])
            ->whereBetween('planned_at', [$this->now, $this->now->addDays(7)->endOfDay()])
            ->orderBy('planned_at')
            ->get();

        $warnings = [];
        foreach ($posts as $post) {
            $issues = [];

            $noCaption = $post->variants->filter(fn ($v) => $v->status === VariantStatus::Planned && blank($v->caption));
            if ($noCaption->isNotEmpty()) {
                $issues[] = __('no caption for :platforms', ['platforms' => $noCaption->map(fn ($v) => $v->platform->name)->implode(', ')]);
            }
            if ($post->assets->isEmpty()) {
                $issues[] = __('no file on Drive yet');
            }
            if ($post->isProductionLate()) {
                $issues[] = __('production was due :date', ['date' => $post->due_on->isoFormat('ddd D MMM')]);
            } elseif ($post->stage->position() < Stage::Scheduled->position() && $post->planned_at->lte($this->now->addHours(48))) {
                $issues[] = __('still in :stage', ['stage' => $post->stage->label()]);
            }

            if ($issues) {
                $level = $post->planned_at->lte($this->now->addHours(48)) ? 'urgent' : 'soon';
                $warnings[] = $this->warning($level, 'clock', $brand, implode(' · ', $issues), $post->id, $this->subject($post));
            }
        }

        return $warnings;
    }

    /** Weekly targets for this week and the next ones. */
    private function weekGaps(Brand $brand): array
    {
        $weekQuotas = $brand->quotas->where('period', 'week');
        if ($weekQuotas->isEmpty()) {
            return [];
        }

        $warnings = [];
        $firstWeek = $this->now->startOfWeek(CarbonInterface::MONDAY);

        for ($i = 0; $i < self::WEEKS_AHEAD; $i++) {
            $week = $firstWeek->addWeeks($i);
            $posts = $this->postsBetween($brand, $week, $week->endOfWeek(CarbonInterface::SUNDAY));
            $label = $i === 0 ? __('This week') : __('Week of :date', ['date' => $week->isoFormat('D MMM')]);

            $total = $weekQuotas->firstWhere('dimension.value', 'total');
            if ($posts->isEmpty() && $total) {
                $warnings[] = $this->warning('gap', 'calendar-plus', $brand, $label.': '.trans_choice(
                    'nothing planned yet, :count post expected|nothing planned yet, :count posts expected', $total->target
                ));

                continue;
            }

            $short = $weekQuotas
                ->map(fn (Quota $quota) => [$quota, $quota->target - $quota->countIn($posts)])
                ->filter(fn ($row) => $row[1] > 0);

            // "1 post missing, 1 video missing, 1 TikTok post missing" says the same thing three times:
            // keep "1 video missing". Platform lines stay only when a platform is short on its own.
            $formatShort = $short->filter(fn ($row) => $row[0]->dimension->value === 'format')->sum(fn ($row) => $row[1]);
            $totalShort = $short->first(fn ($row) => $row[0]->dimension->value === 'total')[1] ?? 0;
            $postsShort = max($formatShort, $totalShort);
            $short = $short
                ->reject(fn ($row) => $row[0]->dimension->value === 'total' && $formatShort >= $row[1])
                ->reject(fn ($row) => $row[0]->dimension->value === 'platform' && $row[1] <= $postsShort);

            if ($short->isNotEmpty()) {
                $warnings[] = $this->warning('gap', 'calendar-plus', $brand, $label.': '.$short->map(fn ($row) => $row[0]->missingLabel($row[1]))->implode(', '));
            }
        }

        return $warnings;
    }

    /** Monthly targets for the current month (only what can still be planned). */
    private function monthGaps(Brand $brand): array
    {
        $monthQuotas = $brand->quotas->where('period', 'month');
        if ($monthQuotas->isEmpty()) {
            return [];
        }

        $posts = $this->postsBetween($brand, $this->now->startOfMonth(), $this->now->endOfMonth());
        $label = ucfirst($this->now->isoFormat('MMMM'));

        $missing = $monthQuotas
            ->map(fn (Quota $quota) => [$quota, $quota->target - $quota->countIn($posts)])
            ->filter(fn ($row) => $row[1] > 0)
            ->map(fn ($row) => $row[0]->missingLabel($row[1]));

        return $missing->isEmpty() ? [] : [$this->warning('gap', 'calendar-plus', $brand, $label.': '.$missing->implode(', '))];
    }

    /** "Smartphones not featured for 3 weeks". A post planned in the next 7 days counts. */
    private function familyGaps(Brand $brand): array
    {
        $warnings = [];

        foreach ($brand->productFamilies->where('active', true) as $family) {
            $last = $brand->posts()->where('product_family_id', $family->id)
                ->where('planned_at', '<=', $this->now->addDays(7))
                ->max('planned_at');

            if ($last === null) {
                $warnings[] = $this->warning('gap', 'tag', $brand, __(':family not featured yet', ['family' => $family->name]));

                continue;
            }

            $weeks = (int) floor(CarbonImmutable::parse($last)->diffInDays($this->now, false) / 7);
            if ($weeks >= $family->max_gap_weeks) {
                $warnings[] = $this->warning('gap', 'tag', $brand, trans_choice(':family not featured for :count week|:family not featured for :count weeks', $weeks, ['family' => $family->name]));
            }
        }

        return $warnings;
    }

    /** Seasonal dates whose shooting or design deadline is coming up. */
    private function seasons(): array
    {
        $ids = $this->brands->pluck('id')->all();

        return SeasonalEvent::forBrands($ids)
            ->where('starts_on', '>', $this->now->toDateString())
            ->where('starts_on', '<=', $this->now->addDays(60)->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->filter(fn (SeasonalEvent $event) => $event->shootBy()->lte($this->now->addDays(21)))
            ->map(function (SeasonalEvent $event) {
                $shoot = $event->shootBy()->lt($this->now->startOfDay())
                    ? __('shoot date passed (:date)', ['date' => $event->shootBy()->isoFormat('D MMM')])
                    : __('shoot by :date', ['date' => $event->shootBy()->isoFormat('ddd D MMM')]);

                return $this->warning('info', 'flag', null, trans_choice(':event in :count day|:event in :count days', $event->daysUntil(), ['event' => $event->name()])
                    .': '.$shoot.', '.__('design by :date', ['date' => $event->designBy()->isoFormat('ddd D MMM')]));
            })
            ->values()
            ->all();
    }

    // Month grid and content mix ------------------------------------------------

    /** @return array<int, CarbonImmutable> Mondays of the weeks that touch the month */
    public function weeksOf(CarbonImmutable $month): array
    {
        $weeks = [];
        for ($week = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY); $week->lte($month->endOfMonth()); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * Weekly targets of a brand, week by week, for the month.
     *
     * @return array<int, array{label: string, cells: array<int, array{planned: int, target: int, past: bool}>}>
     */
    public function weekGrid(Brand $brand, CarbonImmutable $month): array
    {
        $weeks = $this->weeksOf($month);
        $postsByWeek = collect($weeks)->map(fn ($week) => $this->postsBetween($brand, $week, $week->endOfWeek(CarbonInterface::SUNDAY)));
        $currentWeek = $this->now->startOfWeek(CarbonInterface::MONDAY);

        return $brand->quotas->where('period', 'week')->map(fn (Quota $quota) => [
            'label' => $quota->refLabel(),
            'cells' => collect($weeks)->map(fn ($week, $i) => [
                'planned' => $quota->countIn($postsByWeek[$i]),
                'target' => $quota->target,
                'past' => $week->lt($currentWeek),
            ])->all(),
        ])->values()->all();
    }

    /**
     * Planned vs target for the month: formats, offers, series, product families.
     * Weekly targets are converted to the month (target x days / 7).
     *
     * @return array<int, array{group: string, label: string, planned: int, target: ?int}>
     */
    public function mix(Brand $brand, CarbonImmutable $month): array
    {
        $posts = $this->postsBetween($brand, $month->startOfMonth(), $month->endOfMonth());
        $weeksInMonth = $month->daysInMonth / 7;

        $target = function (string $dimension, ?string $ref) use ($brand, $weeksInMonth): ?int {
            $quotas = $brand->quotas->filter(fn (Quota $q) => $q->dimension->value === $dimension && (string) $q->dimension_ref === (string) $ref);
            $monthly = $quotas->firstWhere('period', 'month');
            $weekly = $quotas->firstWhere('period', 'week');

            return $monthly?->target ?? ($weekly ? (int) round($weekly->target * $weeksInMonth) : null);
        };

        $rows = [
            ['group' => __('Format'), 'label' => __('Videos'), 'planned' => $posts->filter(fn ($p) => $p->format->value === 'video')->count(), 'target' => $target('format', 'video')],
            ['group' => __('Format'), 'label' => __('Static posts'), 'planned' => $posts->filter(fn ($p) => $p->format->value === 'static')->count(), 'target' => $target('format', 'static')],
            ['group' => __('Format'), 'label' => __('Offers'), 'planned' => $posts->where('is_offer', true)->count(), 'target' => $target('offer', null)],
        ];

        foreach ($brand->series as $series) {
            $rows[] = ['group' => __('Series'), 'label' => $series->name, 'planned' => $posts->where('series_id', $series->id)->count(), 'target' => $target('series', (string) $series->id)];
        }
        foreach ($brand->productFamilies as $family) {
            $rows[] = ['group' => __('Product families'), 'label' => $family->name, 'planned' => $posts->where('product_family_id', $family->id)->count(), 'target' => $target('product_family', (string) $family->id)];
        }

        // Rows with no target and nothing planned add noise.
        return array_values(array_filter($rows, fn ($row) => $row['target'] !== null || $row['planned'] > 0));
    }

    // Helpers -------------------------------------------------------------------

    private function postsBetween(Brand $brand, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $brand->posts()->with('variants.platform')
            ->whereBetween('planned_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();
    }

    /** "Sat 24 Oct · Livraison Day #1", shown above the problem. */
    private function subject(Post $post): string
    {
        return ucfirst($post->planned_at?->isoFormat('ddd D MMM') ?? '').' · '.$post->title;
    }

    private function warning(string $level, string $icon, ?Brand $brand, string $text, ?int $postId = null, ?string $subject = null): array
    {
        return ['level' => $level, 'icon' => $icon, 'brand' => $brand, 'text' => $text, 'post_id' => $postId, 'subject' => $subject];
    }
}
