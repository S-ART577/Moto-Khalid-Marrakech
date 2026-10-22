<?php

namespace App\Livewire;

use App\Support\BrandContext;
use App\Support\Radar as RadarReport;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Quota and gap radar: what is missing, week by week, and the content mix of the month. */
class Radar extends Component
{
    #[Url]
    public string $month = '';

    #[On('post-saved')]
    public function refresh(): void {}

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        try {
            return ($this->month ? CarbonImmutable::parse($this->month.'-01') : CarbonImmutable::today())->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::today()->startOfMonth();
        }
    }

    #[Computed]
    public function brands()
    {
        $context = BrandContext::make();

        return $context->brands()->whereIn('id', $context->ids())->values();
    }

    #[Computed]
    public function report(): RadarReport
    {
        return new RadarReport($this->brands);
    }

    public function go(int $months): void
    {
        $this->month = $this->monthStart->addMonths($months)->format('Y-m');
    }

    public function render()
    {
        return view('livewire.radar')->title(__('Radar'));
    }
}
