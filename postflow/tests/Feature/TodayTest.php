<?php

namespace Tests\Feature;

use App\Livewire\Today;
use App\Models\Brand;
use App\Models\Post;
use App\Models\PostVariant;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Notifications\PostReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Demo data. On Thu 22 Oct 2026 the CM has Verified by Groshop to post (IG 18:00, FB 18:30, TT 20:00). */
class TodayTest extends TestCase
{
    use RefreshDatabase;

    private User $cm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();
        $this->cm = User::firstWhere('email', 'cm@postflow.test');
        $this->admin = User::firstWhere('email', 'admin@postflow.test');
    }

    private function variant(string $title, string $platform): PostVariant
    {
        return PostVariant::whereHas('post', fn ($q) => $q->where('title', 'like', $title.'%'))
            ->whereHas('platform', fn ($q) => $q->where('key', $platform))
            ->firstOrFail();
    }

    public function test_today_lists_due_and_overdue_posts_in_time_order(): void
    {
        $response = $this->actingAs($this->cm)->get('/today')->assertOk();

        $response->assertSeeInOrder(['21:00', 'Snapchat', '18:00', 'Instagram', '18:30', 'Facebook', '20:00', 'TikTok']);
        $response->assertSee('Kol montre katdouz');
    }

    public function test_mark_as_posted_with_the_link(): void
    {
        $instagram = $this->variant('Verified by Groshop: carousel', 'instagram');

        Livewire::actingAs($this->cm)->test(Today::class)
            ->call('startPosting', $instagram->id)
            ->set('urls.'.$instagram->id, 'https://www.instagram.com/p/ABC/')
            ->call('markPosted', $instagram->id)
            ->assertHasNoErrors();

        $instagram->refresh();
        $this->assertSame('posted', $instagram->status->value);
        $this->assertSame('https://www.instagram.com/p/ABC/', $instagram->post_url);
        $this->assertSame($this->cm->id, $instagram->posted_by);
    }

    public function test_a_wrong_link_is_refused(): void
    {
        $instagram = $this->variant('Verified by Groshop: carousel', 'instagram');

        Livewire::actingAs($this->cm)->test(Today::class)
            ->set('urls.'.$instagram->id, 'not a link')
            ->call('markPosted', $instagram->id)
            ->assertHasErrors('urls.'.$instagram->id);
    }

    public function test_skip_and_undo(): void
    {
        $snapchat = $this->variant('Wait... same price? Earbuds', 'snapchat');

        Livewire::actingAs($this->cm)->test(Today::class)->call('skip', $snapchat->id);
        $this->assertSame('skipped', $snapchat->fresh()->status->value);
        // Every platform of the post is now done.
        $this->assertSame('posted', $snapchat->post->fresh()->stage->value);

        Livewire::actingAs($this->cm)->test(Today::class)->call('undo', $snapchat->id);
        $this->assertSame('planned', $snapchat->fresh()->status->value);
    }

    public function test_posts_of_other_brands_are_out_of_reach(): void
    {
        $secret = Brand::create(['name' => 'Secret', 'slug' => 'secret']);
        $post = $secret->posts()->create(['title' => 'Secret', 'format' => 'video', 'planned_at' => '2026-10-22 15:00']);
        $variant = $post->variants()->create(['platform_id' => \App\Models\Platform::first()->id, 'publish_at' => '2026-10-22 15:00']);

        Livewire::actingAs($this->cm)->test(Today::class)
            ->assertDontSee('Secret')
            ->call('markPosted', $variant->id)
            ->assertNotFound();
    }

    public function test_reminder_before_the_publish_time_and_once_only(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 17:31'));

        $this->artisan('postflow:remind')->assertSuccessful();
        $this->artisan('postflow:remind')->assertSuccessful();

        Notification::assertSentToTimes($this->cm, PostReminder::class, 2); // IG 18:00 + overdue Snapchat
        Notification::assertSentTo($this->cm, PostReminder::class, fn ($n) => $n->kind === 'upcoming' && $n->variant->platform->key === 'instagram');
        Notification::assertSentTo($this->cm, PostReminder::class, fn ($n) => $n->kind === 'overdue' && $n->variant->platform->key === 'snapchat');
    }

    public function test_moving_a_post_sends_its_reminders_again(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 17:31'));
        $this->artisan('postflow:remind');

        $post = Post::where('title', 'like', 'Verified by Groshop: carousel%')->first();
        $post->moveTo($post->planned_at->addMinutes(20));
        $this->travelTo(CarbonImmutable::parse('2026-10-22 17:51'));
        $this->artisan('postflow:remind');

        Notification::assertSentToTimes($this->cm, PostReminder::class, 3);
    }

    public function test_morning_summary_goes_to_the_community_manager(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 08:30'));

        $this->artisan('postflow:digest')->assertSuccessful();

        Notification::assertSentTo($this->cm, DailyDigest::class, fn ($n) => $n->variants->count() === 3);
        Notification::assertNotSentTo($this->admin, DailyDigest::class);
    }

    public function test_push_subscription_is_saved(): void
    {
        $this->actingAs($this->cm)->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'BKey', 'auth' => 'secret'],
        ])->assertOk();

        $this->assertSame(1, $this->cm->pushSubscriptions()->count());
    }
}
