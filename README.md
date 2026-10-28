# Firecrackers E-Commerce Platform

Core PHP 8 (simple MVC) · MySQL/MariaDB (PDO) · Tailwind CSS · vanilla JS · PHPMailer (bundled).
Light, festive, fully responsive storefront + secure Admin Panel. Guest checkout (no customer login/registration).

> **Windows / XAMPP?** Read `docs/WINDOWS_XAMPP.md` — double-click `setup-windows.bat`, then `start.bat`. (`'php' is not recognized` is fixed by the included `console.bat`.)

## Quick start (local, ~2 minutes)

Requirements: PHP 8.1+ (pdo_mysql, mbstring, gd, curl, fileinfo, openssl), MySQL 8 / MariaDB 10.4+ running.

```bash
php bin/console install --demo --db-user=root --db-pass=YOUR_DB_PASSWORD \
    --admin-email=you@example.com --admin-password='ChangeMe12345'
php bin/console serve
```
Shop: http://127.0.0.1:8000 · Admin: http://127.0.0.1:8000/admin. `install` creates `.env` (with a random `APP_KEY`), creates the database, migrates, seeds and creates your admin. Leave out `--demo` for a clean production database. Full list of commands: `docs/COMMANDS.md`.

## What's new in v2 (UI refresh)
* **Clean light storefront** — new design system, sticky glass header, mobile bottom navigation, search on `/products`, rebuilt home page, product cards and checkout. Fully responsive (mobile → desktop).
* **🎆 Fire-blast animation** (`public/assets/js/fireworks.js`) — ambient fireworks in the hero, a burst on every *Add to cart*, a finale on *Order placed* and after a successful Voice Order. Respects “reduce motion”.
* **Quick Order** — every active product in one searchable table (ID, name, Tamil name, price, stock, quantity, line total); add all selected rows in one tap (`POST /api/cart/add-many`). Bulk-paste of `ID qty` lines fills the table.
* **Voice Order** — rewritten for reliability (fresh recogniser per session, clear messages for blocked mic / no HTTPS / unsupported browser, editable transcript with *Fix & re-add*). **The microphone needs HTTPS** — set `FORCE_HTTPS=true` in `.env` once SSL is on.
* **Tailwind CSS** — compiled to `public/assets/css/tailwind.css` (no CDN needed). After adding new Tailwind classes run `npm install && npm run build:css`, or set `TAILWIND_CDN=true` in `.env` while developing.
* **Admin** — cleaner layout (mobile drawer, welcome/quick actions); every admin process was re-tested (products, categories, banners, services, orders, enquiries, content, media, settings, SMTP, users).
* **v2.1** — full-width storefront; `/products` has a filter sidebar (category, price, in-stock), per-page limit (12/24/48/96) and infinite scroll; unique product cards with a slide-over **cart drawer**; redesigned Quick Order (category tabs + floating total pill); firecracker-themed SVG art; soft ambient fireworks + corner cannons; Voice Order now matches partial names (“chakra”, “flower pot”) and tells you when it guessed.
* **Admin v2.1** — dark theme (toggle in the top bar, remembered), new **Reports** page (revenue by day, top products, categories, status, channel, locations, best customers, low stock, CSV export) and **Company Profile** page (GSTIN, licence, bank/UPI, invoice text — printed on invoices).
* **v2.2** — Admin → Company Profile now has *Order rules*: minimum order amount (default 3000 for new installs), delivery charge, free-delivery threshold. About/policy pages are full width. Voice Order has a *Test my microphone* level meter and a “mic open but no speech” hint. Fixed a fireworks animation bug (negative time step) that could stop the effect. Every page was checked in headless Chrome at 360/390/768/1024/1366/1920 px — no sideways overflow, no JS errors.
* Windows: use `console.bat migrate` (or `C:\xampp\php\php.exe bin/console migrate`) if `php` is not on PATH.
* Upgrading: run `php bin/console migrate` (adds `003_clean_light_theme`, which only changes the background colour if you never customised it).

## Excel product import / export (Admin → Products → *Excel import / export*)
Download the sheet (all products, or a blank template), edit in Excel, upload it back: matching products (by **ID**, else **SKU**) are **updated**, rows without a match are **added**. Empty cell = keep current value (`-` clears a text field). Validation per row; bad rows are skipped and listed with the Excel row number, good rows are saved. Needs PHP `zip` extension for `.xlsx` (`.csv` also accepted). Images/videos are still added on the product page. The Media Library and AI Image pages were removed (images are uploaded directly in each form).

