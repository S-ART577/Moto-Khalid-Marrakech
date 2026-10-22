# PostFlow: notes for AI coding assistants

Read `README.md` first (setup, roles, where things are).

- Laravel 13 + Livewire 4 **class-based** components: `app/Livewire/X.php` + `resources/views/livewire/x.blade.php`.
  Full pages are routed with `Route::livewire()` in `routes/web.php`.
- Keep code simple: the owner edits it by hand. No extra packages unless needed; prefer plain Blade + Alpine.
- UI text: English keys with `__('...')`, French in `lang/fr.json`. Every new string needs a French entry.
- Design: GROSHOP tokens in `resources/css/app.css` (navy background, red `#d61e3a` for tiles with white text,
  `#f02443` only for accents, cyan rarely). Poppins titles, Inter body, 12px card radius, no emoji, min 14px text,
  WCAG AA contrast, keyboard accessible.
- Captions may be Arabic/Darija: use `dir="auto"` on caption inputs and outputs.
- Access: `App\Support\BrandContext` gives the brands in scope; `App\Policies\PostPolicy` holds the rules.
- Tests: `php artisan test` (SQLite in memory). The app itself runs on MySQL.
