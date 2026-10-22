<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * SAMPLE numbers so the Performance screen has something to show:
 * a September 2026 history for GROSHOP (12 posted posts with made-up metrics)
 * and metrics for the posts already published in October.
 *
 * Remove DemoHistorySeeder from DatabaseSeeder to skip it.
 */
class DemoHistorySeeder extends Seeder
{
    public function run(): void
    {
        $groshop = Brand::firstWhere('slug', 'groshop');
        if (! $groshop || $groshop->posts()->where('planned_at', '<', '2026-10-01')->exists()) {
            return;
        }

        mt_srand(2026);
        $cm = User::firstWhere('email', 'cm@postflow.test');
        $series = $groshop->series()->pluck('id', 'name');
        $family = $groshop->productFamilies()->pluck('id', 'name');
        $platforms = Platform::all()->keyBy('key');

        // [date, format, series, family, title, how well it did (1 = average)]
        $history = [
            ['2026-09-01', 'video', 'Wait... same price?', 'Smartphones', 'Wait... same price? Phone alone vs phone + case + charger', 1.4],
            ['2026-09-03', 'static', 'Verified by Groshop', 'Audio', 'Verified by Groshop: earbuds test', 0.8],
            ['2026-09-05', 'video', 'Livraison Day', null, 'Livraison Day: first delivery vlog', 1.1],
            ['2026-09-08', 'video', 'Akha, check again', 'Smartphones', 'Akha, check again: the phone price', 1.6],
            ['2026-09-10', 'static', 'Offer & Seasonal', null, 'Back to school pack', 0.9],
            ['2026-09-12', 'video', 'Wait... same price?', 'Montres connectées', 'Wait... same price? Watch alone vs pack', 1.3],
            ['2026-09-15', 'video', 'Livraison Day', null, 'Livraison Day: Casablanca in 24 h', 0.9],
            ['2026-09-17', 'static', 'Verified by Groshop', 'Montres connectées', 'Verified by Groshop: watch checklist', 1.0],
            ['2026-09-19', 'video', 'Akha, check again', 'Audio', 'Akha, check again: the headphones deal', 1.5],
            ['2026-09-22', 'video', 'Wait... same price?', 'Audio', 'Wait... same price? Speaker vs speaker + earbuds', 1.2],
            ['2026-09-24', 'static', 'Offer & Seasonal', 'Smartphones', 'End of September offer', 1.1],
            ['2026-09-26', 'video', 'Livraison Day', null, 'Livraison Day: Marrakech by scooter', 1.0],
        ];

        foreach ($history as [$date, $format, $seriesName, $familyName, $title, $boost]) {
            $day = CarbonImmutable::parse($date.' 20:00');
            $post = $groshop->posts()->create([
                'title' => $title,
                'series_id' => $series[$seriesName],
                'product_family_id' => $familyName ? $family[$familyName] : null,
                'format' => $format,
                'is_offer' => $seriesName === 'Offer & Seasonal',
                'planned_at' => $day,
                'stage' => 'posted',
                'owner_id' => $cm->id,
                'created_by' => $cm->id,
            ]);

            $keys = $format === 'video' ? ['tiktok', 'instagram', 'facebook', 'snapchat'] : ['instagram', 'facebook', 'tiktok'];
            foreach ($keys as $key) {
                $variant = $post->variants()->create([
                    'platform_id' => $platforms[$key]->id,
                    'placement' => $format === 'video' ? ($key === 'tiktok' ? 'video' : ($key === 'snapchat' ? 'spotlight' : 'reel')) : ($key === 'tiktok' ? 'photo' : 'carousel'),
                    'aspect_ratio' => $format === 'video' ? '9:16' : '4:5',
                    'publish_at' => $day,
                    'status' => 'posted',
                    'posted_at' => $day->addMinutes(4),
                    'posted_by' => $cm->id,
                    'post_url' => 'https://example.com/demo/'.$key.'/'.$post->id,
                ]);
                $this->metrics($variant->id, $key, $format, $boost, $day->isoWeekday() === 6);
            }
        }

        // Numbers for what is already posted in October.
        foreach (Post::where('stage', '!=', 'brief')->where('planned_at', '>=', '2026-10-01')->with('variants.platform')->get() as $post) {
            foreach ($post->variants->where('status.value', 'posted') as $variant) {
                $this->metrics($variant->id, $variant->platform->key, $post->format->value, 1.2, false);
            }
        }
    }

    /** Made-up but plausible numbers: TikTok reaches most, Instagram gets the saves, Facebook the orders. */
    private function metrics(int $variantId, string $platform, string $format, float $boost, bool $saturday): void
    {
        $reach = ['tiktok' => 14000, 'instagram' => 6000, 'facebook' => 3500, 'snapchat' => 1500][$platform];
        $reach *= $format === 'video' ? 1.0 : 0.45;
        $reach *= $boost * ($saturday ? 1.25 : 1.0) * (mt_rand(80, 120) / 100);
        $views = (int) round($reach);

        \App\Models\Metric::updateOrCreate(['post_variant_id' => $variantId], [
            'views' => $views,
            'likes' => (int) round($views * mt_rand(35, 70) / 1000),
            'comments' => (int) round($views * mt_rand(3, 9) / 1000),
            'saves' => (int) round($views * ($platform === 'instagram' ? mt_rand(15, 35) : mt_rand(3, 10)) / 1000),
            'shares' => (int) round($views * mt_rand(3, 15) / 1000),
            'whatsapp_orders' => (int) round(($platform === 'facebook' ? 3.5 : ($platform === 'snapchat' ? 0.5 : 1.8)) * $boost * mt_rand(50, 150) / 100),
            'source' => 'manual',
        ]);
    }
}
