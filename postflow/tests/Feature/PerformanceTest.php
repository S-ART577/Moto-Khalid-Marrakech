<?php

namespace Tests\Feature;

use App\Livewire\Performance;
use App\Models\Metric;
use App\Models\PostVariant;
use App\Models\User;
use App\Support\Leaderboard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $cm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();
        app()->setLocale('en');
        $this->cm = User::firstWhere('email', 'cm@postflow.test');
        $this->cm->update(['locale' => 'en']);
    }

    private function tiktok20Oct(): PostVariant
    {
        return PostVariant::whereHas('post', fn ($q) => $q->where('title', 'like', 'Wait... same price? Earbuds%'))
            ->whereHas('platform', fn ($q) => $q->where('key', 'tiktok'))->firstOrFail();
    }

    public function test_the_community_manager_enters_numbers(): void
    {
        $variant = $this->tiktok20Oct();

        Livewire::actingAs($this->cm)->test(Performance::class, ['tab' => 'enter', 'month' => '2026-10'])
            ->set("values.{$variant->id}.views", 25000)
            ->set("values.{$variant->id}.whatsapp_orders", 7)
            ->call('saveMetrics')
            ->assertHasNoErrors();

        $this->assertSame(25000, $variant->metric()->first()->views);
        $this->assertSame(7, $variant->metric()->first()->whatsapp_orders);
    }

    public function test_numbers_sheet_round_trip(): void
    {
        $variant = $this->tiktok20Oct();

        // The downloaded sheet lists the posted platforms of the month.
        $csv = $this->actingAs($this->cm)->get('/export/numbers.csv?month=2026-10')->assertOk()->streamedContent();
        $this->assertStringContainsString($variant->post_url, $csv);

        // Fill it in (here: semicolon, like French Excel) and import it back.
        $file = UploadedFile::fake()->createWithContent('numbers.csv', "post_url;views;likes;comments;saves;shares;whatsapp_orders\n{$variant->post_url};31 000;1200;80;150;90;9\nhttps://example.com/unknown;1;1;1;1;1;1\n");

        Livewire::actingAs($this->cm)->test(Performance::class, ['tab' => 'enter', 'month' => '2026-10'])
            ->set('file', $file)
            ->call('importCsv')
            ->assertSet('importReport.matched', 1)
            ->assertCount('importReport.unmatched', 1);

        $this->assertSame(31000, $variant->metric()->first()->views);
        $this->assertSame('csv', $variant->metric()->first()->source);
    }

    public function test_leaderboard_ranks_by_the_chosen_metric(): void
    {
        $board = new Leaderboard([\App\Models\Brand::firstWhere('slug', 'groshop')->id]);

        $platforms = collect($board->groups('views')['platform']['rows'])->pluck('label');
        $this->assertSame('TikTok', $platforms->first());

        $byOrders = collect($board->groups('orders')['platform']['rows'])->pluck('label');
        $this->assertSame('Facebook', $byOrders->first());
    }

    public function test_performance_page_renders(): void
    {
        $this->actingAs($this->cm)->get('/performance')->assertOk()->assertSee('Top posts');
        $this->actingAs($this->cm)->get('/performance?tab=enter&month=2026-09')->assertOk()->assertSee('Livraison Day: first delivery vlog');
    }
}
