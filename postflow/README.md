# PostFlow

Social media planning for a small team: one place that answers, every day,
**what to post, on which platform, when, who is on it, what is still missing this week, and what worked.**

PostFlow plans, tracks and reminds. It does **not** publish anything for you and needs no platform API.

- Laravel 13, Livewire 4 (with Alpine), Tailwind 4, MySQL / MariaDB
- French and English interface, Arabic / Darija captions (right-to-left text, IBM Plex Sans Arabic)
- Installable on a phone as an app (PWA)

---

## 1. Run it on your computer

You need PHP 8.3+, Composer, Node 20+ and MySQL (or MariaDB).

```bash
cd postflow
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database called `postflow`, then put your MySQL user and password in `.env`
(`DB_USERNAME`, `DB_PASSWORD`).

```bash
php artisan migrate --seed     # creates the tables + the demo data
php artisan storage:link       # lets brand logos show up
npm run build                  # builds CSS/JS once (use `npm run dev` while you edit)
php artisan serve              # http://localhost:8000
```

No MySQL at hand? Set `DB_CONNECTION=sqlite` in `.env` and remove the other `DB_` lines.
Laravel uses `database/database.sqlite`.

### Demo logins

| Who | Email | Password |
|---|---|---|
| You (admin) | `admin@postflow.test` | `password` |
| Community manager | `cm@postflow.test` | `password` |

Change both passwords in **Settings** before going live.

To start over with fresh demo data: `php artisan migrate:fresh --seed`.

### Tests

```bash
php artisan test
```

---

## 2. Where things are

| You want to change... | Look in |
|---|---|
| A screen (layout, text, buttons) | `resources/views/livewire/*.blade.php` |
| What a screen does (saving, loading) | `app/Livewire/*.php` (same name as the view) |
| Colours, fonts, buttons, cards | `resources/css/app.css` (tokens at the top) |
| Menu, top bar, brand switcher | `resources/views/layouts/app.blade.php` |
| Icons | `resources/views/components/icon.blade.php` |
| French text | `lang/fr.json` (English is the key, French the value) |
| Database tables | `database/migrations/` |
| Demo data | `database/seeders/DemoSeeder.php` |
| Who can do what | `app/Policies/PostPolicy.php` |
| Pipeline stages, formats, statuses | `app/Enums/` |

Every screen is a pair: a PHP class in `app/Livewire` and a Blade view in `resources/views/livewire`.

### Roles

| | Admin | Community manager |
|---|---|---|
| Brands, quotas, team, settings | Yes | No |
| Create posts, change dates and owners, delete | Yes | No |
| Edit captions, hashtags, assets, checklist, comments | Yes | Yes |
| Move pipeline cards | All stages | Up to Review |
| Approve (Review to Scheduled) | Yes | No |
| Mark as posted, enter metrics | Yes | Yes |

The community manager only sees the brands ticked for her in **Settings > Team**.

---

## 3. Put it online

Any PHP host with MySQL works (shared hosting included).

1. Upload the project, point the domain to the `public/` folder.
2. Run `composer install --no-dev`, `php artisan migrate --force`, `php artisan storage:link`, and upload the `public/build` folder
   produced by `npm run build` on your computer.
3. In `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, your MySQL
   and mail settings.
4. Add **one cron job** (reminders need it):

   ```
   * * * * * cd /path/to/postflow && php artisan schedule:run >> /dev/null 2>&1
   ```

Browser and phone notifications only work over **HTTPS**.

---

## 4. Build log (slices)

| Slice | What | Status |
|---|---|---|
| 0 | Foundation: login, roles, layout, colours, FR/EN, installable app | Done |
| 1 | Multi-brand workspace | Done |
| 2 | Calendar (month / week / list) with drag-and-drop, post panel | Done |
| 3 | Quota and gap radar | |
| 4 | Production pipeline | |
| 5 | Today screen + reminders | |
| 6 | Seasonal and offer calendar | |
| 7 | Performance log | |
| 8 | Import / export | |
