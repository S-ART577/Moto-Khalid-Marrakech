<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'locale', 'notify_email', 'notify_push', 'reminder_minutes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Same defaults as the database, so a new User object is complete before it is saved. */
    protected $attributes = [
        'role' => 'community_manager',
        'locale' => 'fr',
        'notify_email' => true,
        'notify_push' => true,
        'reminder_minutes' => 30,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Brands this person is assigned to (the admin sees every brand anyway). */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return Collection<int, Brand> */
    public function accessibleBrands(): Collection
    {
        $query = $this->isAdmin() ? Brand::query() : $this->brands();

        return $query->where('active', true)->orderBy('position')->orderBy('name')->get();
    }

    public function canAccessBrand(Brand|int $brand): bool
    {
        $id = $brand instanceof Brand ? $brand->id : $brand;

        return $this->isAdmin() || $this->brands()->whereKey($id)->exists();
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
