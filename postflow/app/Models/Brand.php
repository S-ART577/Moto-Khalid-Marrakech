<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'slug', 'logo_path', 'color_bg', 'color_bg_2', 'color_primary', 'color_primary_text',
    'color_light', 'color_accent', 'tone_notes', 'tagline', 'signature', 'whatsapp', 'active', 'position',
])]
class Brand extends Model
{
    /** The GROSHOP identity, used for "All brands" and as defaults for new brands. */
    public const DEFAULT_COLORS = [
        'color_bg' => '#102533',
        'color_bg_2' => '#17344c',
        'color_primary' => '#f02443',
        'color_primary_text' => '#d61e3a',
        'color_light' => '#dfebf7',
        'color_accent' => '#43dde6',
    ];

    /** Same defaults as the database, so a new Brand object is complete before it is saved. */
    protected $attributes = self::DEFAULT_COLORS + ['active' => true, 'position' => 0];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class)
            ->withPivot(['handle', 'profile_url'])
            ->orderBy('platforms.position');
    }

    public function series(): HasMany
    {
        return $this->hasMany(Series::class)->orderBy('position')->orderBy('name');
    }

    public function productFamilies(): HasMany
    {
        return $this->hasMany(ProductFamily::class)->orderBy('position')->orderBy('name');
    }

    public function postingSlots(): HasMany
    {
        return $this->hasMany(PostingSlot::class)->orderBy('weekday')->orderBy('time');
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(Quota::class);
    }

    public function monthNotes(): HasMany
    {
        return $this->hasMany(MonthNote::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    /** @return array<string, string> colour tokens used by the layout */
    public function colors(): array
    {
        return collect(self::DEFAULT_COLORS)->mapWithKeys(fn ($default, $key) => [$key => $this->{$key} ?: $default])->all();
    }

    /** "Tue video · Thu static · Sat video" */
    public function rhythmLabel(): string
    {
        return $this->postingSlots
            ->map(fn (PostingSlot $slot) => $slot->dayShort().' '.mb_strtolower($slot->format->label()))
            ->implode(' · ');
    }
}
