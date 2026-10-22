<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'brand_id', 'subject_type', 'subject_id', 'action', 'changes', 'created_at'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Record who did what. Called from the screens, not from model events, so it stays readable. */
    public static function record(Model $subject, string $action, ?array $changes = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'brand_id' => $subject->brand_id ?? $subject->post?->brand_id ?? null,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
