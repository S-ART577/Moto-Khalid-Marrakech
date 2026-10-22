<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'month', 'theme_title', 'theme_body', 'offer_title', 'offer_body', 'dates_title', 'dates_body'])]
class MonthNote extends Model
{
    protected function casts(): array
    {
        return ['month' => 'date'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
