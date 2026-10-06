.PHONY: up down serve test seed demo eval
up:
	docker compose up -d --build
down:
	docker compose down
serve:
	php -d upload_max_filesize=20M -d post_max_size=21M artisan serve
test:
	php artisan test
	cd services/embedder && .venv/bin/python -m pytest -q
seed:
	php artisan migrate:fresh --seed
demo:
	php artisan db:seed --class=DemoSeeder
eval:
	php artisan rag:eval
