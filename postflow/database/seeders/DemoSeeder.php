<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Platform;
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
