<?php

namespace App\Support;

use App\Enums\Format;
use App\Enums\Stage;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a content calendar exported from Google Sheets (CSV).
 *
 * Works with one line per post ("Platforms: TT, IG, FB") or one line per platform
 * (like PostFlow's own export): lines with the same brand + date + title become one post.
 */
class CalendarImporter
{
    /** PostFlow field => column names it recognises (normalized: lowercase, no accents). */
    public const FIELDS = [
        'date' => ['date', 'jour', 'day', 'date_de_publication', 'publish_date', 'date_publication'],
        'time' => ['time', 'heure', 'hour', 'publish_time', 'horaire'],
        'brand' => ['brand', 'marque', 'page', 'compte', 'account'],
        'title' => ['title', 'titre', 'idee', 'idea', 'sujet', 'concept', 'post', 'nom', 'contenu'],
        'format' => ['format', 'type', 'type_de_post', 'type_post'],
        'offer' => ['offer', 'offre', 'promo', 'promotion'],
        'series' => ['series', 'serie', 'rubrique', 'pilier', 'pillar', 'categorie_contenu'],
        'product_family' => ['product_family', 'famille', 'famille_produit', 'famille_de_produits', 'produit', 'product', 'categorie', 'category'],
        'platforms' => ['platform', 'platforms', 'plateforme', 'plateformes', 'reseau', 'reseaux', 'network', 'networks', 'canal', 'canaux'],
        'caption' => ['caption', 'legende', 'texte', 'copy', 'description', 'caption_text'],
        'hashtags' => ['hashtags', 'hashtag', 'tags'],
        'owner' => ['owner', 'responsable', 'assigne', 'assigned_to', 'qui', 'who'],
        'stage' => ['stage', 'etape', 'statut', 'status', 'avancement'],
        'brief' => ['brief', 'notes', 'note', 'details', 'commentaire', 'commentaires'],
        'drive' => ['drive_links', 'drive', 'lien_drive', 'asset', 'assets', 'fichier', 'lien', 'link', 'url_drive'],
    ];

    public static function labels(): array
    {
        return [
            'date' => __('Publish date'), 'time' => __('Time'), 'brand' => __('Brand'), 'title' => __('Title'),
            'format' => __('Format'), 'offer' => __('Offer'), 'series' => __('Series'), 'product_family' => __('Product family'),
            'platforms' => __('Platforms'), 'caption' => __('Caption'), 'hashtags' => __('Hashtags'), 'owner' => __('Owner'),
            'stage' => __('Stage'), 'brief' => __('Brief'), 'drive' => __('Drive link'),
        ];
    }

    /**
     * Best guess of which column holds which field.
     *
     * @param  array<int, string>  $headers  original header texts
     * @return array<string, ?string> field => normalized column key
     */
    public static function guessMapping(array $headers): array
    {
        $keys = array_map([Csv::class, 'key'], $headers);
        $mapping = [];

        foreach (self::FIELDS as $field => $names) {
            $mapping[$field] = collect($names)->first(fn ($name) => in_array($name, $keys, true));
        }

        // "status" is the platform status in PostFlow's export; use it as the stage only when there is no stage column.
        if ($mapping['stage'] === 'status' && in_array('stage', $keys, true)) {
            $mapping['stage'] = 'stage';
        }

        return $mapping;
    }

    /** @param  Collection<int, Brand>  $brands  brands the person may import into */
    public function __construct(private Collection $brands, private ?Brand $defaultBrand = null) {}

    /**
     * Read every line into a clean structure, with the problems found.
     *
     * @return array<int, array{line: int, brand: ?Brand, date: ?CarbonImmutable, title: string, format: Format, offer: bool, platforms: array<int, string>, errors: array<int, string>, raw: array}>
     */
    public function parse(array $rows, array $mapping): array
    {
        $get = fn (array $row, string $field) => isset($mapping[$field]) && $mapping[$field] !== null ? trim((string) ($row[$mapping[$field]] ?? '')) : '';
        $parsed = [];

        foreach ($rows as $i => $row) {
            $errors = [];

            $brandName = $get($row, 'brand');
            $brand = $brandName !== ''
                ? $this->brands->first(fn (Brand $b) => Str::lower($b->name) === Str::lower($brandName) || $b->slug === Str::slug($brandName))
                : $this->defaultBrand;
            if (! $brand) {
                $errors[] = $brandName !== '' ? __('unknown brand ":name"', ['name' => $brandName]) : __('no brand (choose one above)');
            }

            $date = self::parseDate($get($row, 'date'), $get($row, 'time'));
            if ($get($row, 'date') !== '' && ! $date) {
                $errors[] = __('date not understood ":value"', ['value' => $get($row, 'date')]);
            }

            $title = $get($row, 'title');
            if ($title === '') {
                $errors[] = __('no title');
            }

            $formatText = Str::lower(Str::ascii($get($row, 'format')));
            $offerText = Str::lower(Str::ascii($get($row, 'offer')));

            $parsed[] = [
                'line' => $i + 2,
                'brand' => $brand,
                'date' => $date,
                'title' => $title,
                // "Vidéo", "Reel" = video. "Statique", "Carrousel", "Offre" = static. Empty = video.
                'format' => preg_match('/vid|reel|tiktok|short/', $formatText) || ! preg_match('/stat|post|carrou|carou|photo|image|visuel|offre|offer|promo/', $formatText)
                    ? Format::Video
                    : Format::Static,
                'offer' => (bool) preg_match('/^(yes|oui|x|1|true|vrai)$/', $offerText) || (bool) preg_match('/offre|offer|promo/', $formatText),
                'platforms' => self::parsePlatforms($get($row, 'platforms')),
                'series' => $get($row, 'series'),
                'product_family' => $get($row, 'product_family'),
                'caption' => $get($row, 'caption'),
                'hashtags' => $get($row, 'hashtags'),
                'owner' => $get($row, 'owner'),
                'stage' => self::parseStage($get($row, 'stage')),
                'brief' => $get($row, 'brief'),
                'drive' => $get($row, 'drive'),
                'errors' => $errors,
            ];
        }

        return $parsed;
    }

    /**
     * Create the posts. Lines with errors are skipped, existing posts (same brand, day and title) too.
     *
     * @return array{created: int, duplicates: int, skipped: int}
     */
    public function import(array $parsed, bool $createMissing = true): array
    {
        $result = ['created' => 0, 'duplicates' => 0, 'skipped' => 0];
        $platforms = Platform::all();
        $people = User::all();

        $groups = collect($parsed)
            ->reject(function ($line) use (&$result) {
                if ($line['errors']) {
                    $result['skipped']++;

                    return true;
                }

                return false;
            })
            ->groupBy(fn ($line) => $line['brand']->id.'|'.($line['date']?->toDateString() ?? 'idea').'|'.Str::lower($line['title']));

        DB::transaction(function () use ($groups, $platforms, $people, $createMissing, &$result) {
            foreach ($groups as $lines) {
                $first = $lines->first();
                $brand = $first['brand']->loadMissing(['platforms', 'series', 'productFamilies']);

                $exists = $brand->posts()->where('title', $first['title'])
                    ->when($first['date'], fn ($q) => $q->whereBetween('planned_at', [$first['date']->startOfDay(), $first['date']->endOfDay()]), fn ($q) => $q->whereNull('planned_at'))
                    ->exists();
                if ($exists) {
                    $result['duplicates']++;

                    continue;
                }

                $post = $brand->posts()->create([
                    'title' => $first['title'],
                    'brief' => $lines->pluck('brief')->filter()->unique()->implode("\n") ?: null,
                    'format' => $first['format'],
                    'is_offer' => $first['offer'],
                    'planned_at' => $first['date'],
                    'stage' => $first['stage'] ?? Stage::Brief,
                    'series_id' => $this->findOrCreate($brand, 'series', $first['series'], $createMissing),
                    'product_family_id' => $this->findOrCreate($brand, 'productFamilies', $first['product_family'], $createMissing),
                    'owner_id' => $this->findPerson($people, $first['owner']),
                    'created_by' => auth()->id(),
                ]);

                // Platforms: listed on the lines, or every platform of the brand.
                $wanted = $lines->flatMap(fn ($line) => $line['platforms'])->unique();
                $targets = $wanted->isEmpty() ? $brand->platforms : $platforms->whereIn('key', $wanted->all());

                foreach ($targets as $platform) {
                    $line = $lines->first(fn ($l) => in_array($platform->key, $l['platforms'], true)) ?? $first;
                    $placement = $post->format->placements($platform->key)[0];
                    $post->variants()->create([
                        'platform_id' => $platform->id,
                        'placement' => $placement,
                        'aspect_ratio' => $post->format->defaultRatio($placement),
                        'caption' => $line['caption'] ?: null,
                        'hashtags' => $line['hashtags'] ?: null,
                        'publish_at' => $line['date'] ?? $first['date'],
                    ]);
                }

                foreach (Post::CHECKLIST[$post->format->value] as $position => $label) {
                    $post->checklistItems()->create(['label' => __($label), 'position' => $position]);
                }
                foreach ($lines->pluck('drive')->filter()->flatMap(fn ($links) => preg_split('/\s+/', $links))->filter(fn ($url) => filter_var($url, FILTER_VALIDATE_URL))->unique() as $url) {
                    $post->assets()->create(['label' => __('Drive file'), 'url' => $url]);
                }

                ActivityLog::record($post, 'created', ['import' => true]);
                $result['created']++;
            }
        });

        return $result;
    }

    /** "20/10/2026", "2026-10-20", "20-10-26", "20.10.2026" + "20:00", "20h", "20h30", "8:00 PM". */
    public static function parseDate(string $date, string $time = ''): ?CarbonImmutable
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $day = null;
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $date, $m)) {
            $day = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})/', $date, $m)) {
            [$a, $b, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $year = $year < 100 ? 2000 + $year : $year;
            // Day first (Morocco), unless that is impossible.
            [$dayOfMonth, $month] = $b > 12 ? [$b, $a] : [$a, $b];
            $day = [$year, $month, $dayOfMonth];
        }

        if (! $day || ! checkdate($day[1], $day[2], $day[0])) {
            return null;
        }

        // A time inside the date cell ("20/10/2026 20:00") counts too.
        $time = trim($time) ?: (preg_match('/\d{1,2}[:h]\d{0,2}\s*(am|pm)?$/i', $date, $t) ? $t[0] : '');
        [$hour, $minute] = [20, 0];
        if (preg_match('/^(\d{1,2})\s*[:hH]\s*(\d{2})?\s*(am|pm)?$/i', $time, $t)) {
            $hour = (int) $t[1];
            $minute = (int) ($t[2] ?? 0);
            if (isset($t[3]) && strtolower($t[3]) === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if (isset($t[3]) && strtolower($t[3]) === 'am' && $hour === 12) {
                $hour = 0;
            }
        }

        return CarbonImmutable::create($day[0], $day[1], $day[2], min($hour, 23), min($minute, 59));
    }

    /** "TT, IG / FB + snap" -> [tiktok, instagram, facebook, snapchat] */
    public static function parsePlatforms(string $text): array
    {
        $map = [
            'tt' => 'tiktok', 'tiktok' => 'tiktok', 'tik' => 'tiktok',
            'ig' => 'instagram', 'insta' => 'instagram', 'instagram' => 'instagram', 'reels' => 'instagram',
            'fb' => 'facebook', 'facebook' => 'facebook', 'meta' => 'facebook',
            'sc' => 'snapchat', 'snap' => 'snapchat', 'snapchat' => 'snapchat',
        ];

        $tokens = preg_split('/[\s,\/;+&|]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(array_map(fn ($token) => $map[$token] ?? null, $tokens))));
    }

    public static function parseStage(string $text): ?Stage
    {
        $text = Str::lower(Str::ascii(trim($text)));
        if ($text === '') {
            return null;
        }

        foreach (Stage::cases() as $stage) {
            if ($text === $stage->value) {
                return $stage;
            }
        }

        return match (true) {
            Str::contains($text, ['publie', 'posted', 'poste', 'fait', 'done']) => Stage::Posted,
            Str::contains($text, ['programme', 'scheduled', 'planifie', 'pret', 'ready']) => Stage::Scheduled,
            Str::contains($text, ['valid', 'review', 'relecture']) => Stage::Review,
            Str::contains($text, ['design', 'graph', 'visuel']) => Stage::Design,
            Str::contains($text, ['montage', 'edit']) => Stage::Edit,
            Str::contains($text, ['tournage', 'shoot', 'film']) => Stage::Shoot,
            default => Stage::Brief,
        };
    }

    private function findOrCreate(Brand $brand, string $relation, string $name, bool $create): ?int
    {
        if ($name === '') {
            return null;
        }

        $existing = $brand->{$relation}->first(fn ($item) => Str::lower($item->name) === Str::lower($name));
        if ($existing || ! $create) {
            return $existing?->id;
        }

        $item = $brand->{$relation}()->create(['name' => $name, 'position' => $brand->{$relation}->count()]);
        $brand->setRelation($relation, $brand->{$relation}->push($item));

        return $item->id;
    }

    private function findPerson(Collection $people, string $who): ?int
    {
        if ($who === '') {
            return null;
        }

        return $people->first(fn (User $user) => Str::lower($user->email) === Str::lower($who)
            || Str::lower($user->name) === Str::lower($who)
            || Str::startsWith(Str::lower($user->name), Str::lower($who)))?->id;
    }
}
