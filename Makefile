.PHONY: up down install test migrate fresh horizon admin storefront api-shell logs dev

dev:
	./scripts/dev.sh

up:
	docker compose up -d

down:
	docker compose down

install:
	cd apps/api && composer install
	pnpm install

migrate:
	cd apps/api && php artisan migrate

fresh:
	cd apps/api && php artisan migrate:fresh

test:
	cd apps/api && vendor/bin/pest
	pnpm -r --if-present run test

horizon:
	cd apps/api && php artisan horizon

admin:
	pnpm --filter admin dev

storefront:
	pnpm --filter storefront dev

api-shell:
	docker compose exec api sh

logs:
	docker compose logs -f
