<?php

namespace Tests\Feature;

use App\Livewire\Brands\Edit;
use App\Models\Brand;
use App\Models\User;
use App\Support\BrandContext;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    public function test_admin_creates_a_brand_with_series_rhythm_and_targets(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Edit::class)
            ->set('form.name', 'GROSHOP')
            ->call('addRow', 'series')
            ->set('series.0.name', 'Livraison Day')
            ->call('addRow', 'families')
            ->set('families.0.name', 'Smartphones')
            ->call('addRow', 'postingSlots')
            ->set('postingSlots.0.weekday', 2)
            ->call('addRow', 'quotas')
            ->set('quotas.0.what', 'format:video')
            ->set('quotas.0.target', 2)
            ->call('save')
            ->assertHasNoErrors();

        $brand = Brand::firstWhere('slug', 'groshop');
        $this->assertNotNull($brand);
        $this->assertSame('#102533', $brand->color_bg);
        $this->assertCount(4, $brand->platforms);
        $this->assertSame(['Livraison Day'], $brand->series->pluck('name')->all());
        $this->assertSame(['Smartphones'], $brand->productFamilies->pluck('name')->all());
        $this->assertSame(2, $brand->postingSlots->first()->weekday);
        $this->assertSame('video', $brand->quotas->first()->dimension_ref);
    }

    public function test_removing_a_series_also_removes_its_target(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::create(['name' => 'Test', 'slug' => 'test']);
        $series = $brand->series()->create(['name' => 'Akha, check again']);
        $brand->quotas()->create(['period' => 'month', 'dimension' => 'series', 'dimension_ref' => $series->id, 'target' => 2]);

        Livewire::actingAs($admin)->test(Edit::class, ['brand' => $brand])
            ->call('removeRow', 'series', 0)
            ->call('removeRow', 'quotas', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, $brand->series()->count());
        $this->assertSame(0, $brand->quotas()->count());
    }

    public function test_colours_must_be_hex(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Edit::class)
            ->set('form.name', 'Bad')
            ->set('form.color_bg', 'red; } body { display:none')
            ->call('save')
            ->assertHasErrors('form.color_bg');
    }

    public function test_low_contrast_palette_is_flagged(): void
    {
        $admin = User::factory()->admin()->create();

        $checks = Livewire::actingAs($admin)->test(Edit::class)
            ->set('form.color_primary_text', '#ff8a8a')
            ->get('contrastChecks');

        $this->assertLessThan(4.5, min($checks));
    }

    public function test_community_manager_cannot_manage_brands(): void
    {
        $cm = User::factory()->create();

        $this->actingAs($cm)->get('/brands')->assertForbidden();
        $this->actingAs($cm)->get('/brands/create')->assertForbidden();
    }

    public function test_brand_switcher_only_allows_assigned_brands(): void
    {
        $mine = Brand::create(['name' => 'Mine', 'slug' => 'mine']);
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $cm = User::factory()->create();
        $cm->brands()->attach($mine);

        $this->actingAs($cm)->get('/brand/'.$other->id);
        $this->assertNull(session('brand_id'));

        $this->actingAs($cm)->get('/brand/'.$mine->id);
        $this->assertSame($mine->id, session('brand_id'));

        $this->assertSame([$mine->id], BrandContext::make()->ids());
    }
}
