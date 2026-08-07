#!/usr/bin/env bash
# One-shot local test run: env, infra, install, migrate, then boot API + admin.
set -e

cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
[ -f apps/api/.env ] || cp apps/api/.env.example apps/api/.env

echo "==> Starting postgres, redis, minio, mailpit"
docker compose up -d postgres redis minio mailpit

echo "==> Backend: composer install + migrate"
(cd apps/api && composer install --quiet)
grep -q '^APP_KEY=base64' apps/api/.env || (cd apps/api && php artisan key:generate)
(cd apps/api && php artisan migrate --force)

echo "==> Frontend: pnpm install"
npx --yes pnpm@latest install --silent

echo "==> Boot: API on :8000, admin on :3000 (Ctrl+C stops both)"
npx --yes concurrently \
  --names "api,admin" \
  --prefix-colors "cyan,magenta" \
  "cd apps/api && php artisan serve" \
  "npx --yes pnpm@latest --filter admin dev"
