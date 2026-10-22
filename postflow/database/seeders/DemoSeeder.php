<?php

namespace Database\Seeders;

use App\Enums\Format;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Platform;
use App\Models\Post;
use Carbon\CarbonImmutable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Demo workspace:
 * - two users (admin + community manager)
 * - GROSHOP with its October 2026 plan
 * - Moto Khalid Marrakech as a second brand, to test switching
 *
 * Change the passwords in Settings after the first login.
 */
class DemoSeeder extends Seeder
{
    public User $admin;

    public User $cm;

    public function run(): void
    {
        $this->admin = User::updateOrCreate(['email' => 'admin@postflow.test'], [
            'name' => 'Art Director',
            'password' => Hash::make('password'),
            'role' => Role::Admin,
            'locale' => 'fr',
        ]);

        $this->cm = User::updateOrCreate(['email' => 'cm@postflow.test'], [
            'name' => 'Community Manager',
            'password' => Hash::make('password'),
            'role' => Role::CommunityManager,
            'locale' => 'fr',
        ]);

        $groshop = $this->groshop();
        $moto = $this->motoKhalid();

        $this->cm->brands()->sync([$groshop->id, $moto->id]);

        // Posts are only created once, so re-running the seeder never duplicates them.
        if (! $groshop->posts()->exists()) {
            $this->groshopOctoberPlan($groshop);
        }
        if (! $moto->posts()->exists()) {
            $this->motoKhalidPosts($moto);
        }
    }

