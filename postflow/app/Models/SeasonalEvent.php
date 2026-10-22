<?php

namespace App\Models;

use App\Enums\EventCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'brand_id', 'name_fr', 'name_en', 'starts_on', 'ends_on', 'category', 'is_approximate',
    'shoot_lead_days', 'design_lead_days', 'guidance',
])]
class SeasonalEvent extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'category' => EventCategory::class,
            'is_approximate' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Events shared by all brands plus the ones of the given brands. */
    #[Scope]
    protected function forBrands(Builder $query, array $brandIds): void
    {
        $query->where(fn ($q) => $q->whereNull('brand_id')->orWhereIn('brand_id', $brandIds));
    }

    /** Events that overlap the given period. */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_on', '<=', $to->toDateString())
            ->where(fn ($q) => $q->where('ends_on', '>=', $from->toDateString())
                ->orWhere(fn ($q) => $q->whereNull('ends_on')->where('starts_on', '>=', $from->toDateString())));
    }

    public function name(): string
    {
        return app()->getLocale() === 'fr' ? $this->name_fr : $this->name_en;
    }

    public function endDate(): CarbonInterface
    {
        return $this->ends_on ?? $this->starts_on;
    }

    public function covers(CarbonInterface $day): bool
    {
        return $day->betweenIncluded($this->starts_on->startOfDay(), $this->endDate()->endOfDay());
    }

    public function shootBy(): CarbonInterface
    {
        return $this->starts_on->copy()->subDays($this->shoot_lead_days);
    }

    public function designBy(): CarbonInterface
    {
        return $this->starts_on->copy()->subDays($this->design_lead_days);
    }

    public function daysUntil(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->starts_on, false);
    }
}
