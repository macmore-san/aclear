# DTR System (aclear) — Project Context for Claude Code

Read this file first. This is a **separate, simplified DTR product** for a
small business, built on top of the `laravel/react-starter-kit` access-control
base. It is not the DOH project (`C:\laragon\www\dtr`) — rules differ
deliberately; see "Computation rules" below.

Backend and frontend are both done. The app is branded **AClear**; see
`PRODUCT.md` and `DESIGN.md` for product context and the shipped visual
system (a "sanitary permit / compliance register" world — see
`.impeccable/surfaces/app-shell.md` for the full direction contract).

---

## Stack

Laravel 13, PHP 8.4, Inertia v3 + React 19, shadcn/ui, Tailwind CSS 4,
Spatie Laravel Permission, TCPDF. MySQL in production; `phpunit.xml` runs
tests against in-memory SQLite.

---

## Access control (inherited from the starter kit — read this before adding routes)

- **Menus are DB rows** (`app/Models/Menu.php`), not a hardcoded nav. Each menu
  row with a non-null `permission` auto-creates four Spatie permissions on save:
  `{permission}.view|create|edit|delete` (see `Menu::booted()`).
- **`CheckMenuPermission` middleware** (aliased `menu.permission`) resolves a
  route name like `dtr.show` to its resource prefix (`dtr`), finds the Menu row
  whose `route` starts with `dtr.`, and requires `{permission}.{ability}` where
  ability comes from the action suffix (`index`/`show` → `view`, `store` →
  `create`, `update` → `edit`, `destroy` → `delete`; anything else falls back to
  `view` on GET, `edit` otherwise). **A route with no matching menu row is not
  guarded at all** — every new route needs a Menu row with a `route` matching
  its name prefix, or it's wide open.
- `Super Admin` bypasses every check (`Gate::before` — see `AppServiceProvider`).
- Adding a new protected feature = add the routes + add a Menu row (see
  `database/seeders/DtrMenuSeeder.php` for the pattern) + run
  `php artisan db:seed --class=DtrMenuSeeder` (or `db:seed` for a fresh DB, it's
  called from `DatabaseSeeder`).

---

## Tables (this feature's own — everything else is the starter kit's)

| Table                | Purpose                                                                                                                                         |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `employees`          | `emp_code` (unique, matches CSV `Ac-No`), `name`, `position`, `department`, `start_time` (nullable `time`, tardy basis), `is_active`            |
| `attendance_punches` | `emp_code`, `punch_time` (datetime), `source_file`, `uploaded_at`. Unique on `(emp_code, punch_time)` — a re-upload of the same file is a no-op |

No `work_shifts` / `shift_overrides` / `dtr_holidays` tables — this client has
no fixed schedule and no holiday calendar requirement.

---

## Request flow

```
CSV upload  →  PunchImportController::store  →  CsvPunchParser  →  attendance_punches (+ employees, from the Name column)
Print DTR   →  DtrController::show / pdf     →  DtrService::build →  DtrPdfService
```

- `App\Services\CsvPunchParser` — parses the `sTime` column of a ZKTeco export.
- `App\Services\DtrService` — pairs punches into shifts and computes hours (the
  core logic; see "Computation rules" below).
- `App\Services\DtrPdfService` — renders one employee's DTR as a TCPDF page;
  `DtrController::pdf` appends one page per employee into a single PDF.

---

## Computation rules (deliberately different from the DOH `dtr` project)

There is **no fixed company schedule**. Every employee just needs 10 hours a
day, from whenever they clock in to whenever they clock out — no lunch punches,
no grace period on arrival.

