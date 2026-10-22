<?php

namespace Tests\Feature;

use App\Livewire\Calendar;
use App\Livewire\PostPanel;
use App\Models\Brand;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Uses the demo data (GROSHOP October 2026 plan), "today" = Thu 22 Oct 2026. */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cm;

    private Brand $groshop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();

        $this->admin = User::firstWhere('email', 'admin@postflow.test');
        $this->cm = User::firstWhere('email', 'cm@postflow.test');
        $this->groshop = Brand::firstWhere('slug', 'groshop');
    }

    private function findPost(string $title): Post
    {
        return Post::where('title', 'like', $title.'%')->firstOrFail();
    }

    public function test_the_october_plan_is_seeded(): void
    {
        $october = $this->groshop->posts()->whereBetween('planned_at', ['2026-10-01', '2026-10-31 23:59'])->get();

        $this->assertCount(6, $october);
        $this->assertSame(4, $october->where('format.value', 'video')->count());
        $this->assertSame(2, $october->where('format.value', 'static')->count());
        $this->assertSame(1, $october->where('is_offer', true)->count());
    }

    public function test_calendar_renders_month_week_and_list(): void
    {
        foreach (['month', 'week', 'list'] as $view) {
            $this->actingAs($this->admin)
                ->get('/calendar?view='.$view.'&date=2026-10-26')
                ->assertOk()
                ->assertSee('Black Friday early access');
        }
    }

    public function test_moving_a_post_keeps_each_platform_time(): void
    {
        $post = $this->findPost('Wait... same price? Smartwatch');

        Livewire::actingAs($this->admin)->test(Calendar::class)
            ->call('handleSort', $post->id, 0, '2026-10-28');

        $post->refresh();
        $this->assertSame('2026-10-28 20:00', $post->planned_at->format('Y-m-d H:i'));
        $this->assertEqualsCanonicalizing(
            ['20:00', '20:00', '20:15', '21:00'],
            $post->variants->map(fn ($v) => $v->publish_at->format('H:i'))->all()
        );
        $this->assertTrue($post->variants->every(fn ($v) => $v->publish_at->isSameDay('2026-10-28')));
    }

    public function test_dragging_to_ideas_unschedules_a_post(): void
    {
        $post = $this->findPost('Akha, check again');

        Livewire::actingAs($this->admin)->test(Calendar::class)->call('handleSort', $post->id, 0, 'ideas');

        $this->assertNull($post->fresh()->planned_at);
        $this->assertTrue($post->variants()->get()->every(fn ($v) => $v->publish_at === null));
    }

    public function test_community_manager_cannot_move_posts(): void
    {
        $post = $this->findPost('Akha, check again');

        Livewire::actingAs($this->cm)->test(Calendar::class)
            ->call('handleSort', $post->id, 0, '2026-10-30')
            ->assertForbidden();
    }

    public function test_fill_empty_slots_creates_drafts_from_the_weekly_rhythm(): void
    {
        $this->actingAs($this->admin)->get('/brand/'.$this->groshop->id);

        Livewire::actingAs($this->admin)->test(Calendar::class, ['date' => '2026-11-01'])
            ->call('fillEmptySlots');

        // November 2026: 4 Tuesdays + 4 Thursdays + 4 Saturdays = 12 slots, all empty.
        $november = $this->groshop->posts()->whereBetween('planned_at', ['2026-11-01', '2026-11-30 23:59'])->get();
        $this->assertCount(12, $november);
        $this->assertSame(8, $november->where('format.value', 'video')->count());
        $this->assertSame(4, $november->first()->variants()->count());

        // Running it again creates nothing new.
        Livewire::actingAs($this->admin)->test(Calendar::class, ['date' => '2026-11-01'])->call('fillEmptySlots');
        $this->assertSame(12, $this->groshop->posts()->whereBetween('planned_at', ['2026-11-01', '2026-11-30 23:59'])->count());
    }

    public function test_admin_creates_a_post_from_the_panel(): void
    {
        $this->actingAs($this->admin)->get('/brand/'.$this->groshop->id);

        Livewire::actingAs($this->admin)->test(PostPanel::class)
            ->call('newPost', '2026-11-03')
            ->assertSet('form.format', 'video')   // Tuesday slot = video
            ->assertSet('form.time', '20:00')
            ->set('form.title', 'Wait... same price? Power bank')
            ->set('variants.'.$this->platformId('instagram').'.caption', 'Wait... nefs taman?')
            ->call('save')
            ->assertHasNoErrors();

        $post = $this->findPost('Wait... same price? Power bank');
        $this->assertSame(4, $post->variants()->count());
        $this->assertSame('Wait... nefs taman?', $post->variants()->where('platform_id', $this->platformId('instagram'))->value('caption'));
        $this->assertSame(6, $post->checklistItems()->count());
    }

    public function test_changing_the_time_in_the_panel_moves_every_platform(): void
    {
        $post = $this->findPost('Verified by Groshop: carousel');

        Livewire::actingAs($this->admin)->test(PostPanel::class)
            ->call('openPost', $post->id)
            ->set('form.time', '19:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['19:00', '19:30', '21:00'], $post->variants()->get()->map(fn ($v) => $v->publish_at->format('H:i'))->all());
    }

    public function test_community_manager_edits_captions_but_not_dates(): void
    {
        $post = $this->findPost('Livraison Day #1');
        $instagram = $this->platformId('instagram');

        Livewire::actingAs($this->cm)->test(PostPanel::class)
            ->call('openPost', $post->id)
            ->set('variants.'.$instagram.'.caption', 'Livraison Day: men l\'emballage 7tal bab dar.')
            ->set('form.title', 'Hacked title')
            ->set('variants.'.$instagram.'.time', '08:00')
            ->call('save')
            ->assertHasNoErrors();

        $post->refresh();
        $this->assertSame('Livraison Day #1: from the packing table to the door', $post->title);
        $variant = $post->variants()->where('platform_id', $instagram)->first();
        $this->assertSame('Livraison Day: men l\'emballage 7tal bab dar.', $variant->caption);
        $this->assertSame('20:00', $variant->publish_at->format('H:i'));
    }

    public function test_community_manager_cannot_approve(): void
    {
        $post = $this->findPost('Livraison Day #1');

        Livewire::actingAs($this->cm)->test(PostPanel::class)
            ->call('openPost', $post->id)
            ->set('form.stage', 'review')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('review', $post->fresh()->stage->value);

        Livewire::actingAs($this->cm)->test(PostPanel::class)
            ->call('openPost', $post->id)
            ->set('form.stage', 'scheduled')
            ->call('save')
            ->assertForbidden();
    }

    public function test_a_post_becomes_posted_when_every_platform_is_posted(): void
    {
        $post = $this->findPost('Verified by Groshop: carousel');

        foreach ($post->variants as $variant) {
            $this->assertNotSame('posted', $post->fresh()->stage->value);
            $variant->markPosted($this->cm, 'https://example.com/p/'.$variant->id);
        }

        $this->assertSame('posted', $post->fresh()->stage->value);
    }

    public function test_community_manager_only_sees_her_brands(): void
    {
        $other = Brand::create(['name' => 'Secret', 'slug' => 'secret']);
        $other->posts()->create(['title' => 'Secret launch', 'format' => 'video', 'planned_at' => '2026-10-23 20:00']);

        $this->actingAs($this->cm)->get('/calendar?date=2026-10-01')->assertOk()->assertDontSee('Secret launch');
        $this->actingAs($this->admin)->get('/calendar?date=2026-10-01')->assertSee('Secret launch');
    }

    private function platformId(string $key): int
    {
        return \App\Models\Platform::where('key', $key)->value('id');
    }
}
