# Troubleshooting
* **Blank/500 page** – set `APP_DEBUG=true` temporarily, check `storage/logs/app-*.log`.
* **DB connection error** – verify `.env` DB_*; user needs privileges; `pdo_mysql` enabled.
* **Pages unstyled / icons missing** – the browser must reach cdn.tailwindcss.com, fonts.googleapis.com and unpkg.com (or build Tailwind locally, see DEPLOYMENT.md).
* **404 on every URL except `/`** – web root must be `public/` and rewrite enabled (Apache `AllowOverride All`, Nginx `try_files`).
* **Upload fails** – make `public/uploads/` writable; check `upload_max_filesize` / `post_max_size` ≥ 6M.
* **Microphone doesn't work** – needs HTTPS or localhost, a supported browser (Chrome/Edge/Safari), and mic permission. Otherwise use the typed fallback on the Voice Order page. Tamil needs the “தமிழ்” language selected and browser support for `ta-IN`.
* **Voice doesn't find a product** – add aliases (English + Tamil) to the product in Admin → Products.
* **"Session expired" messages** – cookies blocked or the page was open too long; reload.
* **Can't log in / locked out** – 5 failed attempts lock an email+IP for 15 minutes. Reset with `php bin/create-admin.php` (new user) or `UPDATE admins SET password_hash=...`; clear locks: `DELETE FROM rate_limits;`.
* **Emails not sent** – use Admin → Email Settings → Send test and read the error.
* **AI image error** – message is the provider's real error (key, billing, content policy, model access).
* **Voice cuts off too early / too late** – it stops after ~3 s of silence (`SILENCE_MS` in `public/assets/js/voice.js`); tap *Done* to finish immediately.
* **`Unknown column name_ta`** – run `php bin/console migrate`.
* **Wrong DB wiped?** `migrate:fresh` drops every table in the DB named in `.env`; it is blocked when `APP_ENV=production` unless `--force`.
* **Mic button disabled on a phone** – browsers only allow the microphone on HTTPS or `http://localhost`. Testing from a phone over `http://192.168…` shows a notice and the typed fallback; use HTTPS (e.g. a tunnel or a real certificate).
* **Android Chrome** – Voice Order uses single-utterance mode there (it stops when you pause) to avoid repeated words.
* **Wrong product picked by voice** – the product whose name/alias matches wins; give each product distinct aliases (Admin → Products → Voice aliases). The result panel always shows exactly which product was added, and *Undo this voice order* reverses it.
