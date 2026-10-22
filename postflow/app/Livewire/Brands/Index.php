<?php

namespace App\Livewire\Brands;

use App\Models\Brand;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    #[Computed]
    public function brands()
    {
        return Brand::with(['platforms', 'postingSlots', 'series', 'users', 'quotas'])
            ->withCount(['posts as posts_this_month' => fn ($q) => $q->whereBetween('planned_at', [now()->startOfMonth(), now()->endOfMonth()])])
            ->orderBy('position')->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.brands.index')->title(__('Brands'));
    }
}