| Rule             | Value                                                                                                                                                                                                                                                                                            |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Worked hours     | Raw `Time Out − Time In`, no lunch deduction                                                                                                                                                                                                                                                     |
| Required         | 600 minutes (10:00) per shift — `config('dtr.required_minutes')`                                                                                                                                                                                                                                 |
| Undertime        | `max(0, required − worked)`. **Every minute counts**, no grace                                                                                                                                                                                                                                   |
| Overtime         | `max(0, worked − required)`. Only on a _complete_ shift (a shift missing Time Out gets 0 overtime, not a negative number)                                                                                                                                                                        |
| Tardy            | Compared against the **employee's own `start_time`** (no company-wide time). Below `config('dtr.tardy_threshold_minutes')` (60) late, it's **not tardy at all**. At or above it, the **full** minutes late count — no grace subtracted. No `start_time` set → tardy is never computed (always 0) |
| Overnight shifts | Supported — Time Out can be the next calendar day. The row is keyed to the **Time In date**; the PDF and JSON mark it with `out_next_day: true` / `(+1)`                                                                                                                                         |

### Shift-pairing algorithm (`DtrService::pairShifts`)

There's no way to know which punch is "in" and which is "out" from the data
alone (no `punch_state` column, unlike the DOH project) — a fixed 16-hour
window (`config('dtr.max_shift_hours')`) does the pairing instead:

1. A shift **opens** at a punch (call it P).
2. Every later punch within `max_shift_hours` of P joins the same shift; the
   **last one absorbed becomes Time Out**.
3. The next punch after that window opens a new shift.
4. If the resulting Time Out is closer than `min_shift_minutes` (10) to Time
   In, it's discarded (a double-tap on the device, not a real shift) — the
   shift is left incomplete.

Worked examples (`max_shift_hours = 16`):

- `[Mon 08:00, Mon 18:00]` → one shift, `08:00 → 18:00`, complete, 10:00 worked.
- `[Mon 22:00, Tue 06:00, Tue 20:00]` → shift 1 = `Mon 22:00 → Tue 06:00`
  (Tue 20:00 is _outside_ the 16h window measured from Mon 22:00). Tue 20:00
  opens shift 2, still open (incomplete) until a later punch closes it.
- `[Mon 08:00]` alone → incomplete (no Time Out).
- `[Mon 08:00, Mon 08:03]` → incomplete: 3 minutes is a double-tap, not a shift.

A shift belongs to the calendar date of its **Time In**, and only shifts whose
Time In falls inside the requested `[date_from, date_to]` range are included
— `DtrService::build` queries a window `max_shift_hours` wider on both ends
specifically so an overnight shift's tail (or a shift that starts the evening
before `date_from`) still pairs correctly before being dropped by that filter.

```
// ponytail: fixed-window ceiling — a missed Time Out followed by a Time In
// less than max_shift_hours later pairs into one (wrong) shift. Upgrade path:
// use the employee's start_time to decide where a new shift should begin,
// once real usage data shows this is happening.
```

### Tardy calculation (`DtrService::computeTardy`)

Compares Time In against the **nearest occurrence** of the employee's
`start_time` — checking the day before, the same day, and the day after — so
an overnight shift's evening start time still compares correctly (e.g.
`start_time = 22:00`, arrival `22:15` → 15 minutes, not compared against a
same-day-only 22:00 that would look like -23h45m early).

---

## Config — `config/dtr.php`

```php
'company' => ['name' => ..., 'address' => ..., 'logo' => ...],  // PDF header; env DTR_COMPANY_*
'required_minutes' => 600,
'tardy_threshold_minutes' => 60,
'max_shift_hours' => 16,
'min_shift_minutes' => 10,
'ambiguous_date_format' => 'dmy',  // see CsvPunchParser below; env DTR_CSV_DATE_FORMAT
```

---

## CSV import (`CsvPunchParser` + `PunchImportController`)

Required columns: **`Ac-No`** (employee code) and **`sTime`** (punch
timestamp) — same as the DOH project's export format. An optional **`Name`**
column seeds new rows into `employees` (existing employees are never
overwritten — only introduces employees the CSV mentions for the first time).