## Migration & seed commands (`php bin/console …`)
| Command | What it does |
|---|---|
| `install [--demo] [--db-*] [--admin-*]` | One-step setup (.env + database + migrate + seed + admin) |
| `serve [--port=8000]` | Start the dev server |
| `migrate` | Apply pending `database/migrations/*.sql` (tracked in `schema_migrations`) |
| `migrate:status` | Show applied / pending migrations |
| `migrate:fresh [--seed] [--demo] [--force]` | **Drops all tables**, re-migrates, optionally seeds. Refuses when `APP_ENV=production` unless `--force` |
| `seed [--demo]` | Default settings, pages, services, banners (idempotent — never overwrites what you edited). `--demo` adds sample categories + products with Tamil names & voice aliases |
| `db:setup [--demo]` | `migrate` + `seed` — first-time setup |
| `admin:create [name] [email] [role] [--password=…]` | Create or reset an admin (staff / admin / superadmin) |

Adding a migration: create `database/migrations/003_your_change.sql` (one statement per `;` + newline, `--` comments allowed) and run `php bin/console migrate`. Adding seed data: drop a PHP file returning a function into `database/seeders/` (or `seeders/demo/`). **Upgrading an older copy of this project: run `php bin/console migrate`** (adds the Tamil product name column).

## Routes
Customer: `/`, `/about`, `/services`, `/products`, `/product-details.php?id=123`, `/quick-order`, `/voice-order`, `/cart`, `/checkout`, `/order-success`, `/track`, `/contact`, `/policy/{privacy|terms|shipping|refund|safety}`.
Primary nav = Home · About Us · Services · Products · Quick Order · Voice Order · Shopping Cart. Policies/Track/Contact are in the footer. No search-results, login or registration pages exist.
Admin: `/admin` (Dashboard, Orders, Products, Categories, Banners, Services, Website Content, Enquiries, App Customization, Email Settings, Admin Users).

## Architecture
```
public/            web root: index.php (front controller), assets/, uploads/
app/Core/          Env, DB (PDO), Router, View, Controller, Auth, RateLimiter
app/Models/        Product, Category, Page, Setting
app/Services/      CartService, OrderService, VoiceParser, MailService, MediaService, ProductSheet (Excel), Xlsx
app/Controllers/   public controllers + Admin/ controllers
app/Views/         layouts, partials, front/, admin/
database/          migrations/*.sql, seeders/*.php (+ seeders/demo/), and ready-to-import schema.sql / seed.sql / demo_seed.sql
bin/               console (migrate, seed, admin:create …)
docs/              deployment, SMTP, AI, testing, troubleshooting
```
Key design points
* **One shared cart** (session) used by listing, details, Quick Order and Voice Order. Prices/stock are always recomputed server-side from the DB.
* **One orders system**: `orders.order_source` = `cart | quick_order | voice_order`; one Admin → Orders page for all of them.
* **Checkout** runs in a transaction: `SELECT … FOR UPDATE` on products, re-validates price/stock, writes snapshot rows, decrements stock with `stock >= qty` guard, history row, random 50-bit order reference, and a unique idempotency token (session-bound) that blocks duplicate submissions.
* **Voice Order (one-shot full flow)**: tap once and say the *whole* order in one sentence (English or Tamil). Listening stops by itself after ~3 s of silence. The recognised *text* (never audio) goes to `/api/voice/parse`, which matches every product in the sentence against active DB products and adds them straight to the shared cart. A clear result panel shows each item as ✓ added / ⚠ reduced to available stock / ✗ out of stock, plus “not found” phrases with tap-to-add suggestions — in English or Tamil. **Undo this voice order** removes exactly what that command added. It never places an order. Typed + dropdown fallback is always available.
* **Theming**: colours live in `site_settings`, emitted as CSS variables in every page head; Tailwind is configured to read them.
* **Security**: PDO prepared statements, CSRF on every POST (incl. JSON API), output escaping, login rate limiting (5/15 min per email+IP), session regeneration, idle timeout, role-based access (staff/admin/superadmin), upload validation (finfo + getimagesize + GD re-encode, random names, PHP disabled in `uploads/`), secrets only in `.env`, SMTP password encrypted at rest.

## Payments
No online gateway is wired in (none was specified). Checkout offers configurable **pay on delivery** and **UPI/bank transfer after confirmation**; every order is stored as `payment pending` and is only marked paid when an admin records it. Card data is never handled.

## More docs
`docs/WINDOWS_XAMPP.md` · `docs/COMMANDS.md` · `docs/DEPLOYMENT.md` · `docs/SMTP_SETUP.md` · `docs/TESTING.md` · `docs/TROUBLESHOOTING.md`
