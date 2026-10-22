<?php

namespace App\Models;

use App\Enums\Format;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'weekday', 'format', 'time'])]
class PostingSlot extends Model
{
    protected function casts(): array
    {
        return ['format' => Format::class, 'weekday' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** "Tue" / "mar." in the current language. */
    public function dayShort(): string
    {
        // 2024-01-01 was a Monday, so weekday 1..7 maps to 1..7 January 2024.
        return CarbonImmutable::create(2024, 1, $this->weekday)->locale(app()->getLocale())->isoFormat('ddd');
    }
}
