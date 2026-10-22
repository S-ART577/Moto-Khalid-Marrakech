<?php

namespace App\Models;

use App\Enums\Format;
use App\Enums\QuotaDimension;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'period', 'dimension', 'dimension_ref', 'target'])]
class Quota extends Model
{
    protected function casts(): array
    {
        return ['dimension' => QuotaDimension::class, 'target' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Human label of what is counted, e.g. "Video", "Livraison Day", "Instagram". */
    public function refLabel(): string
    {
        return match ($this->dimension) {
            QuotaDimension::Total => __('All posts'),
            QuotaDimension::Offer => __('Offers'),
            QuotaDimension::Format => Format::tryFrom((string) $this->dimension_ref)?->label() ?? '?',
            QuotaDimension::Platform => Platform::firstWhere('key', $this->dimension_ref)?->name ?? '?',
            QuotaDimension::Series => Series::find($this->dimension_ref)?->name ?? '?',
            QuotaDimension::ProductFamily => ProductFamily::find($this->dimension_ref)?->name ?? '?',
        };
    }
}
