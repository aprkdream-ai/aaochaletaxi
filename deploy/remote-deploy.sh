#!/usr/bin/env bash
# Runs ON THE SERVER after deploy/deploy.sh uploaded a release.
# Release layout:  $APP_ROOT/{releases/<id>, shared/{.env,storage}, current -> releases/<id>}
set -euo pipefail

RELEASE="${1:?release id required}"
APP_ROOT="${APP_ROOT:-$HOME/domains/aaochaletaxi.com/aaochaletaxi_app}"
WEB_ROOT="${WEB_ROOT:-$HOME/domains/aaochaletaxi.com/public_html}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/domains/aaochaletaxi.com/deploy_backups}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP="${PHP_BIN:-php}"

R="$APP_ROOT/releases/$RELEASE"
S="$APP_ROOT/shared"
log() { echo "[deploy $(date +%H:%M:%S)] $*"; }

cd "$R"

# ---------- shared state ----------
mkdir -p "$S"/storage/{app/public,app/private,framework/cache/data,framework/sessions,framework/views,framework/testing,logs,fonts} "$BACKUP_DIR"
if [ ! -f "$S/.env" ]; then
    log "Creating shared .env from .env.production.example (edit DB/mail settings!)"
    cp "$R/.env.production.example" "$S/.env"
fi
chmod 600 "$S/.env"
rm -rf "$R/storage"
ln -sfn "$S/storage" "$R/storage"
ln -sfn "$S/.env" "$R/.env"
mkdir -p "$R/bootstrap/cache"

if ! grep -qE '^APP_KEY=base64:.+' "$S/.env"; then
    log "Generating APP_KEY"
    "$PHP" artisan key:generate --force --no-interaction
fi

# SQLite fallback support (only if configured)
if grep -qE '^DB_CONNECTION=sqlite' "$S/.env"; then
    DB_FILE=$(grep -E '^DB_DATABASE=' "$S/.env" | cut -d= -f2- | tr -d '"')
    [ -n "$DB_FILE" ] && [ ! -f "$DB_FILE" ] && touch "$DB_FILE"
fi

# ---------- database ----------
log "Running migrations"
"$PHP" artisan migrate --force --no-interaction
log "Seeding platform defaults (idempotent)"
"$PHP" artisan db:seed --class='Database\Seeders\PlatformSeeder' --force --no-interaction

# ---------- caches ----------
"$PHP" artisan optimize --no-interaction >/dev/null
log "Caches built"

# ---------- public assets (additive first; old files removed after switch) ----------
mkdir -p "$WEB_ROOT/build"
rsync -a "$R/public/build/" "$WEB_ROOT/build/"
for f in robots.txt favicon.svg site.webmanifest og-image.png apple-touch-icon.png icon-192.png icon-512.png; do
    [ -f "$R/public/$f" ] && cp -f "$R/public/$f" "$WEB_ROOT/$f"
done

# ---------- pre-switch smoke test ----------
APP_URL=$(grep -E '^APP_URL=' "$S/.env" | cut -d= -f2- | tr -d '"')
log "Smoke testing release before switch"
if ! "$PHP" "$R/deploy/smoke.php" "$R" "$WEB_ROOT" "$APP_URL"; then
    log "SMOKE TEST FAILED — release not activated. Live site unchanged."
    exit 1
fi

# ---------- front controller (+ one-time backup of anything we replace) ----------
STAMP=$(date +%Y%m%d%H%M%S)
if [ -f "$WEB_ROOT/index.php" ] && ! grep -q AAOCHALETAXI_FRONT_CONTROLLER "$WEB_ROOT/index.php"; then
    cp -p "$WEB_ROOT/index.php" "$BACKUP_DIR/index.php.$STAMP"; log "Backed up previous index.php"
fi
if [ -f "$WEB_ROOT/.htaccess" ] && ! grep -q AAOCHALETAXI_HTACCESS "$WEB_ROOT/.htaccess"; then
    cp -p "$WEB_ROOT/.htaccess" "$BACKUP_DIR/htaccess.$STAMP"; log "Backed up previous .htaccess"
fi
cp "$R/deploy/public_html/.htaccess" "$WEB_ROOT/.htaccess.new" && mv -f "$WEB_ROOT/.htaccess.new" "$WEB_ROOT/.htaccess"
cp "$R/deploy/public_html/index.php" "$WEB_ROOT/index.php.new" && mv -f "$WEB_ROOT/index.php.new" "$WEB_ROOT/index.php"

if [ ! -e "$WEB_ROOT/storage" ] || [ -L "$WEB_ROOT/storage" ]; then
    ln -sfn "$S/storage/app/public" "$WEB_ROOT/storage"
fi

# ---------- atomic switch ----------
PREV=""
[ -L "$APP_ROOT/current" ] && PREV=$(readlink -f "$APP_ROOT/current")
ln -sfn "$R" "$APP_ROOT/current.new" && mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
log "Switched current -> $RELEASE"

# ---------- live health check with automatic rollback ----------
sleep 2
CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "${APP_URL%/}/up" || echo 000)
if [ "$CODE" != "200" ]; then
    log "Live health check returned $CODE"
    if [ -n "$PREV" ] && [ -d "$PREV" ] && [ "$PREV" != "$R" ]; then
        ln -sfn "$PREV" "$APP_ROOT/current.new" && mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
        log "ROLLED BACK to $(basename "$PREV")"
    fi
    exit 1
fi
log "Live health check OK"

# ---------- cleanup ----------
rsync -a --delete "$R/public/build/" "$WEB_ROOT/build/"
"$PHP" artisan queue:restart --no-interaction >/dev/null 2>&1 || true
cd "$APP_ROOT/releases"
ls -1t | grep -v "^$RELEASE$" | tail -n +"$KEEP_RELEASES" | xargs -r rm -rf
log "Deploy of $RELEASE complete"