    /**
     * The GROSHOP October 2026 plan: 6 posts (4 video, 2 static) from Tue 20 Oct.
     * Tue video · Thu static · Sat video.
     */
    private function groshopOctoberPlan(Brand $brand): void
    {
        $series = $brand->series()->pluck('id', 'name');
        $family = $brand->productFamilies()->pluck('id', 'name');
        $tags = '#groshop #growbeyond #maroc #marrakech #casablanca #tech';

        $wait = $this->post($brand, [
            'title' => 'Wait... same price? Earbuds alone vs the full pack',
            'series_id' => $series['Wait... same price?'],
            'product_family_id' => $family['Audio'],
            'format' => 'video',
            'planned_at' => '2026-10-20 20:00',
            'stage' => 'posted',
            'owner_id' => $this->admin->id,
            'due_on' => '2026-10-18',
            'brief' => "Hook: \"Wait... tqder tkhod l'écouteurs bo7dhom b [prix]...\" [pause 0.5 s] \"...wla takhod pack kamel b nefs taman?\"\nOn-screen: نفس الثمن؟\nFirst frame: earbuds alone, spotlight on navy.\nProof: paiement à la livraison. CTA: Sift GRO f DM.\nPrice: confirm with the shop before posting.",
        ], [
            'tiktok' => ['20:00', "Wait... tqder takhod pack kamel b nefs taman? Sift 'GRO' f DM. Livraison 24/48h, paiement à la livraison.", $tags.' #ecouteurs', 'posted'],
            'instagram' => ['20:00', "Wait... nefs taman, pack kamel. Sauvegardi had reel 9bel ma t-nsah. Sift 'GRO' f DM.", $tags.' #ecouteurs', 'posted'],
            'facebook' => ['20:15', "Wait... nefs taman, pack kamel? Commandez sur WhatsApp. Paiement à la livraison.", $tags, 'posted'],
            'snapchat' => ['21:00', 'نفس الثمن؟ شوف الباك كامل. التوصيل 24/48 ساعة والدفع عند الاستلام.', '', 'planned'],
        ], checklistDone: 6);

        $verified = $this->post($brand, [
            'title' => 'Verified by Groshop: carousel template',
            'series_id' => $series['Verified by Groshop'],
            'product_family_id' => $family['Montres connectées'],
            'format' => 'static',
            'planned_at' => '2026-10-22 18:00',
            'stage' => 'scheduled',
            'owner_id' => $this->cm->id,
            'due_on' => '2026-10-21',
            'brief' => "Carousel, 5 slides: 1 the watch, 2 what we test, 3 garantie jusqu'à 1 an, 4 SAV certifié, 5 CTA WhatsApp.\nThis becomes the template for every Verified by Groshop post.",
        ], [
            'instagram' => ['18:00', "Kol montre katdouz men 3ndna 9bel ma twsel lik: test, garantie, SAV. Verified by Groshop. Sauvegardi had post.", $tags.' #smartwatch', 'planned', 'carousel'],
            'facebook' => ['18:30', "Avant d'arriver chez vous, chaque montre est testée. Garantie jusqu'à 1 an, SAV certifié. Verified by Groshop.", $tags.' #smartwatch', 'planned', 'carousel'],
            'tiktok' => ['20:00', "Verified by Groshop: chno kan-testiw 9bel ma nbi3ou. Sift 'GRO' f DM.", $tags.' #smartwatch', 'planned', 'photo'],
        ], checklistDone: 4, asset: ['Carousel 5 slides (final)', 'https://drive.google.com/file/d/1DemoVerifiedCarousel01/view']);

        $livraison = $this->post($brand, [
            'title' => 'Livraison Day #1: from the packing table to the door',
            'series_id' => $series['Livraison Day'],
            'format' => 'video',
            'planned_at' => '2026-10-24 20:00',
            'stage' => 'edit',
            'owner_id' => $this->admin->id,
            'due_on' => '2026-10-23',
            'brief' => "Behind the scenes: packing, the courier picks up, a customer opens the box at the door.\nProof: paiement à la livraison, 24/48h. CTA: commandez sur WhatsApp.",
        ], [
            'tiktok' => ['20:00', "Livraison Day: men l'emballage 7tal bab dar. Paiement à la livraison.", $tags.' #livraison'],
            'instagram' => ['20:00', '', $tags.' #livraison'],
            'facebook' => ['20:15', '', $tags],
            'snapchat' => ['21:00', '', ''],
        ], checklistDone: 3, asset: ['Rushes 23 Oct', 'https://drive.google.com/drive/folders/1DemoLivraisonRushes']);

        $this->post($brand, [
            'title' => 'Wait... same price? Smartwatch alone vs the pack',
            'series_id' => $series['Wait... same price?'],
            'product_family_id' => $family['Montres connectées'],
            'format' => 'video',
            'planned_at' => '2026-10-27 20:00',
            'stage' => 'shoot',
            'owner_id' => $this->admin->id,
            'due_on' => '2026-10-25',
        ], [
            'tiktok' => ['20:00', '', $tags], 'instagram' => ['20:00', '', $tags], 'facebook' => ['20:15', '', $tags], 'snapchat' => ['21:00', '', ''],
        ], checklistDone: 1);

        $this->post($brand, [
            'title' => 'Black Friday early access: message us on WhatsApp',
            'series_id' => $series['Offer & Seasonal'],
            'format' => 'static',
            'is_offer' => true,
            'planned_at' => '2026-10-29 20:00',
            'stage' => 'design',
            'owner_id' => $this->cm->id,
            'due_on' => '2026-10-27',
            'brief' => "Ask followers to message the shop on WhatsApp to hear the Black Friday offers first. Black Friday is 27 Nov.\nNo prices yet.",
        ], [
            'instagram' => ['20:00', "Black Friday jay. Bghiti tkoun l'awel? Sift 'BF' f WhatsApp w ghadi nsiftou lik l'offres 9bel ga3.", $tags.' #blackfriday', 'planned', 'post'],
            'facebook' => ['20:00', 'Black Friday arrive. Envoyez "BF" sur WhatsApp pour recevoir les offres en avant-première.', $tags.' #blackfriday', 'planned', 'post'],
            'tiktok' => ['20:30', '', $tags.' #blackfriday', 'planned', 'photo'],
            'snapchat' => ['21:00', '', '', 'planned', 'story'],
        ], checklistDone: 1);

        $this->post($brand, [
            'title' => 'Akha, check again: the smartphone deal',
            'series_id' => $series['Akha, check again'],
            'product_family_id' => $family['Smartphones'],
            'format' => 'video',
            'planned_at' => '2026-10-31 20:00',
            'stage' => 'brief',
            'owner_id' => $this->admin->id,
            'due_on' => '2026-10-29',
            'brief' => "\"Akha... hadchi ma ymkench ykoun s7i7. Khellini n3awed nchouf.\" Re-reads the price on the phone, then confirms.\nUnity Day is on the same day: keep it light, no hard sell.",
        ], [
            'tiktok' => ['20:00', '', $tags], 'instagram' => ['20:00', '', $tags], 'facebook' => ['20:15', '', $tags], 'snapchat' => ['21:00', '', ''],
        ]);

        // Ideas without a date yet.
        $this->post($brand, ['title' => 'Verified by Groshop: what is in the box', 'series_id' => $series['Verified by Groshop'], 'format' => 'video', 'planned_at' => null], []);
        $this->post($brand, ['title' => '11.11 teaser: 24 h flash deals', 'series_id' => $series['Offer & Seasonal'], 'format' => 'static', 'is_offer' => true, 'planned_at' => null], []);

        $livraison->comments()->create(['user_id' => $this->cm->id, 'body' => 'Courier confirmed for Saturday, 10:00.']);
        $livraison->comments()->create(['user_id' => $this->admin->id, 'body' => 'Good. I shoot the packing at 9:30, edit in the afternoon.']);
        $verified->comments()->create(['user_id' => $this->admin->id, 'body' => 'Final slides are on Drive. Post Instagram first, then Facebook.']);
        $wait->comments()->create(['user_id' => $this->cm->id, 'body' => 'Snapchat story still to post, I was out of battery.']);
    }

