#!/usr/bin/env bash
# Run ON THE SERVER: switch `current` to the previous release (or the one given).
# Usage: bash ~/domains/aaochaletaxi.com/aaochaletaxi_app/current/deploy/rollback.sh [release_id]
set -euo pipefail
APP_ROOT="${APP_ROOT:-$HOME/domains/aaochaletaxi.com/aaochaletaxi_app}"
WEB_ROOT="${WEB_ROOT:-$HOME/domains/aaochaletaxi.com/public_html}"
CURRENT=$(basename "$(readlink -f "$APP_ROOT/current")")
TARGET="${1:-$(ls -1t "$APP_ROOT/releases" | grep -v "^$CURRENT$" | head -1)}"
[ -n "$TARGET" ] && [ -d "$APP_ROOT/releases/$TARGET" ] || { echo "No release to roll back to"; exit 1; }
rsync -a "$APP_ROOT/releases/$TARGET/public/build/" "$WEB_ROOT/build/"
ln -sfn "$APP_ROOT/releases/$TARGET" "$APP_ROOT/current.new" && mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
echo "Rolled back: $CURRENT -> $TARGET (database migrations are NOT reverted)"
