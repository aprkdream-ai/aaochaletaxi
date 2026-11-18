#!/usr/bin/env bash
# Upload a release and activate it. Used by GitHub Actions and for manual deploys.
# Requires: production vendor (composer install --no-dev) and built assets (npm run build).
# Env: SSH_HOST, SSH_USER, [SSH_PORT=22], [SSH_KEY], [APP_DIR=domains/aaochaletaxi.com/aaochaletaxi_app]
set -euo pipefail

: "${SSH_HOST:?SSH_HOST required}"
: "${SSH_USER:?SSH_USER required}"
SSH_PORT="${SSH_PORT:-22}"
APP_DIR="${APP_DIR:-domains/aaochaletaxi.com/aaochaletaxi_app}"   # relative to remote $HOME

cd "$(dirname "$0")/.."

[ -f public/build/manifest.json ] || { echo "public/build missing — run: npm ci && npm run build"; exit 1; }
[ -f vendor/autoload.php ] || { echo "vendor missing — run: composer install --no-dev -o"; exit 1; }
if [ -d vendor/phpunit ] && [ "${ALLOW_DEV_VENDOR:-0}" != "1" ]; then
    echo "vendor contains dev packages — run: composer install --no-dev -o (or ALLOW_DEV_VENDOR=1)"; exit 1
fi

# Never ship a stale checkout: local HEAD must equal its upstream (CI runs on the pushed commit).
if [ "${CI:-}" != "true" ] && [ "${ALLOW_STALE:-0}" != "1" ] && git rev-parse '@{u}' >/dev/null 2>&1; then
    git fetch -q 2>/dev/null || true
    if [ "$(git rev-parse HEAD)" != "$(git rev-parse '@{u}')" ]; then
        echo "HEAD ($(git rev-parse --short HEAD)) differs from upstream ($(git rev-parse --short '@{u}')). Pull/push first or set ALLOW_STALE=1."; exit 1
    fi
fi

SSH_CMD="ssh -p $SSH_PORT -o BatchMode=yes -o StrictHostKeyChecking=accept-new ${SSH_KEY:+-i $SSH_KEY}"
REMOTE="$SSH_USER@$SSH_HOST"
REV="$(git rev-parse --short HEAD 2>/dev/null || echo manual)"
RELEASE="$(date -u +%Y%m%d%H%M%S)-$REV"

echo "==> Preparing release $RELEASE"
PREV=$($SSH_CMD "$REMOTE" "mkdir -p ~/$APP_DIR/releases/$RELEASE && if [ -L ~/$APP_DIR/current ]; then readlink -f ~/$APP_DIR/current; fi")

echo "==> Uploading files"
rsync -az --delete --exclude-from=deploy/rsync-exclude.txt ${PREV:+--link-dest="$PREV"} \
    -e "$SSH_CMD" ./ "$REMOTE:$APP_DIR/releases/$RELEASE/"

echo "==> Activating release"
$SSH_CMD "$REMOTE" "bash ~/$APP_DIR/releases/$RELEASE/deploy/remote-deploy.sh $RELEASE"
