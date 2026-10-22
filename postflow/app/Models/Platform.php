<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'short', 'open_url', 'position'])]
#[WithoutTimestamps]
class Platform extends Model
{
    /** Link the "Open platform" button uses: the brand's own page when we know it. */
    public function openUrlFor(?Brand $brand = null): string
    {
        $profile = $brand?->platforms->firstWhere('id', $this->id)?->pivot?->profile_url;

        return $profile ?: $this->open_url;
    }
}
