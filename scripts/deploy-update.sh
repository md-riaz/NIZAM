#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/opt/NIZAM"
LOCK_FILE="/var/lock/nizam-deploy-update.lock"
LOG_PREFIX="[nizam-deploy]"

log() {
  printf '%s %s %s\n' "$LOG_PREFIX" "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*"
}

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  log "another deploy is already running; exiting"
  exit 0
fi

cd "$APP_DIR"

log "starting update from $(git rev-parse --short HEAD)"

git fetch --prune origin main
LOCAL_HEAD="$(git rev-parse HEAD)"
REMOTE_HEAD="$(git rev-parse origin/main)"

if [ "$LOCAL_HEAD" != "$REMOTE_HEAD" ]; then
  log "fast-forwarding to origin/main"
  git merge --ff-only origin/main
else
  log "already at origin/main"
fi

log "installing frontend dependencies"
npm ci --prefix frontend

log "building frontend"
npm run build --prefix frontend

log "building containers"
docker compose build

log "starting containers"
docker compose up -d --remove-orphans

log "running backend migrations"
docker compose exec -T app php artisan migrate --force

log "clearing backend caches"
docker compose exec -T app php artisan optimize:clear

after="$(git rev-parse --short HEAD)"
log "deploy complete at $after"