    private function motoKhalidPosts(Brand $brand): void
    {
        $series = $brand->series()->pluck('id', 'name');
        $family = $brand->productFamilies()->pluck('id', 'name');
        $tags = '#motokhalid #marrakech #moto #scooter';

        $this->post($brand, [
            'title' => 'Atelier: tyre change in 30 seconds',
            'series_id' => $series['Atelier'],
            'product_family_id' => $family['Scooters'],
            'format' => 'video',
            'planned_at' => '2026-10-21 19:00',
            'stage' => 'posted',
            'owner_id' => $this->admin->id,
        ], [
            'instagram' => ['19:00', "Changement de pneu en 30 secondes. Passez à l'atelier ou appelez-nous.", $tags, 'posted'],
            'tiktok' => ['19:00', "Pneu jdid f 30 seconde. Dowzo l'atelier.", $tags, 'posted'],
            'facebook' => ['19:30', 'Changement de pneu en 30 secondes. Atelier ouvert tous les jours.', $tags, 'posted'],
        ], checklistDone: 6);

        $this->post($brand, [
            'title' => 'Nouvel arrivage: 125 cc scooters',
            'series_id' => $series['Nouvel arrivage'],
            'product_family_id' => $family['Scooters'],
            'format' => 'video',
            'planned_at' => '2026-10-25 12:00',
            'stage' => 'edit',
            'owner_id' => $this->admin->id,
        ], [
            'instagram' => ['12:00', '', $tags], 'tiktok' => ['12:00', '', $tags], 'facebook' => ['12:30', '', $tags],
        ], checklistDone: 3);

        $this->post($brand, [
            'title' => 'Conseil entretien: clean your chain',
            'series_id' => $series['Conseil entretien'],
            'product_family_id' => $family['Motos'],
            'format' => 'video',
            'planned_at' => '2026-10-28 19:00',
            'stage' => 'brief',
            'owner_id' => $this->admin->id,
        ], [
            'instagram' => ['19:00', '', $tags], 'tiktok' => ['19:00', '', $tags], 'facebook' => ['19:30', '', $tags],
        ]);
    }

    /**
     * @param  array<string, array>  $variants  platform key => [time, caption, hashtags, status = planned, placement = default]
     */
    private function post(Brand $brand, array $attributes, array $variants, int $checklistDone = 0, ?array $asset = null): Post
    {
        $format = Format::from($attributes['format']);
        $post = $brand->posts()->create($attributes + ['created_by' => $this->admin->id, 'stage' => 'brief']);
        $platforms = Platform::pluck('id', 'key');
        $day = $post->planned_at?->toDateString();

        foreach ($variants as $key => $row) {
            [$time, $caption, $hashtags] = $row;
            $status = $row[3] ?? 'planned';
            $placement = $row[4] ?? $format->placements($key)[0];
            $publishAt = $day ? CarbonImmutable::parse($day.' '.$time) : null;

            $post->variants()->create([
                'platform_id' => $platforms[$key],
                'placement' => $placement,
                'aspect_ratio' => $format->defaultRatio($placement),
                'caption' => $caption ?: null,
                'hashtags' => $hashtags ?: null,
                'publish_at' => $publishAt,
                'status' => $status,
                'posted_at' => $status === 'posted' ? $publishAt?->addMinutes(3) : null,
                'posted_by' => $status === 'posted' ? $this->cm->id : null,
                'post_url' => $status === 'posted' ? 'https://example.com/demo/'.$key.'/'.$post->id : null,
                // Past reminders are considered sent.
                'reminded_at' => $publishAt?->isPast() ? $publishAt : null,
            ]);
        }

        foreach (Post::CHECKLIST[$format->value] as $position => $label) {
            $post->checklistItems()->create(['label' => $label, 'position' => $position, 'done' => $position < $checklistDone]);
        }

        if ($asset) {
            $post->assets()->create(['label' => $asset[0], 'url' => $asset[1]]);
        }

        $post->syncStageFromVariants();

        return $post;
    }

