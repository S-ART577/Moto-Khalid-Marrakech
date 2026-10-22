<?php

namespace Tests\Feature;

use App\Livewire\ImportExport;
use App\Models\Brand;
use App\Models\Post;
use App\Models\User;
use App\Support\CalendarImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $groshop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00'));
        $this->seed();
        $this->admin = User::firstWhere('email', 'admin@postflow.test');
        $this->groshop = Brand::firstWhere('slug', 'groshop');
    }

    public function test_dates_times_and_platforms_are_understood(): void
    {
        $this->assertSame('2026-11-03 20:00', CalendarImporter::parseDate('03/11/2026')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-03 18:30', CalendarImporter::parseDate('2026-11-03', '18h30')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-03 20:00', CalendarImporter::parseDate('3-11-26', '8:00 PM')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-13 21:00', CalendarImporter::parseDate('11/13/2026 21:00')->format('Y-m-d H:i')); // US order, 13 cannot be a month
        $this->assertNull(CalendarImporter::parseDate('mardi'));

        $this->assertSame(['tiktok', 'instagram', 'facebook', 'snapchat'], CalendarImporter::parsePlatforms('TT, IG / Facebook + snap'));
        $this->assertSame('review', CalendarImporter::parseStage('En validation')->value);
    }

    public function test_a_french_sheet_with_one_line_per_post_is_imported(): void
    {
        $this->actingAs($this->admin)->get('/brand/'.$this->groshop->id);
        $csv = "Date;Heure;Titre;Format;Plateformes;Série;Légende\n"
            ."03/11/2026;20h;Wait... same price? Power bank;Vidéo;TT, IG;Wait... same price?;Wait... nefs taman?\n"
            ."05/11/2026;20h;Verified by Groshop: power bank;Carrousel;IG, FB;Nouvelle série;\n"
            ."07/11/2026;20h;;Vidéo;TT;;\n";

        Livewire::actingAs($this->admin)->test(ImportExport::class)
            ->set('file', UploadedFile::fake()->createWithContent('calendrier.csv', $csv))
            ->assertSet('mapping.date', 'date')
            ->assertSet('mapping.platforms', 'plateformes')
            ->assertSet('mapping.caption', 'legende')
            ->call('import')
            ->assertSet('report.created', 2)
            ->assertSet('report.skipped', 1);

        $video = Post::firstWhere('title', 'Wait... same price? Power bank');
        $this->assertSame('video', $video->format->value);
        $this->assertSame('2026-11-03 20:00', $video->planned_at->format('Y-m-d H:i'));
        $this->assertEqualsCanonicalizing(['tiktok', 'instagram'], $video->variants->map->platform->pluck('key')->all());
        $this->assertSame('Wait... nefs taman?', $video->variants->first()->caption);

        $static = Post::firstWhere('title', 'Verified by Groshop: power bank');
        $this->assertSame('static', $static->format->value);
        $this->assertSame('Nouvelle série', $static->series->name); // created on the fly
    }

    public function test_export_then_import_gives_the_same_posts_back(): void
    {
        $csv = $this->actingAs($this->admin)->get('/export/posts.csv?from=2026-10-01&to=2026-10-31')->assertOk()->streamedContent();
        $this->assertStringContainsString('Black Friday early access', $csv);

        $october = $this->groshop->posts()->whereBetween('planned_at', ['2026-10-01', '2026-10-31 23:59']);
        $captions = $october->clone()->with('variants')->get()->flatMap->variants->pluck('caption')->filter()->sort()->values()->all();
        $october->clone()->get()->each->forceDelete();

        Livewire::actingAs($this->admin)->test(ImportExport::class)
            ->set('file', UploadedFile::fake()->createWithContent('posts.csv', $csv))
            ->call('import');

        $back = $this->groshop->posts()->whereBetween('planned_at', ['2026-10-01', '2026-10-31 23:59'])->with('variants')->get();
        $this->assertCount(6, $back);
        $this->assertSame($captions, $back->flatMap->variants->pluck('caption')->filter()->sort()->values()->all());
        $this->assertTrue($back->firstWhere('title', 'Black Friday early access: message us on WhatsApp')->is_offer);
    }

    public function test_posts_already_there_are_skipped(): void
    {
        $csv = "date,time,brand,title,format,platform\n2026-10-24,20:00,GROSHOP,Livraison Day #1: from the packing table to the door,video,TikTok\n";

        Livewire::actingAs($this->admin)->test(ImportExport::class)
            ->set('file', UploadedFile::fake()->createWithContent('again.csv', $csv))
            ->call('import')
            ->assertSet('report.duplicates', 1)
            ->assertSet('report.created', 0);
    }

    public function test_community_manager_cannot_import(): void
    {
        Livewire::actingAs(User::firstWhere('email', 'cm@postflow.test'))->test(ImportExport::class)
            ->set('file', UploadedFile::fake()->createWithContent('x.csv', "date,title\n2026-11-01,Test\n"))
            ->assertForbidden();
    }

    public function test_example_file_downloads(): void
    {
        $this->actingAs($this->admin)->get('/export/example.csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->admin)->get('/import-export')->assertOk();
    }
}
