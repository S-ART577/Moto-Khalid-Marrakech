<?php

namespace App\Livewire;

use App\Enums\EventCategory;
use App\Models\SeasonalEvent;
use App\Support\BrandContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Seasonal and offer calendar: Moroccan dates with a countdown and the
 * "shoot by" / "design by" deadlines that come from the lead times.
 */
class Seasons extends Component
{
    public ?int $editingId = null;

    public array $form = [];

    #[Computed]
    public function events()
    {
        return SeasonalEvent::forBrands(BrandContext::make()->ids())
            ->with('brand')
            ->orderBy('starts_on')
            ->get();
    }

    #[Computed]
    public function groups(): array
    {
        $today = CarbonImmutable::today();
        $events = $this->events;

        return [
            'soon' => $events->filter(fn ($e) => $e->endDate()->gte($today) && $e->starts_on->lte($today->addDays(90))),
            'later' => $events->filter(fn ($e) => $e->starts_on->gt($today->addDays(90))),
            'past' => $events->filter(fn ($e) => $e->endDate()->lt($today))->reverse(),
        ];
    }

    public function create(): void
    {
        Gate::authorize('admin');

        $this->editingId = 0;
        $this->form = [
            'name_fr' => '', 'name_en' => '', 'starts_on' => CarbonImmutable::today()->addMonth()->toDateString(), 'ends_on' => null,
            'category' => EventCategory::Commercial->value, 'is_approximate' => false,
            'shoot_lead_days' => 14, 'design_lead_days' => 7, 'guidance' => '',
            'brand_id' => BrandContext::make()->current()?->id,
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('admin');

        $event = SeasonalEvent::findOrFail($id);
        $this->editingId = $event->id;
        $this->form = [
            'name_fr' => $event->name_fr, 'name_en' => $event->name_en,
            'starts_on' => $event->starts_on->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
            'category' => $event->category->value, 'is_approximate' => $event->is_approximate,
            'shoot_lead_days' => $event->shoot_lead_days, 'design_lead_days' => $event->design_lead_days,
            'guidance' => $event->guidance ?? '', 'brand_id' => $event->brand_id,
        ];
    }

    public function save(): void
    {
        Gate::authorize('admin');

        $data = $this->validate([
            'form.name_fr' => ['required', 'string', 'max:80'],
            'form.name_en' => ['nullable', 'string', 'max:80'],
            'form.starts_on' => ['required', 'date'],
            'form.ends_on' => ['nullable', 'date', 'after_or_equal:form.starts_on'],
            'form.category' => ['required', Rule::enum(EventCategory::class)],
            'form.is_approximate' => ['boolean'],
            'form.shoot_lead_days' => ['required', 'integer', 'min:0', 'max:180'],
            'form.design_lead_days' => ['required', 'integer', 'min:0', 'max:180'],
            'form.guidance' => ['nullable', 'string', 'max:500'],
            'form.brand_id' => ['nullable', 'exists:brands,id'],
        ], [], [
            'form.name_fr' => __('Name (French)'),
            'form.starts_on' => __('Start'),
            'form.ends_on' => __('End'),
        ])['form'];

        $data['name_en'] = $data['name_en'] ?: $data['name_fr'];
        $data['ends_on'] = $data['ends_on'] ?: null;
        $data['brand_id'] = $data['brand_id'] ?: null;

        SeasonalEvent::updateOrCreate(['id' => $this->editingId ?: null], $data);

        $this->editingId = null;
        unset($this->events, $this->groups);
        $this->dispatch('toast', message: __('Saved'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('admin');

        SeasonalEvent::whereKey($id)->delete();
        $this->editingId = null;
        unset($this->events, $this->groups);
        $this->dispatch('toast', message: __('Deleted'));
    }

    public function render()
    {
        return view('livewire.seasons')->title(__('Seasons'));
    }
}
