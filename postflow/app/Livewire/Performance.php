<?php

namespace App\Livewire;

use App\Enums\VariantStatus;
use App\Models\Metric;
use App\Models\PostVariant;
use App\Support\BrandContext;
use App\Support\Csv;
use App\Support\Leaderboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Lightweight performance log: enter or import views, likes, saves, shares and
 * WhatsApp orders per platform, then see what works best.
 */
class Performance extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'leaderboard';

    #[Url]
    public string $period = '90';

    #[Url]
    public string $sort = 'views';

    #[Url]
    public string $month = '';

    /** [variant id => [views, likes, comments, saves, shares, whatsapp_orders]] */
    public array $values = [];

    public $file = null;

    public ?array $importReport = null;

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
        $this->loadValues();
    }

    #[Computed]
    public function leaderboard(): Leaderboard
    {
        $from = is_numeric($this->period) ? CarbonImmutable::now()->subDays((int) $this->period) : null;

        return new Leaderboard(BrandContext::make()->ids(), $from);
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->month.'-01')->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    /** Posted platform versions of the month, to type the numbers in. */
    #[Computed]
    public function rows()
    {
        return $this->scoped()
            ->with(['metric', 'platform', 'post.brand'])
            ->where('status', VariantStatus::Posted)
            ->whereBetween('publish_at', [$this->monthStart, $this->monthStart->endOfMonth()])
            ->orderBy('publish_at')
            ->get();
    }

    public function updatedMonth(): void
    {
        unset($this->monthStart, $this->rows);
        $this->loadValues();
    }

    public function goMonth(int $delta): void
    {
        $this->month = $this->monthStart->addMonths($delta)->format('Y-m');
        $this->updatedMonth();
    }

    public function saveMetrics(): void
    {
        $this->validate(['values.*.*' => ['nullable', 'integer', 'min:0', 'max:1000000000']], [], ['values.*.*' => __('number')]);

        $saved = 0;
        $ids = $this->rows->pluck('id')->all();

        foreach ($this->values as $variantId => $numbers) {
            if (! in_array((int) $variantId, $ids, true) || collect($numbers)->every(fn ($n) => $n === '' || $n === null)) {
                continue;
            }

            Metric::updateOrCreate(
                ['post_variant_id' => $variantId],
                collect(Metric::FIELDS)->mapWithKeys(fn ($field) => [$field => (int) ($numbers[$field] ?? 0)])->all() + ['source' => 'manual']
            );
            $saved++;
        }

        unset($this->rows, $this->leaderboard);
        $this->dispatch('toast', message: trans_choice(':count line saved|:count lines saved', $saved));
    }

    /**
     * CSV with a "post_url" column (or date + platform + title) and the numbers.
     * The sheet from "Download this month's sheet" has exactly the right columns.
     */
    public function importCsv(): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [], ['file' => __('File')]);

        $csv = Csv::read($this->file->getRealPath());
        $matched = 0;
        $unmatched = [];

        foreach ($csv['rows'] as $i => $row) {
            $variant = $this->matchRow($row);

            if (! $variant) {
                $unmatched[] = __('Line :line', ['line' => $i + 2]).': '.Str::limit(implode(' · ', array_filter([$row['date'] ?? null, $row['platform'] ?? $row['plateforme'] ?? null, $row['title'] ?? $row['titre'] ?? null, $row['post_url'] ?? $row['lien'] ?? null])), 90);

                continue;
            }

            $numbers = [];
            foreach (Metric::FIELDS as $field) {
                $value = $row[$field] ?? $row[$this->frenchField($field)] ?? null;
                $numbers[$field] = (int) preg_replace('/[^\d]/', '', (string) $value);
            }

            Metric::updateOrCreate(['post_variant_id' => $variant->id], $numbers + ['source' => 'csv']);
            $matched++;
        }

        $this->importReport = ['matched' => $matched, 'unmatched' => $unmatched];
        $this->file = null;
        unset($this->rows, $this->leaderboard);
        $this->loadValues();
    }

    private function matchRow(array $row): ?PostVariant
    {
        $url = $row['post_url'] ?? $row['lien'] ?? $row['url'] ?? null;
        if ($url) {
            $variant = $this->scoped()->whereIn('post_url', [rtrim($url, '/'), rtrim($url, '/').'/'])->first();
            if ($variant) {
                return $variant;
            }
        }

        $date = $row['date'] ?? null;
        $platform = Str::lower($row['platform'] ?? $row['plateforme'] ?? '');
        if (! $date || ! $platform) {
            return null;
        }

        try {
            $day = CarbonImmutable::parse(str_contains($date, '/') ? CarbonImmutable::createFromFormat('d/m/Y', $date)->toDateString() : $date);
        } catch (\Throwable) {
            return null;
        }

        $title = $row['title'] ?? $row['titre'] ?? '';

        return $this->scoped()
            ->whereBetween('publish_at', [$day->startOfDay(), $day->endOfDay()])
            ->whereHas('platform', fn ($q) => $q->where('key', $platform)->orWhere('name', $platform)->orWhere('short', Str::upper($platform)))
            ->when($title, fn ($q) => $q->whereHas('post', fn ($q) => $q->where('title', $title)))
            ->first();
    }

    private function frenchField(string $field): string
    {
        return ['views' => 'vues', 'likes' => 'jaime', 'comments' => 'commentaires', 'saves' => 'enregistrements', 'shares' => 'partages', 'whatsapp_orders' => 'commandes_whatsapp'][$field];
    }

    private function scoped()
    {
        return PostVariant::query()->whereHas('post', fn ($q) => $q->inBrands(BrandContext::make()->ids()));
    }

    private function loadValues(): void
    {
        $this->values = [];
        foreach ($this->rows as $variant) {
            $this->values[$variant->id] = collect(Metric::FIELDS)
                ->mapWithKeys(fn ($field) => [$field => $variant->metric?->{$field} ?? ''])
                ->all();
        }
    }

    public function render()
    {
        return view('livewire.performance')->title(__('Performance'));
    }
}
