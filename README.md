# Onboarding Mentor

Onboarding Mentor це RAG-ментор для нових співробітників: він відповідає на питання про компанію за внутрішніми документами та показує цитати з джерел. Проєкт зроблено як демонстрацію інженерного процесу з агентами для технічних лідів, яким цікаво побачити, як працюють специфікація, плани, рев'ю й тести разом. Дизайн описано в [специфікації](docs/superpowers/specs/2026-10-06-onboarding-mentor-design.md), а процес розробки в [звіті про розробку](docs/process/development-report.md).

## Швидкий старт

Потрібні PHP 8.4, Composer, Node.js та Docker.

```bash
make up                      # MariaDB, Redis, embedder
cp .env.example .env         # потім заповніть ANTHROPIC_API_KEY
composer install
php artisan key:generate
php artisan migrate --seed   # демо-користувачі та п'ять документів Vesna Tech
php artisan serve
php artisan queue:work --queue=ingestion   # окремий термінал: обробка завантажених документів
npm install && npm run dev                 # ще один термінал: фронтенд
```

Після `migrate --seed` документи ставляться в чергу інжесту. Доки `queue:work` не обробить їх, вони матимуть статус, відмінний від `ready`, а відповіді на питання з'являться лише після цього. Демо-дані можна дозавантажити окремо командою `make demo`.

## Демо-логіни

| Email | Пароль | Роль |
|---|---|---|
| `hr@vesna.test` | `password` | HR-адміністратор |
| `dev@vesna.test` | `password` | Розробник, відділ Engineering |
| `pm@vesna.test` | `password` | Менеджер, відділ Marketing |

Документ про code review доступний лише відділу Engineering, тож розробник і менеджер бачать різні відповіді.

## Тести

```bash
php artisan test                  # потрібні mariadb і redis: docker compose up -d mariadb redis
(cd services/embedder && pytest)  # сайдкар ембедингів
npm run test                      # фронтенд (Vitest)
```

Тести не ходять у мережу: зовнішні сервіси підміняються Fake-реалізаціями.

## Структура репозиторію

- `app/Knowledge`: документи, завантаження та ланцюжок інжесту.
- `app/Retrieval`: пошук по чанках із фільтром видимості.
- `app/Chat`: відповіді, SSE-стрім і цитати.
- `app/Insights`: аналітика прогалин у знаннях.
- `services/embedder`: Python-сайдкар для локальних ембедингів.
- `resources/js`: Vue 3 + Vuetify + Pinia SPA.
- `resources/prompts`: версіоновані промпти.
- `database/seeders/demo`: демо-документи вигаданої компанії Vesna Tech.
- `docs`: специфікація, плани, ADR та процес розробки.

## Документація

Уся документація в [`docs/`](docs/): [бриф](docs/00-brief.md), [архітектура](docs/02-architecture.md), [ADR](docs/adr), [процес](docs/process) і [журнали сесій](docs/process/sessions).
