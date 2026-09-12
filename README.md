# Deploying AClear to the shop PC

Assumes Laragon (Apache + PHp 8.4 + MySQL 8) is already installed on the
shop PC and MySQL is already set up. This covers everything from there:
building the release, moving it, first-run setup, backups, and updates.

## 0. What you need before you start

- The shop PC's MySQL `root` password (or ability to create one).
- A USB drive, or a link you can send yourself (Drive/WeTransfer/etc.) — there's
  no server-to-server pull step, the release zip just needs to physically get
  onto that PC.
- 30–45 minutes on site for the first install.

---

## 1. Build the release (on your dev machine)

```powershell
git tag v1.0.0
git push origin v1.0.0
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.0
```

This produces `dist\aclear-v1.0.0.zip` — a `--no-dev` build with the frontend
already compiled, no tests/source maps/dev tooling, and a
`release-manifest.sha256` file hashing every shipped PHP/Blade file (used
later by `verify-release.ps1` to detect if the client edited anything).

**Get the zip onto the shop PC.** Copy it to a USB drive, or upload it
somewhere and download it there. Nothing pulls from GitHub on the shop PC —
treat the zip like any other file you're handing over.

## 2. Create the database (shop PC, one time)

Open HeidiSQL (bundled with Laragon) or a MySQL shell as `root`:

```sql
CREATE DATABASE aclear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aclear'@'127.0.0.1' IDENTIFIED BY 'put-a-long-random-password-here';
GRANT ALL PRIVILEGES ON aclear.* TO 'aclear'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Never point `.env` at `root` — this app-only user is what the `.env` file
below uses.

## 3. Extract and configure

1. Extract the zip to `C:\laragon\www\aclear`.
2. In Laragon: **Menu → Apache → sites-enabled**, confirm the `aclear.test`
   vhost exists (Laragon auto-creates one per folder in `www`), then
   **Menu → Apache → SSL** to enable HTTPS for it. Passkeys and secure
   cookies require HTTPS.
3. Copy `.env.production.example` to `.env` and fill in:
   - `DB_PASSWORD` — the password you set in step 2.
   - `DTR_COMPANY_NAME`, `DTR_COMPANY_ADDRESS`, `DTR_COMPANY_LOGO` — the
     logo path is relative to `public/`, e.g. drop the file at
     `public/images/logo.png` and set `DTR_COMPANY_LOGO=images/logo.png`.
   - `BACKUP_PATH` — a folder inside a Google Drive/OneDrive sync folder on
     the owner's own account, e.g. `C:\Users\owner\Google Drive\AClear Backups`.
   - `MYSQLDUMP_PATH` — find it under Laragon's install, something like
     `C:\laragon\bin\mysql\mysql-8.4.x-winx64\bin\mysqldump.exe`.
4. Confirm `php.ini` allows real uploads (Laragon → Menu → PHP → php.ini):
   `upload_max_filesize=25M`, `post_max_size=150M`, `memory_limit=512M`,
   `max_execution_time=120`. Set `display_errors=Off`. Disable Xdebug if it's
   on — it slows every request down.

## 4. First-run commands

Open a terminal in `C:\laragon\www\aclear` (Laragon's "Terminal" menu item
sets up `php` on PATH automatically):

```powershell
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan app:create-admin
php artisan optimize
```

`app:create-admin` asks for a name, email, and password interactively and
creates the Super Admin account — this is the one account you set up for the
owner. There's no seeded demo login in a production build.

Open `https://aclear.test`, log in with the account you just created, and
have the owner **set their own password and enable 2FA** right there
(Settings → Security).

## 5. Keep it running without anyone logging in

Laragon only runs while someone is logged into Windows, so:

1. Create a dedicated Windows account, e.g. `aclear`.
2. Install [Sysinternals Autologon](https://learn.microsoft.com/sysinternals/downloads/autologon)
   and set that account to log in automatically on boot (it stores the
   password encrypted, unlike Windows' built-in `netplwiz`).
3. Laragon → **Preferences** → tick *Run Laragon when Windows starts* and
   *Start All automatically*.
4. Set Windows to lock the screen after 5 minutes idle (Settings → Accounts
   → Sign-in options) — the app keeps serving requests while the screen is
   locked, only auto-login is affected.

## 6. Backups (do this before you leave)

Open **Task Scheduler** → Create Task:

- **Trigger 1:** Daily, at closing time (e.g. 7:00 PM).
- **Trigger 2:** At log on.
- On the Settings tab, tick **"Run task as soon as possible after a
  scheduled start is missed"** — the PC may be off at 7 PM.
- **Action:** Start a program —
  - Program: `C:\laragon\bin\php\php-8.4.x\php.exe`
  - Arguments: `artisan app:backup`
  - Start in: `C:\laragon\www\aclear`

**Test it immediately:** right-click the task → Run, then check
`BACKUP_PATH` for a new timestamped folder containing `database.sql` and
`.env`. If `BACKUP_PATH` is a synced Drive/OneDrive folder, confirm it
actually uploads (check the Drive web UI, not just the local folder).

**Do a restore drill now, while you're still on site** — don't wait for a
real disaster to find out the backup doesn't work:

```sql
DROP DATABASE aclear;
CREATE DATABASE aclear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
```powershell
mysql -u aclear -p aclear < "path\to\backup\database.sql"
```

Reload the app and confirm the data is back.

## 7. Handing it to the client

- Give them the **owner guide** (separate doc) covering the daily workflow,
  not this file — this one's for you.
- Confirm they know: `update.bat` (shipped at the zip root) is how updates
  are installed — they double-click it and drop the new zip onto it when
  prompted, or drag the new zip file directly onto `update.bat`.
- Point out the monthly check: open `BACKUP_PATH` and confirm there's a
  recent folder in it.

---

## Shipping an update later

```powershell
git tag v1.0.1
git push origin v1.0.1
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.1
```

Send `dist\aclear-v1.0.1.zip` to the client (or bring it on a USB drive).
They drag it onto `update.bat`, which automatically: puts the app in
maintenance mode → runs `app:backup` → extracts the new files → runs
`migrate --force` → clears/rebuilds caches → brings the app back up. If
anything fails partway, it tells them to contact you rather than leaving the
site half-updated.

## Checking for tampering on a support visit

```powershell
powershell -ExecutionPolicy Bypass -File verify-release.ps1
```

Run from inside `C:\laragon\www\aclear` on the shop PC. It lists any shipped
file that doesn't match the hash recorded at release time — back on your
side, that's what the license agreement's "support void if modified" clause
is checked against.
