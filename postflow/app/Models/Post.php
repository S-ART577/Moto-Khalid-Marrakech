<?php

namespace App\Models;

use App\Enums\Format;
use App\Enums\Stage;
use App\Enums\VariantStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'brand_id', 'series_id', 'product_family_id', 'title', 'brief', 'format', 'is_offer',
    'planned_at', 'stage', 'owner_id', 'due_on', 'created_by',
])]
class Post extends Model
{
    use SoftDeletes;

    /** Checklist added to every new post. Edit freely. */
    public const CHECKLIST = [
        'video' => ['Hook + script', 'Shoot', 'Edit + subtitles', 'Cover frame', 'Captions + hashtags', 'Final file on Drive'],
        'static' => ['Brief + copy', 'Design', 'Captions + hashtags', 'Final file on Drive'],
    ];

    protected $attributes = ['stage' => 'brief', 'is_offer' => false];

    protected function casts(): array
    {
        return [
            'format' => Format::class,
            'stage' => Stage::class,
            'is_offer' => 'boolean',
            'planned_at' => 'datetime',
            'due_on' => 'date',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PostVariant::class)->orderBy('publish_at');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /**
     * Create a draft post with one version per brand platform and the default checklist.
     * Used by "Fill empty slots" and the CSV import.
     */
    public static function draft(Brand $brand, Format $format, ?CarbonInterface $at, array $attributes = []): self
    {
        $post = $brand->posts()->create($attributes + [
            'title' => $format === Format::Video ? __('Video idea') : __('Static idea'),
            'format' => $format,
            'planned_at' => $at,
            'stage' => Stage::Brief,
            'owner_id' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        foreach ($brand->platforms as $platform) {
            $placement = $format->placements($platform->key)[0];
            $post->variants()->create([
                'platform_id' => $platform->id,
                'placement' => $placement,
                'aspect_ratio' => $format->defaultRatio($placement),
                'publish_at' => $at,
            ]);
        }

        foreach (self::CHECKLIST[$format->value] as $position => $label) {
            $post->checklistItems()->create(['label' => __($label), 'position' => $position]);
        }

        return $post;
    }

    /** Only posts of the given brands. */
    #[Scope]
    protected function inBrands(Builder $query, array $brandIds): void
    {
        $query->whereIn('brand_id', $brandIds);
    }

    /** video | static | offer: drives the colour on the calendar. */
    public function kind(): string
    {
        return $this->is_offer ? 'offer' : $this->format->value;
    }

    public function kindLabel(): string
    {
        return $this->is_offer ? __('Offer') : $this->format->label();
    }

    /**
     * Move the post to a new date/time. Every platform variant moves by the same
     * amount, so each keeps its own time relative to the others.
     */
    public function moveTo(CarbonInterface $newStart): void
    {
        $oldStart = $this->planned_at ?? $newStart;
        $deltaSeconds = $newStart->getTimestamp() - $oldStart->getTimestamp();

        $this->planned_at = $newStart;
        $this->save();

        foreach ($this->variants as $variant) {
            $variant->publish_at = ($variant->publish_at ?? $oldStart)->copy()->addSeconds($deltaSeconds);
            $variant->save();
            // A post moved to another time needs fresh reminders.
            $variant->sentReminders()->delete();
        }
    }

    /** When every platform is posted (or skipped), the post itself becomes Posted. */
    public function syncStageFromVariants(): void
    {
        $variants = $this->variants()->get();

        $done = $variants->isNotEmpty()
            && $variants->every(fn (PostVariant $v) => $v->status !== VariantStatus::Planned);

        if ($done && $this->stage !== Stage::Posted) {
            $this->update(['stage' => Stage::Posted]);
        } elseif (! $done && $this->stage === Stage::Posted) {
            $this->update(['stage' => Stage::Scheduled]);
        }
    }

    public function checklistProgress(): array
    {
        $items = $this->relationLoaded('checklistItems') ? $this->checklistItems : $this->checklistItems()->get();

        return [$items->where('done', true)->count(), $items->count()];
    }

    public function isProductionLate(): bool
    {
        return $this->due_on !== null
            && $this->due_on->isPast() && ! $this->due_on->isToday()
            && $this->stage->position() < Stage::Scheduled->position();
    }
}
