# Command cheat-sheet (all PHP: `php bin/console …`; on Windows use `console.bat …` — see WINDOWS_XAMPP.md)

## First-time setup
```bash
php bin/console install --demo \
  --db-name=firecracker_shop --db-user=root --db-pass=secret \
  --admin-email=you@example.com --admin-password='ChangeMe12345'
php bin/console serve                      # http://127.0.0.1:8000  ·  admin: /admin
```
`install` = create `.env` (random APP_KEY) → create database → run migrations → seed → create admin. Safe to run again.
Manual route: `cp .env.example .env` → edit → `php bin/console key:generate` → `db:create` → `migrate` → `seed --demo` → `admin:create` → `serve`.

## Daily commands
| Command | Purpose |
|---|---|
| `doctor` | Check PHP version & extensions, .env, DB connection, migrations |
| `serve [--host=127.0.0.1] [--port=8000]` | Run the app locally |
| `migrate` | Apply new migrations (run after every update) |
| `migrate:status` | Which migrations are applied |
| `seed [--demo]` | Add default / demo data (never overwrites your edits) |
| `migrate:fresh [--seed] [--demo] [--force]` | ⚠ Drop ALL tables and rebuild (blocked when APP_ENV=production) |
| `admin:create [name] [email] [role] [--password=…]` | Create/reset an admin: staff · admin · superadmin |
| `env:init` / `key:generate` / `db:create` | Individual setup steps |
| `php tests/smoke_test.php --wipe` | Run the 130-check test suite (⚠ wipes the DB) |

## Production (live server)
```bash
cp .env.example .env     # APP_ENV=production, APP_DEBUG=false, real APP_URL, DB creds, APP_KEY (key:generate)
php bin/console migrate && php bin/console seed      # no --demo
php bin/console admin:create "Owner" you@example.com superadmin
```
Point the web server's document root to `public/`. After each release: `php bin/console migrate`.
