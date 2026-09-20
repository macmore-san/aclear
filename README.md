# Deploying AClear to the shop PC

For you, not the client — they get the separate owner guide.

Assumes Laragon (Apache + PHP 8.3 or 8.4 + MySQL 8) is already installed on the
shop PC. This covers building a release, installing it, backups, and shipping
updates afterwards.

The app requires PHP >= 8.3 (`composer.json`). Laragon can hold several PHP
versions side by side — **Menu → PHP → Version** is what Apache actually serves,
and it matters in a couple of places below.

## 0. What you need before you start

- The shop PC's MySQL `root` password (or the ability to set one).
- A USB drive, or a link you can send yourself (Drive/WeTransfer/etc.) — nothing
  pulls from GitHub on the shop PC, the release file just has to physically get
  onto it.
- About 20 minutes on site with the installer, or 30–45 doing it by hand.

---

## 1. Build the release (your dev machine, Windows)

```powershell
git tag v1.0.0
git push origin v1.0.0
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.0
```

That produces `dist\aclear-v1.0.0.zip`: a `--no-dev` build with the frontend
already compiled, no tests/source maps/dev tooling, plus two files that matter
later —

- `VERSION` — what the in-app Updates page compares against.
- `release-manifest.sha256` — a hash of every shipped PHP/Blade file, which
  `verify-release.ps1` checks on a support visit to see if anything was edited.

### Also build the installer, for a first install

```powershell
Expand-Archive dist\aclear-v1.0.0.zip -DestinationPath installer\payload
iscc /DAppVersion=1.0.0 installer\aclear.iss
```

That produces `dist\AClear-Setup-v1.0.0.exe`, which does §2A below.

> **The .exe is packaging, not code protection.** It bundles the same readable
> PHP source — anyone who extracts it has the code. `release-manifest.sha256`
> plus the license clause remain the only things covering modification. Don't
> sell it to the client as protection.

Both files are Windows-only to build: `build-release.ps1` is PowerShell, and
Inno Setup (`iscc`) needs Windows or Wine.

---

## 2A. Install with the installer (recommended)

Copy `AClear-Setup-v1.0.0.exe` to the shop PC and run it as administrator. It
refuses to start if Laragon isn't found, rather than half-installing.

It prompts for four things and then does the rest:

| Prompt                          | Used for                                                                                                                                                                                                             |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Path to `php.exe`               | Pre-filled with the highest version under `C:\laragon\bin\php\`. **Check it matches Menu → PHP → Version** — with 8.3 and 8.4 both installed the guess can be wrong, and the backup task is tied to this exact path. |
| MySQL `root` password           | Creating the `aclear` database and an app-only user. Used during install only, never stored.                                                                                                                         |
| Owner's name / email / password | The Super Admin account.                                                                                                                                                                                             |
| Backup folder                   | `BACKUP_PATH` — pick a folder inside the owner's Google Drive or OneDrive sync folder.                                                                                                                               |

It then creates the database and a dedicated `aclear` MySQL user with a
generated 32-character password, writes `.env` (filling `DB_PASSWORD`,
`BACKUP_PATH`, and auto-detecting `MYSQLDUMP_PATH` under Laragon), runs
`key:generate` / `migrate` / `db:seed` / `app:create-admin` / `optimize`, and
registers the nightly backup task.

**Then do §3** — the installer deliberately leaves those steps to you.

---

## 2B. Install by hand

Only if you're not using the installer. Extract `aclear-v1.0.0.zip` to
`C:\laragon\www\aclear`, then:

**Create the database** — HeidiSQL (bundled with Laragon) or a MySQL shell as
`root`:

```sql
CREATE DATABASE aclear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aclear'@'127.0.0.1' IDENTIFIED BY 'put-a-long-random-password-here';
GRANT ALL PRIVILEGES ON aclear.* TO 'aclear'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Never point `.env` at `root` — that app-only user is what `.env` uses.

**Configure** — copy `.env.production.example` to `.env` and fill in:

- `DB_PASSWORD` — the password you just set.
- `BACKUP_PATH` — a folder inside the owner's Drive/OneDrive sync folder, e.g.
  `C:\Users\owner\Google Drive\AClear Backups`.
- `MYSQLDUMP_PATH` — under Laragon's install, something like
  `C:\laragon\bin\mysql\mysql-8.4.x-winx64\bin\mysqldump.exe`.
- `DTR_COMPANY_NAME` / `DTR_COMPANY_ADDRESS` — the address prints on every DTR.
  `DTR_COMPANY_LOGO` already ships pointing at the AClear logo; only change it
  if the client supplies new artwork.

**First-run commands** — a terminal in `C:\laragon\www\aclear` (Laragon's
"Terminal" menu item puts `php` on PATH):

```powershell
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan app:create-admin
php artisan optimize
```

`app:create-admin` prompts for a name, email and password and creates the Super
Admin — there's no seeded demo login in a production build. (It also accepts
`--name --email --password` for unattended use; that's how the installer calls
it.)

---

## 3. Finish the setup (both paths)

### php.ini