    private function groshop(): Brand
    {
        $brand = Brand::updateOrCreate(['slug' => 'groshop'], Brand::DEFAULT_COLORS + [
            'name' => 'GROSHOP',
            'tagline' => 'Produits vérifiés. Fiabilité garantie.',
            'signature' => 'Think GRO. Grow More.',
            'whatsapp' => '212703735937',
            'position' => 1,
            'tone_notes' => implode("\n", [
                'Darija first, premium but human. Confident, never arrogant. Funny yes, corny no.',
                'Captions in Arabizi (40 to 125 characters, one idea, one CTA, price in the caption). On-screen text in Arabic script.',
                'Proof beats: Pas de dropshipping · SAV certifié · Garantie jusqu\'à 1 an · Livraison 24/48h partout au Maroc · Paiement à la livraison.',
                'Never invent a price or a stock count. Price format: 219,00 DH.',
                'Hashtags 5 to 8: local + category + brand (#groshop #growbeyond). Ask for saves and shares, not likes.',
                'Orders close on WhatsApp: "Sift GRO f DM" or the WhatsApp link.',
            ]),
        ]);

        $brand->platforms()->sync(Platform::pluck('id'));

        $series = [
            ['Wait... same price?', '#f02443', 'Wait / pause pattern: "Wait... tqder tkhod A bo7do b X... wla takhod A + B + C b nefs taman?" Hold the pause, then reveal the bundle.'],
            ['Verified by Groshop', '#43dde6', 'Carousel template: what we check before a product goes on sale. Proof of quality and SAV.'],
            ['Livraison Day', '#dfebf7', 'Behind the scenes of delivery day: packing, the courier, a happy customer. Cash on delivery.'],
            ['Akha, check again', '#d61e3a', 'Fake checking / disbelief: "Akha... hadchi ma ymkench ykoun s7i7." Visible re-check, then confirm the deal.'],
            ['Offer & Seasonal', '#ffffff', 'Offers, Black Friday, 11.11, Ramadan and seasonal moments.'],
        ];
        foreach ($series as $position => [$name, $color, $description]) {
            $brand->series()->updateOrCreate(['name' => $name], compact('color', 'description', 'position'));
        }

        foreach (['Smartphones', 'Audio', 'Montres connectées', 'Accessoires'] as $position => $name) {
            $brand->productFamilies()->updateOrCreate(['name' => $name], ['max_gap_weeks' => 3, 'position' => $position]);
        }

        // Tue video · Thu static · Sat video
        $brand->postingSlots()->delete();
        foreach ([[2, 'video'], [4, 'static'], [6, 'video']] as [$weekday, $format]) {
            $brand->postingSlots()->create(['weekday' => $weekday, 'format' => $format, 'time' => '20:00']);
        }

        $seriesIds = $brand->series()->pluck('id', 'name');
        $brand->quotas()->delete();
        $brand->quotas()->createMany([
            ['period' => 'week', 'dimension' => 'total', 'target' => 3],
            ['period' => 'week', 'dimension' => 'format', 'dimension_ref' => 'video', 'target' => 2],
            ['period' => 'week', 'dimension' => 'format', 'dimension_ref' => 'static', 'target' => 1],
            ['period' => 'week', 'dimension' => 'platform', 'dimension_ref' => 'tiktok', 'target' => 3],
            ['period' => 'week', 'dimension' => 'platform', 'dimension_ref' => 'instagram', 'target' => 3],
            ['period' => 'week', 'dimension' => 'platform', 'dimension_ref' => 'facebook', 'target' => 3],
            ['period' => 'week', 'dimension' => 'platform', 'dimension_ref' => 'snapchat', 'target' => 2],
            ['period' => 'month', 'dimension' => 'offer', 'target' => 2],
            ['period' => 'month', 'dimension' => 'series', 'dimension_ref' => $seriesIds['Wait... same price?'], 'target' => 4],
            ['period' => 'month', 'dimension' => 'series', 'dimension_ref' => $seriesIds['Verified by Groshop'], 'target' => 3],
            ['period' => 'month', 'dimension' => 'series', 'dimension_ref' => $seriesIds['Livraison Day'], 'target' => 2],
            ['period' => 'month', 'dimension' => 'series', 'dimension_ref' => $seriesIds['Akha, check again'], 'target' => 2],
        ]);

        $brand->monthNotes()->updateOrCreate(['month' => '2026-10-01'], [
            'theme_title' => 'Trust base before the Q4 rush',
            'theme_body' => 'Shoot the first Livraison Day videos and a Verified by Groshop carousel template now.',
            'offer_title' => 'Black Friday early access',
            'offer_body' => 'Ask followers to message the shop on WhatsApp to hear the offers first. Black Friday is 27 Nov.',
            'dates_title' => 'Break and Unity Day',
            'dates_body' => 'School break 18 to 25 Oct. Unity Day on Sat 31 Oct: a greeting at most, keep offers light.',
        ]);

        return $brand;
    }

