# Windows + XAMPP guide

**Why `'php' is not recognized`?** XAMPP does not add PHP to Windows PATH. Pick ONE of the fixes:

1. **Easiest – use the included launchers** (they find XAMPP's PHP by themselves):
   `console.bat migrate` · `console.bat doctor` · `start.bat` · `setup-windows.bat`
2. **Full path**: `D:\xampp\php\php.exe bin\console migrate`
3. **This window only**: `set PATH=D:\xampp\php;%PATH%` then `php bin/console migrate`
4. **Permanent**: Windows search → "Edit the system environment variables" → *Environment Variables* → *Path* → *New* → `D:\xampp\php` → OK → open a **new** cmd window.

Check: `php -v` must show **8.1 or newer** (XAMPP 8.1+). Always `cd /d "E:\fire crackers_php\fc"` first (quotes because of the space).

## A. Automatic setup (recommended)
1. XAMPP Control Panel → **Start MySQL** (Apache is not needed).
2. Double-click `setup-windows.bat` → answer the questions (XAMPP default: user `root`, empty password).
3. Double-click `start.bat` → open http://127.0.0.1:8000 · admin: http://127.0.0.1:8000/admin

Same thing by hand:
```
cd /d "E:\fire crackers_php\fc"
console.bat install --demo --db-user=root --db-pass= --db-name=firecracker_shop --admin-email=you@example.com --admin-password=ChangeMe12345
console.bat serve
```

## B. Import with mysql.exe (like your other projects)
```
D:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE firecracker_shop CHARACTER SET utf8mb4"
D:\xampp\mysql\bin\mysql.exe -u root firecracker_shop < database\schema.sql
D:\xampp\mysql\bin\mysql.exe -u root firecracker_shop < database\seed.sql
D:\xampp\mysql\bin\mysql.exe -u root firecracker_shop < database\demo_seed.sql     (optional demo products)
```
With a password: `-u USER -pPASSWORD`. These files are safe to import again. Then create `.env` (`console.bat env:init --db-name=firecracker_shop --db-user=root --db-pass=`), create the admin (`console.bat admin:create`) and run `console.bat serve`. Later updates: `console.bat migrate` (the import files already record which migrations are done).

## C. Migration commands
```
console.bat migrate            apply new migrations
console.bat migrate:status     show applied / pending
console.bat seed --demo        add default / demo data
console.bat migrate:fresh --demo   ⚠ wipes ALL tables, rebuilds
console.bat admin:create       create / reset an admin login
console.bat doctor             checks PHP version, extensions, .env, database, migrations
```

## D. If `doctor` shows [FAIL]
* **extension gd / mbstring / curl / fileinfo / pdo_mysql / openssl** → open `D:\xampp\php\php.ini`, delete the `;` at the start of that `extension=…` line, save, run again.
* **Database connection** → MySQL not started in XAMPP, or wrong `DB_*` in `.env`. Default XAMPP: `DB_USER=root`, `DB_PASS=` (empty), `DB_HOST=127.0.0.1`.
* **PHP older than 8.1** → install the current XAMPP (can live next to the old one in another folder).

## E. Running under XAMPP Apache instead of `start.bat` (optional)
Add to `D:\xampp\apache\conf\extra\httpd-vhosts.conf` (and enable `Include conf/extra/httpd-vhosts.conf` in httpd.conf), then restart Apache:
```
<VirtualHost *:80>
    ServerName crackers.local
    DocumentRoot "E:/fire crackers_php/fc/public"
    <Directory "E:/fire crackers_php/fc/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Add `127.0.0.1 crackers.local` to `C:\Windows\System32\drivers\etc\hosts` (as Administrator) and set `APP_URL=http://crackers.local` in `.env`. Tip: a folder name without spaces avoids quoting problems.
