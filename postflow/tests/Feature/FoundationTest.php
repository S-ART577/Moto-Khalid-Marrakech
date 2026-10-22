<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/settings')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('PostFlow');
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'cm@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'cm@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_refused(): void
    {
        User::factory()->create(['email' => 'cm@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'cm@example.com')
            ->set('password', 'nope')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_language_switch_is_saved_on_the_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get('/locale/fr')->assertRedirect();
        $this->assertSame('fr', $user->fresh()->locale);

        $this->actingAs($user->fresh())->get('/settings')->assertSee('Réglages');
    }

    public function test_only_the_admin_manages_the_team(): void
    {
        $cm = User::factory()->create();

        Livewire::actingAs($cm)->test(Settings::class)
            ->call('newMember')
            ->assertForbidden();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Settings::class)
            ->call('newMember')
            ->set('member.name', 'Salma')
            ->set('member.email', 'salma@example.com')
            ->set('member.password', 'secret123')
            ->call('saveMember')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'salma@example.com', 'role' => 'community_manager']);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Settings::class)
            ->call('editMember', $admin->id)
            ->set('member.role', 'community_manager')
            ->call('saveMember')
            ->assertHasErrors('member.role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }
}