**Deliberately relaxed vs. the DOH project's parser**: that one _rejects_ a
file whose date format is ambiguous or mixed. This one never rejects a file
over date format — it makes its best guess and imports:

- All-ISO (`yyyy-mm-dd`) → used directly, unambiguous.
- All-slash with a value found `> 12` in one position → that position is the
  day, format decided (dd/mm/yyyy or mm/dd/yyyy).
- Mixed ISO + slash rows in the same file → both parsed (ISO parses correctly
  regardless of the file's overall detected format).
- Genuinely ambiguous (every day/month ≤ 12) or **conflicting** (some rows
  only make sense as dd/mm, others only as mm/dd) → falls back to
  `config('dtr.ambiguous_date_format')` instead of refusing the file.
- Also accepts a 12-hour clock with an AM/PM suffix (`01/08/2026 8:05 AM`).
- Still refused outright: a file where **no** row is a recognisable date at
  all (nothing to guess from).

Every file result in the JSON response carries `date_format_assumed: bool` so
a future UI can flag "we guessed the format" without blocking the upload.

Unchanged from the DOH project: `files` 1–7 per request, `.csv`/`.txt` only,
20MB max per file — the user asked to relax only the date-format restriction.

---

## Routes (`routes/dtr.php`, required from `routes/web.php`)

All under `['auth', 'verified', 'menu.permission']`:

```
GET|POST /employees[...]   EmployeeController (resource, except create/edit/show)
GET      /punches          PunchImportController@index   → Inertia 'punches/index'
POST     /punches          PunchImportController@store   → JSON (see below)
GET      /dtr              DtrController@index           → Inertia 'dtr/index'
GET      /dtr/show         DtrController@show            → JSON (one employee's computed DTR)
GET      /dtr/pdf          DtrController@pdf             → application/pdf
```

## JSON response shapes (for the frontend phase)

**`POST /punches`** (upload) — per-file result:

```jsonc
{
    "name": "punches.csv",
    "detected_format": "yyyy-mm-dd",
    "date_format_assumed": false,
    "imported": 120,
    "duplicates": 3,
    "skipped_blank": 0,
    "skipped_no_time": 0,
    "skipped_unparsable": 0,
    "skipped": 3,
    "first_punch": "2026-09-01 08:00:00",
    "last_punch": "2026-09-30 18:00:00",
}
// or, on a rejected file: { "name": ..., "error": "...", everything else 0/null }
```

Top-level: `{ message, files: [...], imported, skipped, totalPunches }`.

**`GET /dtr/show`** / one PDF page's source data — `DtrService::build()`:

```jsonc
{
  "employee": { "emp_code", "full_name", "position", "department", "start_time" } | null,
  "emp_code", "date_from", "date_to",
  "rows": [{
    "date", "day_label",              // "09/01/2026 Tue"
    "time_in", "time_out",            // "8:00" / "18:00" (24h, no leading zero) or null
    "out_next_day",                   // true when time_out is the following calendar day
    "work_hrs", "tardy", "undertime", "overtime",  // "H:MM" or "" when zero
    "remarks",                        // "*" when incomplete
    "is_incomplete"
  }],
  "total_work_hrs", "total_tardy_hrs", "total_ut_hrs", "total_ot_hrs",  // "H:MM"
  "incomplete_logs"                   // count
}
```

---

## Frontend (`resources/js/pages/{employees,punches,dtr}/index.tsx`, `dashboard.tsx`)

- `employees/index.tsx` — dense CRUD table (client-side search, no backend
  pagination — headcount is small). Flags active employees with no
  `start_time` (tardy can never be computed for them). Create/edit via a
  `useForm` + `Dialog`, matching `pages/admin/users.tsx`'s pattern.
- `punches/index.tsx` — drag-and-drop CSV upload, ported from the DOH
  project's `csv-upload.tsx` structure but posting through Inertia v3's
  `useHttp` (not raw `fetch`) since `PunchImportController::store` returns
  plain JSON, not an Inertia response. Surfaces `date_format_assumed` as a
  visible warning instead of hiding the guess.
- `dtr/index.tsx` — semi-monthly cut-off shortcuts (`resources/js/lib/cutoff.ts`,
  mirrored server-side by `App\Support\Cutoff` for the dashboard — keep both
  in sync if the pay-period rule ever changes), a staff checklist (empty
  selection = every active employee, matching `DtrController::pdf`'s
  default), and a live preview via `GET /dtr/show`. "Open PDF" / "Download
  PDF" build the `DtrController::pdf` URL directly (`inline=1` vs. not).
- `dashboard.tsx` — replaces the starter kit's placeholder. Backed by
  `DashboardController` (an invokable controller, no menu row — same as
  before), which surfaces the current cut-off, active/missing-start-time
  counts, last upload, and an `incompleteThisCutoff` figure computed by
  running `DtrService::build` once per active employee.
  `// ponytail:` marks that as fine at small-business headcount only.

Shared: `components/register-header.tsx` (the document-header masthead every
page opens on) and `components/status-seal.tsx` (the filled/dashed seal glyph
used instead of colored status pills) — both part of the shipped design
system, see `DESIGN.md`.

---

## Running & testing

```bash
composer install && npm install
php artisan migrate
php artisan db:seed                          # fresh DB — also seeds the Time Records menu
php artisan db:seed --class=DtrMenuSeeder    # or just the menu, on an existing DB
composer dev                                  # server + queue + vite together
php artisan test
vendor/bin/pint --dirty                       # style
vendor/bin/phpstan analyse                    # level 7, must be clean — no baseline
npm run types:check                           # tsc --noEmit
npm run check                                  # lint + format
php artisan app:create-admin                  # interactive Super Admin (production install)
php artisan app:backup                        # mysqldump + .env copy → config('dtr.backup.path')
```

Test files for this feature: `tests/Unit/{CsvPunchParserTest,CutoffTest}.php`,
`tests/Feature/{DtrServiceTest,PunchImportTest,DtrPdfTest,DashboardTest,CreateAdminCommandTest,BackupCommandTest}.php`.

### Production (on-premise, client self-managed)

The full rollout plan (hardened Laragon + MySQL at the shop, backups, handover)
lives outside the repo; the pieces in the repo are:

- `.env.production.example` — the production env template (`APP_TIMEZONE=Asia/Manila`,
  `APP_DEBUG=false`, MySQL app user, `BACKUP_PATH`, `MYSQLDUMP_PATH`).
- `scripts/build-release.ps1 -Version X.Y.Z` → `dist/aclear-vX.Y.Z.zip`: a
  `--no-dev` build from a git tag with tests, frontend sources, docs, and tooling
  stripped, plus `release-manifest.sha256`.
- `scripts/update.bat` (shipped at the zip root) — the client drags a new zip onto
  it: maintenance mode → `app:backup` → extract → `migrate --force` → `optimize`.
- `scripts/verify-release.ps1` (shipped at the zip root) — lists shipped code
  files edited since release (backs the license's "support void if modified").

After editing any generated controller's routes/params, regenerate wayfinder
with `php artisan wayfinder:generate --with-form` (matching `vite.config.ts`'s
`wayfinder({ formVariants: true })`) — plain `wayfinder:generate` without
`--with-form` strips the `.form` variants other pages rely on and breaks
`tsc --noEmit` across unrelated files.

---

## Known gotchas

- **Production caches config (`php artisan optimize`), so `env()` returns null
  outside `config/*.php`.** New settings go in a config file (see
  `config('dtr.backup')`), never `env()` in commands, controllers, or services.
- **`DatabaseSeeder` only creates the demo `admin@example.com` / `test@example.com`
  users when `APP_ENV=local`** — they need Faker (a dev dependency) and use the
  password `password`. Production creates its admin with `app:create-admin`.
- **Password reset is deliberately disabled** (`config/fortify.php`): the
  on-premise install has no mail server. The Super Admin resets passwords from
  Access Control → Users.
- **`APP_TIMEZONE` must be `Asia/Manila` in production** — `App\Support\Cutoff`
  decides the current pay period from `Carbon::today()`, which is wrong for the
  first 8 hours of the 1st/16th under UTC.

- **This app casts dates to `CarbonImmutable`** (`Date::use(CarbonImmutable::class)`
  in `AppServiceProvider`) — an Eloquent `datetime` cast gives you
  `CarbonImmutable`, but `Carbon::parse()` calls in your own code still give
  mutable `Carbon\Carbon`. Type-hint `Carbon\CarbonInterface` when a value
  might come from either source (see `DtrService`), not `Carbon\Carbon`.
- **Carbon's `->timestamp` magic property is loosely typed** (`float|int|string`
  in Larastan's stubs) and breaks PHPStan on arithmetic. Use `->getTimestamp()`
  (declared `int`) instead — see `DtrService::computeTardy`.
- **A route with no matching Menu row is unprotected**, not denied — see
  "Access control" above. Adding a controller action is not enough by itself.
- **`ambiguous_date_format` is a guess, not a detection** — if a client's
  export is ever ambiguous in bulk, the wrong assumption silently files a
  month's punches under swapped day/month. `date_format_assumed` in the
  upload response exists so a future UI can surface that risk to the user.
- **Shift pairing is a fixed 16h window**, not schedule-aware (there's no
  schedule to be aware of). See the `// ponytail:` comment on
  `DtrService::pairShifts` for the one case it gets wrong and the upgrade path.
- **PDF generation is synchronous** in `DtrController::pdf` — one request
  builds every employee's page in a loop. Fine at small-business headcount;
  move to a queued job (pattern: the DOH project's `GenerateBatchDtrV2Job`) if
  that ever changes.
- **The self-update mechanism has three constraints that look like bugs if you
  "fix" them.** (1) `UpdateController` must never apply the update itself — PHP
  cannot overwrite the files it is executing, so it stages the zip and hands off
  to a detached `run-update.ps1`. (2) Progress is polled from
  `public/update-status.php`, which deliberately does **not** boot Laravel: the
  app is in maintenance mode (503) and half-overwritten while the update runs.
  (3) Update state lives in `%ProgramData%\AClear\` (`App\Support\UpdatePaths`),
  **not** `storage/` — a release zip contains `storage/`, so the extract would
  delete the status file being polled. `UpdatePaths::stateDir()` is duplicated in
  `public/update-status.php` on purpose; change one, change the other.
- **A release zip (~40MB) is uploaded through the browser**, so `php.ini`'s
  `upload_max_filesize` and `post_max_size` gate it. PHP discards an over-limit
  upload silently — the request just arrives with no file — which is why the
  Updates page displays the server's limit and warns below 128M. README §3 sets
  both; the old 25M value would have broken the page.
- **Laragon can have several PHP versions installed side by side**, so anything
  that resolves a `php.exe` path must not take the first `C:\laragon\bin\php\php-*`
  match — enumeration is alphabetical, which picks 8.3 over 8.4. The installer
  takes the highest and makes the operator confirm it, because the highest
  installed version still isn't necessarily the one Apache serves. The app needs
  PHP >= 8.3 (`composer.json`, no platform pin).
- **`app:create-admin` is used unattended by the installer** (`installer/aclear.iss`)
  via `--name --email --password`. Keep those options working; without all three
  it falls back to prompting, which would hang a silent install.

- **The dashboard's `incompleteThisCutoff` runs one `DtrService::build` per
  active employee per page load** — see the `// ponytail:` comment on
  `DashboardController`. Fine at small-business headcount; revisit if
  headcount grows past a few dozen.
