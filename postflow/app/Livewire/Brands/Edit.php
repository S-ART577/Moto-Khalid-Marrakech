<?php

namespace App\Livewire\Brands;

use App\Enums\Format;
use App\Enums\QuotaDimension;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Platform;
use App\Models\User;
use App\Support\Color;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create or edit a brand: identity and colours, platforms, series,
 * product families, weekly rhythm, targets and who works on it.
 * Everything is saved together with one button.
 */
class Edit extends Component
{
    use WithFileUploads;

    public ?Brand $brand = null;

    public array $form = [];

    public $logo = null;

    public bool $removeLogo = false;

    /** [platform id => [enabled, handle, profile_url]] */
    public array $platforms = [];

    public array $series = [];

    public array $families = [];

    public array $postingSlots = [];

    public array $quotas = [];

    public array $userIds = [];

    private const IDENTITY = [
        'name', 'color_bg', 'color_bg_2', 'color_primary', 'color_primary_text', 'color_light', 'color_accent',
        'tone_notes', 'tagline', 'signature', 'whatsapp', 'active', 'position',
    ];

    public function mount(?Brand $brand = null): void
    {
        Gate::authorize('admin');

        $this->brand = $brand?->exists ? $brand->load(['platforms', 'series', 'productFamilies', 'postingSlots', 'quotas', 'users']) : null;
        $model = $this->brand ?? new Brand(Brand::DEFAULT_COLORS + ['active' => true, 'position' => Brand::max('position') + 1]);

        $this->form = $model->only(self::IDENTITY);

        foreach (Platform::orderBy('position')->get() as $platform) {
            $pivot = $this->brand?->platforms->firstWhere('id', $platform->id)?->pivot;
            $this->platforms[$platform->id] = [
                'enabled' => $this->brand ? $pivot !== null : true,
                'handle' => $pivot?->handle,
                'profile_url' => $pivot?->profile_url,
            ];
        }

        if ($this->brand) {
            $this->series = $this->brand->series->map->only(['id', 'name', 'color', 'description', 'active'])->all();
            $this->families = $this->brand->productFamilies->map->only(['id', 'name', 'max_gap_weeks', 'active'])->all();
            $this->postingSlots = $this->brand->postingSlots->map(fn ($s) => ['id' => $s->id, 'weekday' => $s->weekday, 'format' => $s->format->value, 'time' => $s->time])->all();
            $this->quotas = $this->brand->quotas->map(fn ($q) => [
                'id' => $q->id,
                'period' => $q->period,
                'what' => $q->dimension->value.':'.$q->dimension_ref,
                'target' => $q->target,
            ])->all();
            $this->userIds = $this->brand->users->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    public function addRow(string $list): void
    {
        $blank = match ($list) {
            'series' => ['id' => null, 'name' => '', 'color' => '#f02443', 'description' => '', 'active' => true],
            'families' => ['id' => null, 'name' => '', 'max_gap_weeks' => 3, 'active' => true],
            'postingSlots' => ['id' => null, 'weekday' => 2, 'format' => Format::Video->value, 'time' => '20:00'],
            'quotas' => ['id' => null, 'period' => 'week', 'what' => 'total:', 'target' => 3],
        };

        $this->{$list}[] = $blank;
    }

    public function removeRow(string $list, int $index): void
    {
        abort_unless(in_array($list, ['series', 'families', 'postingSlots', 'quotas'], true), 400);

        unset($this->{$list}[$index]);
        $this->{$list} = array_values($this->{$list});
    }

    /** Choices for "what is counted" in a target. */
    #[Computed]
    public function quotaOptions(): array
    {
        $options = [
            'total:' => __('All posts'),
            'format:video' => __('Videos'),
            'format:static' => __('Static posts'),
            'offer:' => __('Offers'),
        ];

        foreach (Platform::orderBy('position')->get() as $platform) {
            $options['platform:'.$platform->key] = __('Platform').': '.$platform->name;
        }
        foreach ($this->series as $row) {
            if (! empty($row['id'])) {
                $options['series:'.$row['id']] = __('Series').': '.$row['name'];
            }
        }
        foreach ($this->families as $row) {
            if (! empty($row['id'])) {
                $options['product_family:'.$row['id']] = __('Product family').': '.$row['name'];
            }
        }

        return $options;
    }

    #[Computed]
    public function contrastChecks(): array
    {
        $colors = Arr::only($this->form, array_keys(Brand::DEFAULT_COLORS));

        foreach ($colors as $value) {
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                return [];
            }
        }

        return Color::brandChecks($colors);
    }

    #[Computed]
    public function communityManagers()
    {
        return User::where('role', Role::CommunityManager)->orderBy('name')->get();
    }

    protected function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'form.name' => ['required', 'string', 'max:80'],
            'form.color_bg' => $hex,
            'form.color_bg_2' => $hex,
            'form.color_primary' => $hex,
            'form.color_primary_text' => $hex,
            'form.color_light' => $hex,
            'form.color_accent' => $hex,
            'form.tone_notes' => ['nullable', 'string', 'max:5000'],
            'form.tagline' => ['nullable', 'string', 'max:120'],
            'form.signature' => ['nullable', 'string', 'max:120'],
            'form.whatsapp' => ['nullable', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'form.active' => ['boolean'],
            'form.position' => ['integer', 'min:0'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'platforms.*.handle' => ['nullable', 'string', 'max:80'],
            'platforms.*.profile_url' => ['nullable', 'url', 'max:255'],
            'series.*.name' => ['required', 'string', 'max:80'],
            'series.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'series.*.description' => ['nullable', 'string', 'max:1000'],
            'families.*.name' => ['required', 'string', 'max:80'],
            'families.*.max_gap_weeks' => ['required', 'integer', 'min:1', 'max:26'],
            'postingSlots.*.weekday' => ['required', 'integer', 'between:1,7'],
            'postingSlots.*.format' => ['required', Rule::enum(Format::class)],
            'postingSlots.*.time' => ['required', 'date_format:H:i'],
            'quotas.*.period' => ['required', 'in:week,month'],
            'quotas.*.what' => ['required', Rule::in(array_keys($this->quotaOptions))],
            'quotas.*.target' => ['required', 'integer', 'min:1', 'max:200'],
            'userIds.*' => ['exists:users,id'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => __('Name'),
            'form.color_bg' => __('Background'),
            'form.color_bg_2' => __('Cards / second background'),
            'form.color_primary' => __('Accent red (bars, dots, badges)'),
            'form.color_primary_text' => __('Tile red (carries white text)'),
            'form.color_light' => __('Light (text, static tiles)'),
            'form.color_accent' => __('Highlight (used rarely)'),
            'form.whatsapp' => __('WhatsApp number'),
            'series.*.name' => __('Series name'),
            'families.*.name' => __('Product family name'),
            'platforms.*.profile_url' => __('Profile link'),
            'logo' => __('Logo'),
        ];
    }

    public function save()
    {
        Gate::authorize('admin');
        $this->validate();

        $brand = DB::transaction(function () {
            $brand = $this->brand ?? new Brand;
            $brand->fill($this->form);
            $brand->slug = $this->uniqueSlug($brand);

            if ($this->removeLogo) {
                $brand->logo_path = null;
            }
            if ($this->logo) {
                $brand->logo_path = $this->logo->store('logos', 'public');
            }
            $brand->save();

            $brand->platforms()->sync(collect($this->platforms)
                ->filter(fn ($row) => $row['enabled'])
                ->map(fn ($row) => ['handle' => $row['handle'] ?: null, 'profile_url' => $row['profile_url'] ?: null])
                ->all());

            $this->syncRows($brand->series(), $this->series, ['name', 'color', 'description', 'active'], true);
            $this->syncRows($brand->productFamilies(), $this->families, ['name', 'max_gap_weeks', 'active'], true);
            $this->syncRows($brand->postingSlots(), $this->postingSlots, ['weekday', 'format', 'time']);

            $quotaRows = collect($this->quotas)->map(function ($row) {
                [$dimension, $ref] = explode(':', $row['what'], 2);

                return ['id' => $row['id'], 'period' => $row['period'], 'dimension' => $dimension, 'dimension_ref' => $ref ?: null, 'target' => $row['target']];
            })->all();
            $this->syncRows($brand->quotas(), $quotaRows, ['period', 'dimension', 'dimension_ref', 'target']);

            // Targets pointing at a deleted series or family go too.
            $brand->quotas()->where('dimension', QuotaDimension::Series)->whereNotIn('dimension_ref', $brand->series()->pluck('id'))->delete();
            $brand->quotas()->where('dimension', QuotaDimension::ProductFamily)->whereNotIn('dimension_ref', $brand->productFamilies()->pluck('id'))->delete();

            $brand->users()->sync($this->userIds);

            return $brand;
        });

        session()->flash('toast', __('Brand saved'));

        return $this->redirectRoute('brands.edit', $brand);
    }

    public function delete()
    {
        Gate::authorize('admin');

        $this->brand?->delete();
        session()->forget('brand_id');
        session()->flash('toast', __('Brand deleted'));

        return $this->redirectRoute('brands.index');
    }

    /** Update existing rows, create new ones, delete the ones removed from the form. */
    private function syncRows(HasMany $relation, array $rows, array $fields, bool $withPosition = false): void
    {
        $keep = [];

        foreach (array_values($rows) as $index => $row) {
            $data = Arr::only($row, $fields);
            if ($withPosition) {
                $data['position'] = $index;
            }

            $model = ! empty($row['id']) ? (clone $relation)->find($row['id']) : null;
            if ($model) {
                $model->update($data);
            } else {
                $model = $relation->create($data);
            }
            $keep[] = $model->id;
        }

        (clone $relation)->whereNotIn('id', $keep)->delete();
    }

    private function uniqueSlug(Brand $brand): string
    {
        $base = Str::slug($this->form['name']) ?: 'brand';
        $slug = $base;
        $i = 2;

        while (Brand::where('slug', $slug)->when($brand->exists, fn ($q) => $q->whereKeyNot($brand->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.brands.edit', [
            'allPlatforms' => Platform::orderBy('position')->get(),
        ])->title($this->brand?->name ?? __('New brand'));
    }
}