    private function motoKhalid(): Brand
    {
        $logo = null;
        $source = database_path('seeders/assets/moto-khalid.png');
        if (file_exists($source)) {
            Storage::disk('public')->put('logos/moto-khalid.png', file_get_contents($source));
            $logo = 'logos/moto-khalid.png';
        }

        $brand = Brand::updateOrCreate(['slug' => 'moto-khalid-marrakech'], [
            'name' => 'Moto Khalid Marrakech',
            'logo_path' => $logo,
            'color_bg' => '#0d0d0d',
            'color_bg_2' => '#1a1a1a',
            'color_primary' => '#d81f1f',
            'color_primary_text' => '#c41c1c',
            'color_light' => '#f2f2f2',
            'color_accent' => '#e8e8e8',
            'tagline' => 'Vente · Réparation · Entretien · Pièces',
            'whatsapp' => '212678794452',
            'position' => 2,
            'tone_notes' => 'Friendly neighbourhood workshop in Marrakech. Darija and French. Show real bikes, real repairs, real prices. CTA: call or WhatsApp, or visit the shop.',
        ]);

        $platforms = Platform::pluck('id', 'key');
        $brand->platforms()->sync([
            $platforms['instagram'] => ['handle' => '@almyrmnzmn', 'profile_url' => 'https://www.instagram.com/almyrmnzmn/'],
            $platforms['facebook'] => ['handle' => null, 'profile_url' => 'https://www.facebook.com/profile.php?id=100063509325865'],
            $platforms['tiktok'] => ['handle' => null, 'profile_url' => null],
        ]);

        foreach ([['Atelier', '#d81f1f', 'A repair from start to finish, in under 30 seconds.'], ['Nouvel arrivage', '#f2f2f2', 'New bikes and parts in the shop.'], ['Conseil entretien', '#e8e8e8', 'One maintenance tip per video.']] as $position => [$name, $color, $description]) {
            $brand->series()->updateOrCreate(['name' => $name], compact('color', 'description', 'position'));
        }

        foreach (['Scooters', 'Motos', 'Pièces et accessoires'] as $position => $name) {
            $brand->productFamilies()->updateOrCreate(['name' => $name], ['max_gap_weeks' => 4, 'position' => $position]);
        }

        $brand->postingSlots()->delete();
        $brand->postingSlots()->createMany([
            ['weekday' => 3, 'format' => 'video', 'time' => '19:00'],
            ['weekday' => 7, 'format' => 'video', 'time' => '12:00'],
        ]);

        $brand->quotas()->delete();
        $brand->quotas()->createMany([
            ['period' => 'week', 'dimension' => 'total', 'target' => 2],
            ['period' => 'week', 'dimension' => 'format', 'dimension_ref' => 'video', 'target' => 2],
        ]);

        return $brand;
    }
}
