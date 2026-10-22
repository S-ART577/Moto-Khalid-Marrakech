<?php

namespace App\Support;

use App\Enums\VariantStatus;
use App\Models\PostVariant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What worked: averages per series, format, platform, posting day and product family,
 * from the metrics entered for posted platform versions.
 */
class Leaderboard
{
    public const METRICS = ['views', 'engagement', 'saves', 'orders'];

    /** @var Collection<int, PostVariant> */
    private Collection $variants;

    public function __construct(array $brandIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null)
    {
        $this->variants = PostVariant::query()
            ->with(['metric', 'platform', 'post.series', 'post.productFamily', 'post.brand'])
            ->where('status', VariantStatus::Posted)
            ->whereHas('metric')
            ->whereHas('post', fn ($q) => $q->inBrands($brandIds))
            ->when($from, fn ($q) => $q->where('publish_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('publish_at', '<=', $to))
            ->get();
    }

    public function count(): int
    {
        return $this->variants->count();
    }

    /**
     * @return array<string, array{title: string, best: string, rows: array<int, array{label: string, posts: int, views: float, engagement: ?float, saves: float, orders: int, orders_per_post: float}>}>
     */
    public function groups(string $sortBy = 'views'): array
    {
        $groupers = [
            'series' => [__('Series'), __('Best series'), fn (PostVariant $v) => $v->post->series?->name ?? __('No series')],
            'format' => [__('Format'), __('Best format'), fn (PostVariant $v) => $v->post->kindLabel()],
            'platform' => [__('Platform'), __('Best platform'), fn (PostVariant $v) => $v->platform->name],
            'day' => [__('Posting day'), __('Best posting day'), fn (PostVariant $v) => ucfirst($v->publish_at->isoFormat('dddd'))],
            'family' => [__('Product family'), __('Best product family'), fn (PostVariant $v) => $v->post->productFamily?->name ?? __('None')],
        ];

        $result = [];
        foreach ($groupers as $key => [$title, $best, $grouper]) {
            $result[$key] = [
                'title' => $title,
                'best' => $best,
                'rows' => $this->variants->groupBy($grouper)
                    ->map(fn (Collection $items, string $label) => $this->stats($label, $items))
                    ->sortByDesc(fn ($row) => $this->sortValue($row, $sortBy))
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    /** The best posts, platform by platform. */
    public function topPosts(string $sortBy = 'views', int $limit = 8): Collection
    {
        return $this->variants
            ->sortByDesc(fn (PostVariant $v) => $this->sortValue($this->stats('', collect([$v])), $sortBy))
            ->take($limit)
            ->values();
    }

    /** Stats of a single platform post, for the "Top posts" list. */
    public function single(PostVariant $variant): array
    {
        return $this->stats('', collect([$variant]));
    }

    private function stats(string $label, Collection $items): array
    {
        $views = $items->sum(fn ($v) => $v->metric->views);
        $interactions = $items->sum(fn ($v) => $v->metric->likes + $v->metric->comments + $v->metric->saves + $v->metric->shares);
        $orders = $items->sum(fn ($v) => $v->metric->whatsapp_orders);
        $count = $items->count();

        return [
            'label' => $label,
            'posts' => $count,
            'views' => $count ? $views / $count : 0,
            'engagement' => $views ? $interactions / $views : null,
            'saves' => $count ? $items->sum(fn ($v) => $v->metric->saves) / $count : 0,
            'orders' => $orders,
            'orders_per_post' => $count ? $orders / $count : 0,
        ];
    }

    private function sortValue(array $row, string $sortBy): float
    {
        return (float) match ($sortBy) {
            'engagement' => $row['engagement'] ?? 0,
            'saves' => $row['saves'],
            'orders' => $row['orders_per_post'],
            default => $row['views'],
        };
    }

    public static function format(string $metric, array $row): string
    {
        return match ($metric) {
            'engagement' => $row['engagement'] === null ? '-' : number_format($row['engagement'] * 100, 1, ',', ' ').' %',
            'saves' => number_format($row['saves'], 0, ',', ' '),
            'orders' => number_format($row['orders_per_post'], 1, ',', ' '),
            default => self::compact($row['views']),
        };
    }

    public static function value(string $metric, array $row): float
    {
        return (float) match ($metric) {
            'engagement' => $row['engagement'] ?? 0,
            'saves' => $row['saves'],
            'orders' => $row['orders_per_post'],
            default => $row['views'],
        };
    }

    /** 12 400 -> 12,4K */
    public static function compact(float $number): string
    {
        return match (true) {
            $number >= 1_000_000 => number_format($number / 1_000_000, 1, ',', '').'M',
            $number >= 1_000 => number_format($number / 1_000, 1, ',', '').'K',
            default => number_format($number, 0, ',', ' '),
        };
    }
}
