<?php

namespace Tests\Feature;

use App\Livewire\Seasons;
use App\Models\SeasonalEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();
    }

    public function test_moroccan_dates_are_seeded_with_lead_times(): void
    {
        $blackFriday = SeasonalEvent::where('name_en', 'Black Friday')->whereYear('starts_on', 2026)->first();

        $this->assertSame(36, $blackFriday->daysUntil());
        $this->assertSame('2026-11-06', $blackFriday->shootBy()->toDateString());
        $this->assertSame('2026-11-17', $blackFriday->designBy()->toDateString());
        $this->assertTrue(SeasonalEvent::where('name_en', 'Ramadan')->first()->is_approximate);
    }

    public function test_seasons_page_lists_upcoming_dates(): void
    {
        $this->actingAs(User::firstWhere('email', 'cm@postflow.test'))
            ->get('/seasons')->assertOk()->assertSeeInOrder(['Fête de l\'Unité', 'Black Friday', 'Ramadan']);
    }

    public function test_admin_adds_a_date_and_the_cm_cannot(): void
    {
        Livewire::actingAs(User::firstWhere('email', 'cm@postflow.test'))->test(Seasons::class)
            ->call('create')->assertForbidden();

        Livewire::actingAs(User::firstWhere('email', 'admin@postflow.test'))->test(Seasons::class)
            ->call('create')
            ->set('form.name_fr', 'Anniversaire GROSHOP')
            ->set('form.starts_on', '2026-12-01')
            ->set('form.shoot_lead_days', 10)
            ->call('save')
            ->assertHasNoErrors();

        $event = SeasonalEvent::firstWhere('name_fr', 'Anniversaire GROSHOP');
        $this->assertSame('Anniversaire GROSHOP', $event->name_en);
        $this->assertSame('2026-11-21', $event->shootBy()->toDateString());
    }
}
