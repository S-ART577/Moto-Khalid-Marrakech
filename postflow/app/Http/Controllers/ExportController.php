<?php

namespace App\Http\Controllers;

use App\Enums\Stage;
use App\Enums\VariantStatus;
use App\Models\Post;
use App\Models\PostVariant;
use App\Support\BrandContext;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV exports. The posts export uses the same columns the calendar import reads. */
class ExportController extends Controller
{
    public const POST_COLUMNS = [
        'date', 'time', 'brand', 'title', 'series', 'product_family', 'format', 'offer', 'stage', 'owner',
        'platform', 'placement', 'ratio', 'caption', 'hashtags', 'status', 'post_url',
        'views', 'likes', 'comments', 'saves', 'shares', 'whatsapp_orders', 'brief', 'drive_links',
    ];

    /** One line per platform version (a post without platforms gets one line). */
    public function posts(Request $request): StreamedResponse
    {
        $from = CarbonImmutable::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('to', now()->endOfMonth()->toDateString()))->endOfDay();

        $posts = Post::inBrands(BrandContext::make()->ids())
            ->with(['brand', 'series', 'productFamily', 'owner', 'variants.platform', 'variants.metric', 'assets'])
            ->whereBetween('planned_at', [$from, $to])
            ->when($request->query('platform'), fn ($q, $key) => $q->whereHas('variants.platform', fn ($q) => $q->where('key', $key)))
            ->when($request->query('series'), fn ($q, $id) => $q->where('series_id', $id))
            ->when($request->query('owner'), fn ($q, $id) => $q->where('owner_id', $id))
            ->when(Stage::tryFrom((string) $request->query('stage')), fn ($q, $stage) => $q->where('stage', $stage))
            ->orderBy('planned_at')
            ->get();

        $rows = [];
        foreach ($posts as $post) {
            $base = [
                'date' => $post->planned_at->toDateString(),
                'time' => $post->planned_at->format('H:i'),
                'brand' => $post->brand->name,
                'title' => $post->title,
                'series' => $post->series?->name,
                'product_family' => $post->productFamily?->name,
                'format' => $post->format->value,
                'offer' => $post->is_offer ? 'yes' : '',
                'stage' => $post->stage->value,
                'owner' => $post->owner?->name,
                'brief' => $post->brief,
                'drive_links' => $post->assets->pluck('url')->implode(' '),
            ];

            foreach ($post->variants->isEmpty() ? [null] : $post->variants as $variant) {
                $line = $base + [
                    'platform' => $variant?->platform->name,
                    'placement' => $variant?->placement,
                    'ratio' => $variant?->aspect_ratio,
                    'caption' => $variant?->caption,
                    'hashtags' => $variant?->hashtags,
                    'status' => $variant?->status->value,
                    'post_url' => $variant?->post_url,
                ];
                foreach (['views', 'likes', 'comments', 'saves', 'shares', 'whatsapp_orders'] as $field) {
                    $line[$field] = $variant?->metric?->{$field};
                }
                $line['time'] = $variant?->publish_at?->format('H:i') ?? $base['time'];
                $rows[] = array_map(fn ($column) => $line[$column] ?? '', self::POST_COLUMNS);
            }
        }

        return Csv::download('postflow-posts-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.csv', self::POST_COLUMNS, $rows);
    }

    /** The month's posted platforms with their numbers, to fill in Google Sheets and import back. */
    public function metrics(Request $request): StreamedResponse
    {
        $month = CarbonImmutable::parse(($request->query('month') ?: now()->format('Y-m')).'-01');

        $variants = PostVariant::with(['platform', 'metric', 'post.brand'])
            ->whereHas('post', fn ($q) => $q->inBrands(BrandContext::make()->ids()))
            ->where('status', VariantStatus::Posted)
            ->whereBetween('publish_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->orderBy('publish_at')
            ->get();

        $headers = ['post_url', 'date', 'brand', 'platform', 'title', 'views', 'likes', 'comments', 'saves', 'shares', 'whatsapp_orders'];

        $rows = $variants->map(fn ($v) => [
            $v->post_url, $v->publish_at->toDateString(), $v->post->brand->name, $v->platform->key, $v->post->title,
            $v->metric?->views, $v->metric?->likes, $v->metric?->comments, $v->metric?->saves, $v->metric?->shares, $v->metric?->whatsapp_orders,
        ]);

        return Csv::download('postflow-numbers-'.$month->format('Y-m').'.csv', $headers, $rows);
    }
}
