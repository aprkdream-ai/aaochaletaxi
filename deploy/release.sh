#!/usr/bin/env bash
# Manual release without GitHub Actions: builds origin/main in a clean checkout and deploys it.
# Usage: SSH_KEY=~/.ssh/aaochaletaxi_deploy bash deploy/release.sh
# Env: SSH_HOST/SSH_PORT/SSH_USER (defaults to production), BUILD_DIR (default: ~/.cache/aaochaletaxi-build)
set -euo pipefail

export SSH_HOST="${SSH_HOST:-145.79.212.47}" SSH_PORT="${SSH_PORT:-65002}" SSH_USER="${SSH_USER:-u856292259}"
: "${SSH_KEY:?Set SSH_KEY to the deploy private key}"
REPO="$(git -C "$(dirname "$0")/.." remote get-url origin)"
BUILD_DIR="${BUILD_DIR:-$HOME/.cache/aaochaletaxi-build}"

if [ ! -d "$BUILD_DIR/.git" ]; then
    git clone -q "$REPO" "$BUILD_DIR"
fi
cd "$BUILD_DIR"
git fetch -q origin
git reset -q --hard origin/main
git clean -fdq -e vendor -e node_modules
echo "==> Building $(git log --oneline -1)"

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist -q
npm ci --no-audit --no-fund --silent
npm run build --silent >/dev/null
if [ -n "$(git status --porcelain)" ]; then
    echo "Build changed tracked files (run npm run icons and commit):"; git status --short; exit 1
fi

bash deploy/deploy.sh
