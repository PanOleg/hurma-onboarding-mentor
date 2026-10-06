.PHONY: up down test seed demo eval
up:
	docker compose up -d --build
down:
	docker compose down
test:
	php artisan test
	cd services/embedder && pytest -q
seed:
	php artisan migrate:fresh --seed
demo:
	php artisan db:seed --class=DemoSeeder
eval:
	php artisan rag:eval
