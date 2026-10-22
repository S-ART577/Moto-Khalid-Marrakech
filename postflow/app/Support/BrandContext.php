<?php

namespace App\Support;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which brand the person is looking at right now ("All brands" = null).
 * The choice is kept in the session and set by the switcher in the top bar.
 */
class BrandContext
{
    private ?Collection $brands = null;

    private ?int $userId = null;

    public static function make(): self
    {
        return app(self::class);
    }

    /** @return Collection<int, Brand> brands this person may see */
    public function brands(): Collection
    {
        $user = auth()->user();

        // Cached for the request, per person.
        if ($this->brands === null || $this->userId !== $user?->id) {
            $this->userId = $user?->id;
            $this->brands = $user?->accessibleBrands() ?? new Collection;
        }

        return $this->brands;
    }

    public function current(): ?Brand
    {
        $id = session('brand_id');

        return $id ? $this->brands()->firstWhere('id', $id) : null;
    }

    /** @return array<int> brand ids in scope: the selected one, or all of them */
    public function ids(): array
    {
        $current = $this->current();

        return $current ? [$current->id] : $this->brands()->pluck('id')->all();
    }

    public function select(?int $brandId): void
    {
        $allowed = $brandId && $this->brands()->contains('id', $brandId);

        session(['brand_id' => $allowed ? $brandId : null]);
    }

    /** @return array<string, string> colour tokens for the layout */
    public function colors(): array
    {
        return $this->current()?->colors() ?? Brand::DEFAULT_COLORS;
    }
}
