# Deployment (Hostinger / cPanel / Plesk shared hosting)

## 1. Production layout (Hostinger, live)
```
/home/u856292259/domains/aaochaletaxi.com/
├── aaochaletaxi_app/
│   ├── releases/<utc-timestamp>-<git-sha>/   ← last 5 releases kept
│   ├── shared/.env                          ← production config (chmod 600)
│   ├── shared/storage/                      ← logs, sessions, uploads (persist across releases)
│   └── current -> releases/<id>             ← atomic symlink
├── public_html/                             ← web root, SHARED with other apps in sub-folders
│   ├── index.php, .htaccess                 ← managed by deploy (see deploy/public_html/)
│   ├── build/, robots.txt, favicon.svg
│   └── storage -> ../aaochaletaxi_app/shared/storage/app/public
└── deploy_backups/                          ← original index.php/.htaccess saved before first deploy
```
`.env`, `vendor/`, source and storage are never inside `public_html`.

Server facts (checked 2026-11-17): PHP 8.3 CLI + web, Composer 2.9, git, rsync, GNU coreutils,
`/usr/sbin/sendmail`, symlinks allowed, SSH on port 65002, no `crontab` binary (use hPanel cron).

## 2. CI/CD (GitHub Actions) — `.github/workflows/ci-cd.yml`
On every push / PR: Composer + npm build → Pint → migrate up/down/up on **MySQL 8** → full test suite.
On push to `main` (after tests pass): build production vendor (`--no-dev`) + assets, then
`deploy/deploy.sh` → rsync to a new release dir (hard-linked to previous for speed) →
`deploy/remote-deploy.sh` on the server:
1. link shared `.env` + `storage`, generate `APP_KEY` if missing
2. `migrate --force`, `db:seed PlatformSeeder` (idempotent), `optimize`
3. copy `public/build` (additive), robots, favicon to `public_html`
4. **pre-switch smoke test** (`deploy/smoke.php` boots the new release, requests `/up`, `/`, `/login`, …) — abort if any non-200; live site untouched
5. install front controller + `.htaccess` (backs up foreign originals once), storage symlink
6. atomic `current` symlink switch → live `GET /up` check → **auto-rollback** on failure
7. prune stale build files and old releases

### Required GitHub repository secrets
| Secret | Value |
|---|---|
| `SSH_HOST` | `145.79.212.47` |
| `SSH_PORT` | `65002` |
| `SSH_USER` | `u856292259` |
| `SSH_PRIVATE_KEY` | private key whose public key is in server `~/.ssh/authorized_keys` (dedicated CI key) |
| `SSH_KNOWN_HOSTS` | optional; output of `ssh-keyscan -p 65002 145.79.212.47` (pins host key) |

## 3. Manual deploy (from any machine with SSH access)
```bash
composer install --no-dev -o && npm ci && npm run build
SSH_HOST=145.79.212.47 SSH_PORT=65002 SSH_USER=u856292259 SSH_KEY=~/.ssh/<key> bash deploy/deploy.sh
composer install   # restore dev deps locally
```
Rollback (on server): `bash ~/domains/aaochaletaxi.com/aaochaletaxi_app/current/deploy/rollback.sh [release_id]`
(code only — migrations are not reverted; write backwards-compatible migrations).

## 4. First-time server setup checklist
1. hPanel → Databases → create MySQL DB + user. Put credentials in `aaochaletaxi_app/shared/.env`
   (`DB_CONNECTION=mysql`, `DB_HOST=localhost`). Then re-run deploy (or `php artisan migrate --force` in `current`).
2. Set `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` in shared `.env` before the first deploy (seeder creates the
   first super admin once). Change the password after first login.
3. hPanel → Advanced → **Cron Jobs** → every minute:
   `/usr/bin/php /home/u856292259/domains/aaochaletaxi.com/aaochaletaxi_app/current/artisan schedule:run >> /dev/null 2>&1`
   (drains the database queue, clears expired resets, prunes failed jobs).
4. SSL: hPanel → Security → SSL (already active; HTTP→HTTPS redirect handled by Hostinger). `SESSION_SECURE_COOKIE=true`.
5. Mail: `MAIL_MAILER=sendmail` works out of the box. For better deliverability create `noreply@aaochaletaxi.com`
   in hPanel → Emails and switch to SMTP (`smtp.hostinger.com:465`, `MAIL_SCHEME=smtps`).

## 5. Generic cPanel / Plesk (no SSH)
1. Locally: `composer install --no-dev -o && npm ci && npm run build`.
2. Zip the project **excluding** `.git`, `node_modules`, `tests`, `.env`. Upload via File Manager to
   `/home/<user>/aaochaletaxi_app/current` (outside `public_html`) and extract.
3. Copy `deploy/public_html/index.php` and `.htaccess` into `public_html`; edit `$appBase` in `index.php` to
   point to the app folder. Copy `public/build`, `robots.txt`, `favicon.svg` into `public_html`.
4. Create `.env` from `.env.production.example` in the app folder. Generate an APP_KEY locally with
   `php artisan key:generate --show` and paste it.
5. Database: create DB in cPanel/Plesk. Without SSH, import schema: locally run
   `php artisan migrate --pretend` against MySQL or `php artisan schema:dump`, then import via phpMyAdmin;
   seed by temporarily calling `php artisan db:seed --class=PlatformSeeder --force` through a cron job.
6. Storage link without symlinks: set `FILESYSTEM_DISK=local` and serve uploads through controllers (default design),
   or create the link via a one-off cron `ln -s ...`.
7. If the app *must* live inside `public_html`: keep it in a sub-folder protected by a `.htaccess` containing
   `Require all denied`, and keep only the front controller + assets at the web root.
8. Cron: as in §4.3 (cPanel → Cron Jobs; Plesk → Scheduled Tasks).

## 6. Backups
- Hostinger daily/weekly backups (hPanel → Files → Backups) cover files + DB.
- Additionally (recommended): hPanel cron daily `mysqldump --single-transaction -u USER -pPASS DB | gzip > ~/backups/db-$(date +\%F).sql.gz`
  and delete files older than 14 days. Back up `shared/.env` and `shared/storage/app` offsite monthly.
- Restore: import dump via phpMyAdmin or `gunzip < file | mysql -u USER -p DB`; restore `shared/storage/app`.

## 7. Troubleshooting
| Symptom | Fix |
|---|---|
| 500 error, blank page | `tail -50 shared/storage/logs/laravel-*.log`; check `.env` DB creds; run `php artisan optimize:clear` in `current` |
| CSS/JS missing | `public_html/build/manifest.json` must exist; redeploy |
| 419 Page expired | session table missing (migrate) or cookie domain mismatch (`SESSION_DOMAIN` empty) |
| Old code still served right after deploy | PHP realpath/opcache cache — clears within ~2 min |
| Deploy fails at smoke test | live site untouched; read the printed URL/status, fix, push again |
| Queue jobs not processed | hPanel cron missing or wrong PHP path |
| Sub-folder app broken | our `.htaccess` only rewrites non-existent paths; restore original from `deploy_backups/` if needed |
