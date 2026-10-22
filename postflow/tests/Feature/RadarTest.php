<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Post;
use App\Models\User;
use App\Support\Radar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Demo data, "today" = Thu 22 Oct 2026, interface in English. */
class RadarTest extends TestCase
{
    use RefreshDatabase;

    private Brand $groshop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();
        app()->setLocale('en');
        $this->groshop = Brand::firstWhere('slug', 'groshop');
    }

    private function texts(): array
    {
        return array_column((new Radar(collect([$this->groshop])))->warnings(), 'text');
    }

    private function assertWarning(string $needle): void
    {
        $this->assertTrue(
            collect($this->texts())->contains(fn ($text) => str_contains($text, $needle)),
            "No warning containing \"$needle\" in:\n".implode("\n", $this->texts())
        );
    }

    public function test_a_missing_video_is_reported_in_plain_words(): void
    {
        Post::where('title', 'like', 'Akha, check again%')->first()->delete();

        $this->assertWarning('Week of 26 Oct: 1 video missing');
        $this->assertFalse(collect($this->texts())->contains(fn ($t) => str_contains($t, 'Week of 26 Oct') && str_contains($t, 'TikTok')));
    }

    public function test_an_empty_week_is_reported(): void
    {
        $this->assertWarning('Week of 2 Nov: nothing planned yet, 3 posts expected');
    }

    public function test_filled_weeks_are_not_reported(): void
    {
        $this->assertFalse(collect($this->texts())->contains(fn ($t) => str_contains($t, 'Week of 26 Oct')));
    }

    public function test_product_family_gap(): void
    {
        $this->assertWarning('Accessoires not featured yet');

        $family = $this->groshop->productFamilies()->firstWhere('name', 'Accessoires');
        $this->groshop->posts()->create(['title' => 'Cables', 'format' => 'static', 'planned_at' => '2026-09-22 10:00', 'product_family_id' => $family->id]);

        $this->assertWarning('Accessoires not featured for 4 weeks');
    }

    public function test_overdue_platform_and_missing_captions(): void
    {
        $this->assertWarning('Snapchat not marked as posted');
        $this->assertWarning('Livraison Day #1: from the packing table to the door: no caption for Instagram, Facebook, Snapchat');
    }

    public function test_seasonal_deadline(): void
    {
        $this->assertWarning('Black Friday in 36 days: shoot by Fri 6 Nov');
    }

    public function test_content_mix_against_targets(): void
    {
        $mix = collect((new Radar(collect([$this->groshop])))->mix($this->groshop, CarbonImmutable::parse('2026-10-01')))->keyBy('label');

        $this->assertSame(4, $mix['Videos']['planned']);
        $this->assertSame(9, $mix['Videos']['target']); // 2 a week x 31 / 7
        $this->assertSame(1, $mix['Verified by Groshop']['planned']);
        $this->assertSame(3, $mix['Verified by Groshop']['target']);
    }

    public function test_radar_page_renders(): void
    {
        $admin = User::firstWhere('email', 'admin@postflow.test');
        $admin->update(['locale' => 'en']);

        $this->actingAs($admin)->get('/radar?month=2026-10')->assertOk()->assertSee('Needs attention')->assertSee('Content mix this month');
    }
}
