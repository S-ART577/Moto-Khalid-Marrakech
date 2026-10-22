<?php

namespace Database\Seeders;

use App\Models\SeasonalEvent;
use Illuminate\Database\Seeder;

/**
 * Moroccan and commercial dates, shared by every brand.
 * Hijri dates (Ramadan, Eid) are estimates: confirm them after the official moon sighting
 * and edit them in Seasons.
 */
class SeasonalEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            // [fr, en, start, end, category, approximate, shoot lead, design lead, guidance]
            ['Vacances scolaires', 'School break', '2026-10-18', '2026-10-25', 'school', false, 7, 3, 'Families at home: lifestyle and gaming content works.'],
            ['Fête de l\'Unité', 'Unity Day', '2026-10-31', null, 'national', false, 5, 3, 'A greeting at most, keep offers light.'],
            ['Marche Verte', 'Green March', '2026-11-06', null, 'national', false, 5, 3, 'Greeting post only.'],
            ['11.11', '11.11 (Singles\' Day)', '2026-11-11', null, 'commercial', false, 14, 7, 'Flash deals for 24 h. Tease from 8 Nov.'],
            ['Fête de l\'Indépendance', 'Independence Day', '2026-11-18', null, 'national', false, 5, 3, 'Greeting post only.'],
            ['Black Friday', 'Black Friday', '2026-11-27', '2026-11-30', 'commercial', false, 21, 10, 'Biggest offer of the year. Build the WhatsApp early-access list from late October.'],
            ['Fin d\'année', 'Year-end', '2026-12-15', '2026-12-31', 'commercial', false, 21, 10, 'Gift ideas and bundles. Delivery cut-off reminders.'],
            ['Nouvel an', 'New Year', '2027-01-01', null, 'national', false, 7, 3, 'Greeting + best of the year recap.'],
            ['Nouvel an amazigh', 'Amazigh New Year', '2027-01-14', null, 'national', false, 5, 3, 'Greeting post only.'],
            ['Ramadan', 'Ramadan', '2027-02-08', '2027-03-09', 'religious', true, 30, 14, 'Shoot everything before Ramadan. Post after iftar. Morocco moves the clocks back one hour during Ramadan.'],
            ['Aïd al-Fitr', 'Eid al-Fitr', '2027-03-10', '2027-03-11', 'religious', true, 21, 10, 'Gift ideas, delivery before Eid, greeting on the day.'],
            ['Aïd al-Adha', 'Eid al-Adha', '2027-05-17', '2027-05-18', 'religious', true, 21, 10, 'Family and travel tech. Delivery cut-off before Eid.'],
            ['Été', 'Summer', '2027-07-01', '2027-08-31', 'season', false, 21, 10, 'Travel accessories, power banks, MRE season.'],
            ['Fête du Trône', 'Throne Day', '2027-07-30', null, 'national', false, 5, 3, 'Greeting post only.'],
            ['Rentrée scolaire', 'Back to school', '2027-08-20', '2027-09-15', 'commercial', false, 21, 10, 'Laptops, tablets, headphones for students.'],
            ['11.11', '11.11 (Singles\' Day)', '2027-11-11', null, 'commercial', false, 14, 7, 'Flash deals for 24 h.'],
            ['Black Friday', 'Black Friday', '2027-11-26', '2027-11-29', 'commercial', false, 21, 10, 'Biggest offer of the year.'],
        ];

        foreach ($events as [$fr, $en, $start, $end, $category, $approximate, $shoot, $design, $guidance]) {
            SeasonalEvent::updateOrCreate(
                ['brand_id' => null, 'name_en' => $en, 'starts_on' => $start],
                [
                    'name_fr' => $fr,
                    'ends_on' => $end,
                    'category' => $category,
                    'is_approximate' => $approximate,
                    'shoot_lead_days' => $shoot,
                    'design_lead_days' => $design,
                    'guidance' => $guidance,
                ]
            );
        }
    }
}
