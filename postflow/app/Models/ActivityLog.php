<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'brand_id', 'subject_type', 'subject_id', 'action', 'details', 'created_at'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** "updated (date, stage)", "marked Instagram as posted"... for the history list. */
    public function description(): string
    {
        $details = $this->details ?? [];

        return match ($this->action) {
            'created' => __('created the post'),
            'updated' => __('updated').($details ? ' ('.collect($details)->map(fn ($field) => match ($field) {
                'title' => __('title'),
                'planned_at' => __('date'),
                'stage' => __('stage'),
                'owner_id' => __('owner'),
                'format' => __('format'),
                default => $field,
            })->implode(', ').')' : ''),
            'deleted' => __('deleted the post'),
            'duplicated' => __('created this copy'),
            'moved' => __('moved it to :date', ['date' => $details['to'] ?? '?']),
            'stage' => __('moved it to :stage', ['stage' => \App\Enums\Stage::tryFrom($details['to'] ?? '')?->label() ?? '?']),
            'posted' => __('marked :platform as posted', ['platform' => $details['platform'] ?? '']),
            'skipped' => __('skipped :platform', ['platform' => $details['platform'] ?? '']),
            'asset_added' => __('added a file'),
            default => $this->action,
        };
    }

    /** Record who did what. Called from the screens, not from model events, so it stays readable. */
    public static function record(Model $subject, string $action, ?array $details = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'brand_id' => $subject->brand_id ?? $subject->post?->brand_id ?? null,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'details' => $details,
        ]);
    }
}
