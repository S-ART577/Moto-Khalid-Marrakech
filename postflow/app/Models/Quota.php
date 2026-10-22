<?php

namespace App\Models;

use App\Enums\Format;
use App\Enums\QuotaDimension;
use App\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Collection;
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

    /** How many of these posts count towards this target. */
    public function countIn(Collection $posts): int
    {
        return match ($this->dimension) {
            QuotaDimension::Total => $posts->count(),
            QuotaDimension::Offer => $posts->where('is_offer', true)->count(),
            QuotaDimension::Format => $posts->filter(fn ($post) => $post->format->value === $this->dimension_ref)->count(),
            QuotaDimension::Series => $posts->where('series_id', (int) $this->dimension_ref)->count(),
            QuotaDimension::ProductFamily => $posts->where('product_family_id', (int) $this->dimension_ref)->count(),
            QuotaDimension::Platform => $posts->filter(fn ($post) => $post->variants->contains(
                fn ($variant) => $variant->platform->key === $this->dimension_ref && $variant->status !== VariantStatus::Skipped
            ))->count(),
        };
    }

    /** "1 video missing", "2 Snapchat posts missing"... */
    public function missingLabel(int $missing): string
    {
        return match ($this->dimension) {
            QuotaDimension::Total => trans_choice(':count post missing|:count posts missing', $missing),
            QuotaDimension::Offer => trans_choice(':count offer missing|:count offers missing', $missing),
            QuotaDimension::Format => $this->dimension_ref === Format::Video->value
                ? trans_choice(':count video missing|:count videos missing', $missing)
                : trans_choice(':count static post missing|:count static posts missing', $missing),
            QuotaDimension::Platform => trans_choice(':count :platform post missing|:count :platform posts missing', $missing, ['platform' => $this->refLabel()]),
            QuotaDimension::Series => trans_choice(':count ":series" missing|:count ":series" missing', $missing, ['series' => $this->refLabel()]),
            QuotaDimension::ProductFamily => trans_choice(':count :family post missing|:count :family posts missing', $missing, ['family' => $this->refLabel()]),
        };
    }

    /** Human label of what is counted, e.g. "Video", "Livraison Day", "Instagram". */
    public function refLabel(): string
    {
        return match ($this->dimension) {
            QuotaDimension::Total => __('All posts'),
            QuotaDimension::Offer => __('Offers'),
            QuotaDimension::Format => $this->dimension_ref === Format::Video->value ? __('Videos') : __('Static posts'),
            QuotaDimension::Platform => Platform::firstWhere('key', $this->dimension_ref)?->name ?? '?',
            QuotaDimension::Series => Series::find($this->dimension_ref)?->name ?? '?',
            QuotaDimension::ProductFamily => ProductFamily::find($this->dimension_ref)?->name ?? '?',
        };
    }
}
