# Testing
Automated end-to-end + security suite, **pure PHP** (no extra tools): `tests/smoke_test.php`.
```
php tests/smoke_test.php --wipe          # starts its own PHP server on 127.0.0.1:8099
php tests/smoke_test.php --wipe --base=http://127.0.0.1:8000   # or use a server you already started
php tests/smoke_test.php --wipe -v       # also print every PASS
```
**`--wipe` DROPS ALL TABLES** in the database from `.env`, re-migrates, seeds demo data and creates test admins (`owner@example.com` / `SuperSecret123`, `staff@example.com` / `StaffSecret123`). Use a throw-away database. It refuses to run if `APP_ENV=production`. Exit code is 0 only when every check passes (CI friendly).

## Manual checklist (do before go-live)
- [ ] Homepage shows DB banners/products; banner arrows, dots, swipe work on a phone
- [ ] Edit About/Services/policies in Admin → changes appear publicly
- [ ] Product listing, category filter, pagination, product details by ID
- [ ] Cart add/update/remove/clear; cart survives page navigation
- [ ] Quick Order (SKU lookup, bulk lines) fills the same cart
- [ ] Voice Order in Chrome/Edge: say a full multi-product order once (English and Tamil) → auto-stops after a pause → result panel is clear → Undo works; denied-mic and unsupported-browser fall back to typing
- [ ] Checkout validation (client + server), restricted state blocked, order created, stock decreased, double-click doesn't duplicate
- [ ] Order Success shows only the new order; `/track` needs reference + mobile
- [ ] Cart / Quick Order / Voice orders all appear in Admin → Orders with the right source; status change appears in tracking
- [ ] AI image: without key → clear message; with key → preview, save to library, not live until used in a banner
- [ ] App Customization colours change the whole public site
- [ ] SMTP test email succeeds; order email arrives
- [ ] Anonymous access to any `/admin/*` redirects to login; staff role can't open Products/Settings
- [ ] Layouts at 360px, 768px, 1280px, 1920px
## Result of the automated run in this build
162/162 checks passed (pages, cart API, voice English+Tamil incl. a 5-product one-sentence order, per-item stock/not-found messages and undo, order_source for cart/quick/voice, staff-role access control, 19 realistic voice utterances (number before/after, Tamil, Tanglish, spoken SKU, polite words), a full one-sentence voice → checkout → tracking → admin → cancel journey, checkout, duplicate protection, stock, tracking, admin RBAC, CSRF, SQLi/XSS probes, upload attacks, colour injection, banner scheduling, login rate-limit, honest AI/SMTP failures).
The browser-side Voice Order script (`public/assets/js/voice.js`) was also exercised in a simulated browser with a fake microphone: one-sentence order with auto-stop after silence, Stop button, Tamil, Android-style repeated transcripts (no double quantities), blocked mic, no-speech, unsupported browser, insecure http page, typed/dropdown fallback, suggestions, undo and double-tap safety (23/23).
**Not verified here:** real SMTP delivery, real OpenAI image generation (no network/keys in the build sandbox), live browser speech recognition, and visual layouts on real devices (no browser available).
