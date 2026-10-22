<?php

namespace Tests\Feature;

use App\Livewire\Pipeline;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();
        $this->admin = User::firstWhere('email', 'admin@postflow.test');
        $this->cm = User::firstWhere('email', 'cm@postflow.test');
    }

    private function livraison(): Post
    {
        return Post::where('title', 'like', 'Livraison Day #1%')->firstOrFail();
    }

    public function test_board_and_list_render(): void
    {
        $this->actingAs($this->admin)->get('/pipeline')->assertOk()->assertSee('Livraison Day #1');
        $this->actingAs($this->cm)->get('/pipeline?view=list')->assertOk()->assertSee('Livraison Day #1');
    }

    public function test_admin_drags_a_card_to_any_stage(): void
    {
        Livewire::actingAs($this->admin)->test(Pipeline::class)
            ->call('handleSort', $this->livraison()->id, 0, 'scheduled');

        $this->assertSame('scheduled', $this->livraison()->stage->value);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $this->livraison()->id, 'action' => 'stage']);
    }

    public function test_community_manager_moves_up_to_review_only(): void
    {
        Livewire::actingAs($this->cm)->test(Pipeline::class)
            ->call('advance', $this->livraison()->id)       // edit -> design
            ->call('handleSort', $this->livraison()->id, 0, 'review')
            ->call('handleSort', $this->livraison()->id, 0, 'scheduled')
            ->assertDispatched('toast');

        $this->assertSame('review', $this->livraison()->stage->value);
    }

    public function test_old_posted_cards_are_hidden(): void
    {
        $old = Post::where('title', 'like', 'Atelier%')->firstOrFail();
        $old->update(['planned_at' => now()->subDays(30), 'stage' => 'posted']);

        Livewire::actingAs($this->admin)->test(Pipeline::class)
            ->assertDontSee('Atelier: tyre change')
            ->assertSee('Livraison Day #1');
    }

    public function test_only_mine_filter(): void
    {
        Livewire::actingAs($this->cm)->test(Pipeline::class)
            ->set('owner', 'me')
            ->assertSee('Black Friday early access')
            ->assertDontSee('Livraison Day #1');
    }
}
