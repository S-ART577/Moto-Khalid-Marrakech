<?php

namespace App\Models;

use App\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'post_id', 'platform_id', 'placement', 'aspect_ratio', 'caption', 'hashtags', 'publish_at',
    'status', 'post_url', 'posted_at', 'posted_by',
])]
class PostVariant extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VariantStatus::class,
            'publish_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function sentReminders(): HasMany
    {
        return $this->hasMany(SentReminder::class);
    }

    public function metric(): HasOne
    {
        return $this->hasOne(Metric::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === VariantStatus::Planned
            && $this->publish_at !== null
            && $this->publish_at->isPast();
    }

    public function markPosted(User $user, ?string $url = null): void
    {
        $this->update([
            'status' => VariantStatus::Posted,
            'post_url' => $url ?: $this->post_url,
            'posted_at' => now(),
            'posted_by' => $user->id,
        ]);

        $this->post->syncStageFromVariants();
    }
}
