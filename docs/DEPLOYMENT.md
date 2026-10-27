# Production deployment

1. **Server**: PHP 8.1+ with `pdo_mysql mbstring gd curl fileinfo openssl`, MySQL 8/MariaDB, HTTPS (Let's Encrypt).
2. **Upload** the project; point the web root to `public/` (recommended). On shared hosting that can't, the root `.htaccess` forwards to `public/`; `app/`, `storage/`, `database/` are also denied by `.htaccess`.
3. **.env**: copy `.env.example`; set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, a long random `APP_KEY` (`php -r "echo bin2hex(random_bytes(32));"`), DB credentials. Never commit `.env`. **Do not change APP_KEY later** — it decrypts the saved SMTP password.
4. **Database**: create a DB + a least-privilege user (SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES). Run `php bin/console db:setup` then `php bin/console admin:create`. For later releases run `php bin/console migrate`. Never run `migrate:fresh` on production.
5. **Permissions**: `public/uploads/` and `storage/` writable by the PHP user (`chmod -R 775`), nothing else writable.
6. **Nginx** example:
```
server { server_name shop.example.com; root /var/www/shop/public; index index.php;
  location / { try_files $uri /index.php?$query_string; }
  location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.3-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root/index.php; }
  location ^~ /uploads/ { location ~ \.php$ { return 403; } }
  add_header Permissions-Policy "microphone=(self)"; add_header X-Content-Type-Options nosniff; }
```
Apache: `AllowOverride All` + `mod_rewrite` (the bundled `.htaccess` does the rest).
7. **Tailwind**: pages use the Tailwind Play CDN for zero-build setup. For best performance build a static CSS (`npx tailwindcss -i in.css -o public/assets/css/tw.css --minify` with the config from `layouts/front.php` and content `app/Views/**/*.php`) and replace the CDN `<script>` in `app/Views/layouts/*.php`.
8. **Backups**: nightly `mysqldump` + copy of `public/uploads/`. **Cron** (optional): none required.
9. **Legal**: set licence info, restricted states (Admin → App Customization) and edit all policy pages. You are responsible for compliance with local firecracker laws.
10. **HTTPS**: the session cookie automatically becomes `Secure` over HTTPS (also behind a proxy sending `X-Forwarded-Proto: https`). Voice recognition requires HTTPS (or localhost).
