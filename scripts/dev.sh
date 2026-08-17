#!/usr/bin/env bash
# One-shot local test run: env, infra, install, migrate, then boot API + admin.
set -e

cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
[ -f apps/api/.env ] || cp apps/api/.env.example apps/api/.env

echo "==> Starting postgres, redis, minio, mailpit"
docker compose up -d --wait postgres redis minio mailpit

# Nothing else provisions this bucket (no init container, no seeder) — a
# fresh `docker compose up` or `docker compose down -v` leaves media
# uploads storing successfully but then 500ing when the response tries to
# build the object's URL (Storage's `throw => false` swallows the actual
# S3 "no such bucket" failure). --ignore-existing keeps this safe to rerun.
# Public read matches MediaResource returning a plain (non-presigned) URL.
echo "==> Ensuring MinIO bucket exists"
docker compose exec -T minio sh -c '
  mc alias set local http://127.0.0.1:9000 "${MINIO_ROOT_USER:-obscurify}" "${MINIO_ROOT_PASSWORD:-obscurify_secret}" >/dev/null &&
  mc mb --ignore-existing local/obscurify &&
  mc anonymous set public local/obscurify >/dev/null
'

echo "==> Backend: composer install + migrate"
(cd apps/api && composer install --quiet)
grep -q '^APP_KEY=base64' apps/api/.env || (cd apps/api && php artisan key:generate)
(cd apps/api && php artisan migrate --force)

echo "==> Frontend: pnpm install"
npx --yes pnpm@latest install --silent

echo "==> Boot: API on :8000, admin on :3000 (Ctrl+C stops both)"
# Not "php artisan serve": its built-in dev server is a fresh `php -S`
# subprocess (Illuminate\Foundation\Console\ServeCommand::serverCommand())
# that does NOT inherit -d ini overrides from the outer artisan process, so
# it always falls back to the host PHP's own upload_max_filesize/
# post_max_size (2M/2M by default on a stock install, incl. Herd) —
# rejecting any media upload above that regardless of what the API code or
# infra/docker/api/uploads.ini (used only by `docker compose up`) allow.
# Passing -d directly to this invocation applies to the actual serving
# process and works the same on Herd/Valet/system PHP. Keep in step with
# apps/api/docker/php/uploads.ini and StoreMediaRequest::MAX_FILE_KB.
# Must run from public/ (not `-t public` from apps/api) — Laravel's own
# ServeCommand starts the subprocess with public/ as its cwd because
# vendor/.../resources/server.php resolves the app root from getcwd(),
# not from -t; running elsewhere makes it 500 on a missing index.php.
npx --yes concurrently \
  --names "api,admin" \
  --prefix-colors "cyan,magenta" \
  "cd apps/api/public && php -d upload_max_filesize=25M -d post_max_size=26M -d max_execution_time=60 -d max_input_time=60 -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php" \
  "npx --yes pnpm@latest --filter admin dev"