Laragon → Menu → PHP → php.ini:

```ini
upload_max_filesize = 128M
post_max_size       = 128M
memory_limit        = 512M
max_execution_time  = 120
display_errors      = Off
```

**`upload_max_filesize` must stay well above the release size** (~40 MB and
growing). The Updates page uploads the release zip through the browser, and PHP
silently discards an over-limit upload — the request just arrives with no file.
The page shows the server's current limit and warns if it's under 128 MB, but
it's easier to set it right now. Disable Xdebug if it's on; it slows every
request down.

### HTTPS

Laragon → **Menu → Apache → sites-enabled**, confirm the `aclear.test` vhost
exists (Laragon auto-creates one per folder in `www`), then **Menu → Apache →
SSL**. Passkeys and secure cookies both require HTTPS.

Open `https://aclear.test`, log in as the account you created, and have the
owner **set their own password and enable 2FA** right there (Settings →
Security) while you're standing next to them.

### Keep it running with nobody logged in

Laragon only runs while a Windows user is logged in, so:

1. Create a dedicated Windows account, e.g. `aclear`.
2. Install [Sysinternals Autologon](https://learn.microsoft.com/sysinternals/downloads/autologon)
   and set that account to log in on boot (it encrypts the stored password,
   unlike Windows' built-in `netplwiz`).
3. Laragon → **Preferences** → tick _Run Laragon when Windows starts_ and
   _Start All automatically_.
4. Set Windows to lock the screen after 5 minutes idle — the app keeps serving
   while the screen is locked, only auto-login is affected.

### Verify the backup

The installer registers the task; if you installed by hand, create it in **Task
Scheduler**:

- **Trigger 1:** Daily at closing time (e.g. 7:00 PM).
- **Trigger 2:** At log on.
- Settings tab: tick **"Run task as soon as possible after a scheduled start is
  missed"** — the PC may well be off at 7 PM.
- **Action:** Start a program —
    - Program: `C:\laragon\bin\php\php-8.4.x\php.exe`
    - Arguments: `artisan app:backup`
    - Start in: `C:\laragon\www\aclear`

**Run it now**, either way: right-click → Run, then check `BACKUP_PATH` for a
new timestamped folder with `database.sql` and `.env` in it. If `BACKUP_PATH` is
a synced folder, confirm it actually reached the cloud — check the Drive web UI,
not just the local folder.

### Restore drill — do it before you leave

Don't find out the backup doesn't work during a real disaster:

```sql
DROP DATABASE aclear;
CREATE DATABASE aclear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```powershell
mysql -u aclear -p aclear < "path\to\backup\database.sql"
```

Reload the app and confirm the data is back.

---

## 4. Handing it over

- Give them the **owner guide**, not this file.
- Show them **System → Updates** — that's how they install what you send.
- Point out the monthly check: open `BACKUP_PATH`, confirm there's a recent
  folder.

---

## Shipping an update later

```powershell
git tag v1.0.1
git push origin v1.0.1
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.1
```

Send `dist\aclear-v1.0.1.zip` to the client. They install it themselves:
**System → Updates** → choose the file → **Install update**, and the page shows
progress while it runs. Tell them to do it at closing time — the app is offline
for a few minutes.

Both routes run the same sequence:

1. maintenance mode (`artisan down`)
2. database backup (`app:backup`)
3. rollback snapshot of the current install
4. extract the new files
5. `migrate --force`
6. rebuild caches, then `artisan up`

If a step fails it stops, brings the app back up, and names the database backup
and the rollback snapshot to recover from — rather than leaving a half-updated
site. Recovery is deliberately manual: nothing auto-restores.

### The fallback

`update.bat`, shipped at the install root — drag the new zip onto it. Use it
when the app won't boot far enough to reach the Updates page.

### Why the page can't just do the update itself

Worth knowing before changing any of this. PHP cannot overwrite the files it's
currently executing, and the app is in maintenance mode for the duration, so:

- The page only **validates and stages** the zip, then hands off to a detached
  `run-update.ps1` that outlives the request.
- Progress is polled from `public/update-status.php`, a standalone file that
  answers **without booting Laravel** — mid-extract the framework is
  half-replaced and can't serve a normal route, and `artisan down` would return
  503 anyway.
- The status file lives in `%ProgramData%\AClear\`, deliberately **outside** the
  app directory. A release zip contains `storage/`, so anything staged there
  gets wiped by the extract.

Rollback snapshots accumulate in `%ProgramData%\AClear\rollback\` at roughly
40 MB each — worth clearing out old ones on a support visit.

---

## Checking for tampering on a support visit

```powershell
powershell -ExecutionPolicy Bypass -File verify-release.ps1
```

Run from inside `C:\laragon\www\aclear`. It lists any shipped file that doesn't
match the hash recorded at release time — that's what the license agreement's
"support void if modified" clause gets checked against.

Note what this does and doesn't do: it detects edits to **this** install. It
does nothing about the code being copied elsewhere, since a copied folder runs
fine anywhere. Only bytecode encryption (ionCube/SourceGuardian) would address
that, and it's not currently in use.
