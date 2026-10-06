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
make serve                   # php artisan serve з лімітом завантаження 20 МБ
php artisan queue:work --queue=ingestion   # окремий термінал: обробка завантажених документів (воркер із типовим timeout або більшим)
npm install && npm run dev                 # ще один термінал: фронтенд
```

Застосунок відкривається на `http://localhost:8000`.

Ліміт завантаження 20 МБ (AC-1) потребує значень `upload_max_filesize=20M` і `post_max_size=21M` у php.ini; `make serve` передає їх сам, а Docker-образ у плані 3 пропише їх у конфігурації. Воркер черги має працювати з типовим timeout або вищим (job інжесту має `timeout` 150 с, `retry_after` 180 с).

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
php artisan test                  # потрібна лише MariaDB на порту 3307 (docker compose up -d mariadb)
npm run test                      # фронтенд (Vitest)
(cd services/embedder && python3 -m venv .venv && .venv/bin/pip install -r requirements.txt)  # один раз
(cd services/embedder && .venv/bin/python -m pytest -q)  # сайдкар ембедингів
```

Тести використовують array-кеш і sync-чергу, тому Redis їм не потрібен. Тести не ходять у мережу: зовнішні сервіси підміняються Fake-реалізаціями. Перший запуск pytest завантажує модель ембедингів, тож потрібна мережа.

Команда `make eval` (оцінка якості відповідей) з'явиться в плані 3.

## Тимчасовий безкоштовний провайдер (Groq)

Поки ключ Anthropic недоступний, можна працювати через безкоштовний Groq (див. [ADR 0007](docs/adr/0007-openai-compatible-llm-driver.md)). Фрагменти документів тоді передаються стороннім провайдеру. У `.env`:

```bash
RAG_LLM_DRIVER=openai_compatible
OPENAI_COMPAT_API_KEY=<ключ Groq>
OPENAI_COMPAT_MODEL_ANSWER=openai/gpt-oss-120b
OPENAI_COMPAT_MODEL_HELPER=openai/gpt-oss-20b
```

Перелік доступних моделей залежить від провайдера й акаунта (`GET /models`), тож за потреби змініть назви моделей. Повернення до Claude: `RAG_LLM_DRIVER=anthropic`.

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
