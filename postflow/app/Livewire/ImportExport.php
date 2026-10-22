<?php

namespace App\Livewire;

use App\Support\BrandContext;
use App\Support\CalendarImporter;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Import the Google Sheet calendar (CSV) in three steps: upload, check the columns, import.
 * Export posts and numbers to CSV.
 */
class ImportExport extends Component
{
    use WithFileUploads;

    public $file = null;

    /** Column headers of the uploaded file, as written in the sheet. */
    public array $headers = [];

    /** PostFlow field => normalized column key */
    public array $mapping = [];

    public ?int $brandId = null;

    public bool $createMissing = true;

    public ?array $report = null;

    // Export
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->brandId = BrandContext::make()->current()?->id;
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function updatedFile(): void
    {
        Gate::authorize('admin');
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']], [], ['file' => __('File')]);

        $csv = Csv::read($this->file->getRealPath());
        $this->headers = $csv['headers'];
        $this->mapping = CalendarImporter::guessMapping($csv['headers']);
        $this->report = null;
    }

    /** @return array<int, array<string, string>> */
    private function rows(): array
    {
        return $this->file ? Csv::read($this->file->getRealPath())['rows'] : [];
    }

    private function importer(): CalendarImporter
    {
        $brands = BrandContext::make()->brands();

        return new CalendarImporter($brands, $brands->firstWhere('id', $this->brandId));
    }

    #[Computed]
    public function preview(): array
    {
        if (! $this->file || empty($this->headers)) {
            return [];
        }

        return $this->importer()->parse($this->rows(), $this->mapping);
    }

    public function import(): void
    {
        Gate::authorize('admin');
        abort_unless($this->file, 400);

        $this->report = $this->importer()->import($this->preview, $this->createMissing);
        $this->reset('file', 'headers', 'mapping');
        unset($this->preview);

        $this->dispatch('toast', message: trans_choice(':count post imported|:count posts imported', $this->report['created']));
    }

    public function cancel(): void
    {
        $this->reset('file', 'headers', 'mapping', 'report');
    }

    public function render()
    {
        return view('livewire.import-export', [
            'columnOptions' => collect($this->headers)->mapWithKeys(fn ($header) => [Csv::key($header) => $header])->all(),
            'exportFrom' => CarbonImmutable::parse($this->from ?: now()),
            'platformNames' => \App\Models\Platform::pluck('name', 'key'),
        ])->title(__('Import / export'));
    }
}
