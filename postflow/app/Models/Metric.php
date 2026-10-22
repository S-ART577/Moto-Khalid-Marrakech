<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['post_variant_id', 'views', 'likes', 'comments', 'saves', 'shares', 'whatsapp_orders', 'source'])]
class Metric extends Model
{
    public const FIELDS = ['views', 'likes', 'comments', 'saves', 'shares', 'whatsapp_orders'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PostVariant::class, 'post_variant_id');
    }

    /** Likes + comments + saves + shares, divided by views. */
    public function engagementRate(): ?float
    {
        if ($this->views === 0) {
            return null;
        }

        return ($this->likes + $this->comments + $this->saves + $this->shares) / $this->views;
    }
}
