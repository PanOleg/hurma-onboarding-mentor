# Онбординг-наставник. План 1 з 3: документи процесу й вертикальний зріз

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Робочий вертикальний зріз: HR завантажує Markdown або PDF, документ проходить інжест у MariaDB Vector, співробітник у чаті отримує стрімінгову відповідь із цитатами, а також усі документи процесу (brief, architecture, ADR, CLAUDE.md, skills), які показуються на дзвінку 7.10.

**Architecture:** Laravel 13 володіє RAG: модулі `Knowledge`, `Retrieval`, `Chat` в `app/`, ланцюжок job-ів для інжесту, один репозиторій пошуку з raw SQL до MariaDB Vector, SSE-стрімінг через `StreamedResponse`. Python-сайдкар FastAPI робить лише `/embed` (fastembed, multilingual-e5-small) і `/extract-text` (pymupdf, python-docx). Усі зовнішні залежності за інтерфейсами з фейками, тести не ходять у мережу.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 5, Sanctum 4, `anthropic-ai/sdk` ^0.54, MariaDB 11.8 (Docker), Redis 7 (Docker), Python 3.12 + FastAPI + fastembed 0.8 + pymupdf + python-docx, Vue 3 + Vuetify 3 + Pinia + Vite, vitest.

**Spec:** `docs/superpowers/specs/2026-10-06-onboarding-mentor-design.md`

**Поділ на плани.** Спека покриває три віхи, кожна дає робочий продукт:
- План 1 (цей): дні 1–7. Документи процесу, скаффолд, Compose для mariadb/redis/embedder, сайдкар, міграції, інжест Markdown і PDF, пошук, стрімінгова відповідь із цитатами, мінімальний чат. Закриває AC-1, AC-3, AC-6, AC-7, AC-8. `app` і `worker` у цьому плані запускаються локально (`php artisan serve`, `php artisan queue:work`), їхні Docker-образи й Horizon у плані 3.
- План 2: дні 8–12. Аудиторії в UI, адмінка документів зі статусами й retry, історія розмов із переформулюванням, прогалини, `/stats`, видалення, rate limit. AC-2, AC-4, AC-5 (повний UI; scope `visibleTo` і його негативний тест уже в плані 1), AC-10, AC-12.
- План 3: дні 13–21. Evals і калібрування порогу (AC-9), Docker-образи app/worker, Horizon, CI, runbook (AC-11), сторінка-кейс, GIF, PDF.

## Global Constraints

- PHP `^8.3` (локально 8.4.13), Laravel `^13.0`, Pest `^5.0`, Sanctum `^4.0`, `anthropic-ai/sdk` `^0.54`.
- MariaDB образ `mariadb:11.8`, колонка `embedding VECTOR(384) NOT NULL`, індекс `VECTOR INDEX (embedding) M=8 DISTANCE=cosine`.
- Модель ембедингів `intfloat/multilingual-e5-small`, 384 виміри, префікси `query: ` і `passage: ` додає сайдкар.
- Моделі LLM: відповідь `claude-sonnet-5-5`, допоміжні кроки `claude-haiku-4-5`, усе через `config/rag.php`.
- Параметри пошуку в `config/rag.php`: `top_k` 8, `candidate_limit` 100, `max_distance` 0.35.
- Чанкування: ціль 400 токенів, перекриття 60, токен = `ceil(mb_strlen / 4)`.
- Raw SQL лише у міграції `document_chunks` і в `ChunkSearchRepository`. Будь-який доступ до чанків через `Document::visibilityWhere`.
- Без секретів і реальних персональних даних у коді, фікстурах, промптах, журналах. `.env` у `.gitignore`, `.env.example` з порожніми значеннями.
- Мова: документація, UI, повідомлення користувачу українською; код, назви, коміти англійською.
- Промпти лише у `resources/prompts/*.vN.md`, зміна промпту = нова версія + snapshot-тест.
- Кожна задача закінчується зеленими тестами й комітом. Тести не роблять мережевих викликів.

## Review Focus

Що спека передбачає, але жоден тест не перевіряє напряму. Для кожного пункту тест додано в задачу-власника.

1. **PDF без текстового шару** (скан). Очікування: документ `failed` з кодом `no_text_layer`, а не `ready` з нулем чанків. Тест у Task 8 (job `ExtractDocumentText` з екстрактором, що повертає порожні сторінки).
2. **Markdown із CRLF і BOM** (файл з Windows). Очікування: чанкер бачить заголовки й абзаци так само, як з LF. Тест у Task 7.
3. **Відповідь LLM із маркером поза діапазоном**, наприклад `[9]` при 8 фрагментах. Очікування: маркер відкинутий, цитата не створена, відповідь збережена. Тест у Task 12 і Task 13.
4. **Порожнє або надто довге повідомлення** (0 або 2001+ символів). Очікування: 422, повідомлення не створюються, LLM не викликається. Тест у Task 13.
5. **Сайдкар повертає вектор не того розміру** (наприклад 768). Очікування: `HttpEmbeddingProvider` кидає виняток із зрозумілим кодом, job іде в retry, а не падає на SQL-помилці. Тест у Task 6.

---

## Структура файлів плану 1

```
CLAUDE.md
.claude/skills/{laravel-feature,queued-job,vue-screen,prompt-change,adr}/SKILL.md
docs/00-brief.md  docs/02-architecture.md  docs/adr/0001..0006-*.md  docs/process/workflow.md
docs/process/sessions/2026-10-06-plan-1.md

docker-compose.yml                       mariadb, redis, embedder
services/embedder/{app.py,requirements.txt,Dockerfile,tests/test_api.py}

config/rag.php
app/Models/User.php                      role, department_id, job_role
app/Knowledge/Enums/{DocumentStatus,AudienceType,IngestionStep,FailureCode}.php
app/Knowledge/Models/{Department,Document,DocumentChunk,IngestionRun}.php
app/Knowledge/Contracts/{EmbeddingProvider,TextExtractor}.php
app/Knowledge/Embedding/{HttpEmbeddingProvider,FakeEmbeddingProvider}.php
app/Knowledge/Extraction/{HttpTextExtractor,LocalTextExtractor,CompositeTextExtractor,FakeTextExtractor,ExtractedPage}.php
app/Knowledge/Chunking/{Chunker,Chunk}.php
app/Knowledge/Ingestion/{DocumentIngestionService,ChunkWriter}.php
app/Knowledge/Jobs/{ExtractDocumentText,ChunkDocument,EmbedDocumentChunks}.php
app/Knowledge/Http/{DocumentController,StoreDocumentRequest,DocumentResource}.php
app/Knowledge/Policies/DocumentPolicy.php
app/Retrieval/{ChunkSearchRepository,SearchHit,QueryEmbeddingCache}.php
app/Chat/Enums/MessageStatus.php
app/Chat/Models/{Conversation,Message,MessageCitation}.php
app/Chat/Contracts/LlmClient.php
app/Chat/Llm/{AnthropicLlmClient,FakeLlmClient,AnswerResult,GroundingResult,PromptLoader}.php
app/Chat/{CitationParser,AnswerService,SseWriter}.php
app/Chat/Http/{ConversationController,MessageController,StoreMessageRequest}.php
app/Insights/Models/KnowledgeGap.php
app/Insights/{QuestionNormalizer,KnowledgeGapRecorder}.php
app/Http/Controllers/AuthController.php
app/Providers/RagServiceProvider.php
database/migrations/*                    9 таблиць
database/seeders/{DemoSeeder.php, demo/*.md}
resources/prompts/{answer.v1.md,rewrite.v1.md,grounding.v1.md}
resources/js/{app.js,router.js,api.js,sse.js,stores/chat.js,stores/auth.js,views/{LoginView,ChatView}.vue,components/{MessageBubble,CitationPanel}.vue}
tests/Unit/..., tests/Feature/..., tests/Fixtures/...
```

---

### Task 1: Документи процесу, CLAUDE.md, skills ✅ (c1e9a18, рев'ю прийнято)

**Files:**
- Create: `CLAUDE.md`, `.claude/skills/laravel-feature/SKILL.md`, `.claude/skills/queued-job/SKILL.md`, `.claude/skills/vue-screen/SKILL.md`, `.claude/skills/prompt-change/SKILL.md`, `.claude/skills/adr/SKILL.md`
- Create: `docs/00-brief.md`, `docs/02-architecture.md`, `docs/adr/0001-laravel-owns-rag.md` … `docs/adr/0006-grounding-after-answer.md`, `docs/process/workflow.md`, `docs/process/sessions/2026-10-06-plan-1.md`

**Interfaces:**
- Produces: правила для агента, якими керуються всі наступні задачі. Назви skills використовуються в журналі сесій.

- [x] **Step 1: CLAUDE.md**

```markdown
# Onboarding Mentor (Hurma showcase)

Spec: docs/superpowers/specs/2026-10-06-onboarding-mentor-design.md. Plan: docs/superpowers/plans/.
Every task references an AC-id from the spec. Read the spec section before coding.

## Stack
PHP 8.4, Laravel 13, Pest 5, MariaDB 11.8 (Docker, port 3307 for tests), Redis, Vue 3 + Vuetify + Pinia, Python sidecar in services/embedder.

## Commands
- `php artisan test` (needs `docker compose up -d mariadb redis`), single test: `php artisan test --filter=Name`
- `vendor/bin/pint`, `vendor/bin/phpstan analyse`
- `npm run test`, `npm run build`
- Sidecar: `cd services/embedder && pytest`

## Hard rules
- No secrets, no real personal data anywhere (code, fixtures, prompts, docs/process/sessions). Use the fictional company "Vesna Tech".
- Raw SQL only in the document_chunks migration and app/Retrieval/ChunkSearchRepository.php.
- Any read of document_chunks goes through Document::visibilityWhere(). Never bypass.
- External services only via contracts (EmbeddingProvider, TextExtractor, LlmClient). Tests bind the Fake implementations. No network in tests.
- Prompts live in resources/prompts/<name>.vN.md. Changing a prompt = new version file + snapshot test update + `php artisan rag:eval` + note in docs/04-evals.md. Use skill prompt-change.
- Every PR adds or changes a test. Write the failing test first.
- Modules: app/Knowledge, app/Retrieval, app/Chat, app/Insights. New code goes into the module it belongs to, not app/Http or app/Services.
- Ukrainian for docs, UI copy and user-facing messages; English for code, identifiers, commits.
- Commit messages: `type(scope): summary (AC-n)`.

## Skills
.claude/skills/laravel-feature, queued-job, vue-screen, prompt-change, adr. Use the matching skill for the task type.
```

- [x] **Step 2: Skills (п'ять файлів, кожен один екран)**

`.claude/skills/laravel-feature/SKILL.md`:
```markdown
---
name: laravel-feature
description: Add one API resource (migration, model, policy, form request, resource, controller, feature test) from a spec section.
---
Input: spec section number and AC-ids.
1. Read the spec section and the data model row for the table.
2. Write the Feature test first in tests/Feature/<Module>/<Resource>Test.php using Pest. Bind fakes: `$this->app->bind(EmbeddingProvider::class, FakeEmbeddingProvider::class)` is done in tests/TestCase.php; do not call real services.
3. Create migration, model (in app/<Module>/Models), policy, form request, resource, controller (in app/<Module>/Http). Register route in routes/api.php under the v1 group.
4. Run `php artisan test --filter=<Resource>Test`. Then `vendor/bin/pint --dirty` and `vendor/bin/phpstan analyse`.
5. Commit `feat(<module>): <summary> (AC-n)`.
Never: raw SQL outside the two allowed files; reading chunks without Document::visibilityWhere.
```

`.claude/skills/queued-job/SKILL.md`:
```markdown
---
name: queued-job
description: Add a queued job for the ingestion chain with idempotency, retries and ingestion_runs logging, plus tests for the success and failed paths.
---
1. Job class in app/Knowledge/Jobs, implements ShouldQueue, `public int $tries = 3; public array $backoff = [10, 30, 90];`, queue `ingestion`.
2. handle(): reload the Document, return early if deleted_at or status is already past this step; set the step status; open an IngestionRun (status running); do the work; close the run (done); advance status.
3. failed(Throwable $e): close the run with failed + error, set document status failed with a FailureCode.
4. Tests in tests/Feature/Knowledge/Jobs/<Job>Test.php: success path asserts status and run row; failed path binds a fake that throws, calls `$job->failed($e)` and asserts failure_code.
5. `php artisan test --filter=<Job>Test`, pint, commit `feat(knowledge): <job> (AC-n)`.
```

`.claude/skills/vue-screen/SKILL.md`:
```markdown
---
name: vue-screen
description: Add one Vue 3 + Vuetify screen with a Pinia store and API client calls matching spec section 8.
---
1. Read the endpoint rows in spec section 8 for this screen. Copy the exact paths and payloads.
2. Store in resources/js/stores/<name>.js (Pinia setup store). Write the vitest first in resources/js/stores/<name>.test.js, mocking resources/js/api.js with vi.mock.
3. View in resources/js/views/<Name>View.vue using Vuetify components only (no custom CSS frameworks). Register the route in resources/js/router.js.
4. UI copy in Ukrainian. Errors show the `error.message` from the API envelope.
5. `npm run test`, `npm run build`, commit `feat(web): <screen> (AC-n)`.
```

`.claude/skills/prompt-change/SKILL.md`:
```markdown
---
name: prompt-change
description: Change an LLM prompt safely: new versioned file, snapshot test, eval run, evals log entry.
---
1. Copy resources/prompts/<name>.vN.md to <name>.v(N+1).md and edit the new file only.
2. Point config/rag.php `prompts.<name>` to the new version.
3. Update tests/Unit/Chat/PromptSnapshotTest.php: run `php artisan test --filter=PromptSnapshotTest`, review the diff, accept with `--update-snapshots` only after reading it.
4. Run `php artisan rag:eval` (real providers, costs money, ask before running) and append a row to docs/04-evals.md: date, prompt versions, threshold, models, recall@5, citation_validity, grounded_rate, no_answer_precision.
5. Commit `prompt(<name>): vN+1 <why>`.
```

`.claude/skills/adr/SKILL.md`:
```markdown
---
name: adr
description: Record an architecture decision in docs/adr with context, decision, alternatives and consequences.
---
File docs/adr/NNNN-<slug>.md, NNNN = next number. Sections, Ukrainian:
# NNNN. <Назва>
Дата, Статус (запропоновано | прийнято | замінено на NNNN).
## Контекст — 3–6 речень, яка проблема і які обмеження.
## Рішення — що вирішили, одним абзацем.
## Альтернативи — таблиця: варіант | плюси | мінуси | чому відхилено.
## Наслідки — що стає простіше, що складніше, що треба моніторити.
Link the ADR from docs/02-architecture.md table. Commit `docs(adr): NNNN <slug>`.
```

- [x] **Step 3: docs/00-brief.md (продакт)**

Зміст: розділи 1–3 спеки, переписані для нетехнічного читача, плюс метрики успіху продукту: частка питань з відповіддю ≥ 80 %, час до відповіді < 5 с, кількість закритих прогалин на місяць. Підпис: «Роль: продакт».

- [x] **Step 4: docs/02-architecture.md (архітектор)**

Скопіювати розділи 5–7 спеки (діаграми Mermaid включно), додати таблицю ADR з посиланнями на файли. Підпис: «Роль: архітектор».

- [x] **Step 5: Шість ADR за шаблоном skill adr**

Назви й суть з таблиці 5.2 спеки: 0001 Laravel володіє RAG; 0002 MariaDB Vector як сховище (у наслідках: індекс лише ORDER BY + LIMIT, тому підзапит кандидатів); 0003 локальні ембединги через fastembed (альтернативи Voyage, OpenAI; наслідок: драйвер для заміни); 0004 REST API + SPA з Sanctum cookie; 0005 SSE через StreamedResponse; 0006 grounding після відповіді.

- [x] **Step 6: docs/process/workflow.md і перший журнал сесії**

`workflow.md`: чотири кроки циклу з розділу 16 спеки, список skills, правило запису в журнал: один рядок на виправлення у форматі `| задача | що зробив агент не так | яке правило додано |`.

`docs/process/sessions/2026-10-06-plan-1.md`: заголовок, таблиця з трьома колонками, порожня, і рядок «Старт плану 1».

- [x] **Step 7: Коміт**

```bash
git add CLAUDE.md .claude docs
git commit -m "docs(process): brief, architecture, ADR 0001-0006, CLAUDE.md, agent skills"
```

---

### Task 2: Скаффолд Laravel 13 + Pest 5 + конфіг rag ✅ (1d92d97 + b004aae, рев'ю прийнято після 1 раунду)

**Files:**
- Create: Laravel-скелет у корені, `config/rag.php`, `.env.example` (доповнити), `.env.testing`, `tests/TestCase.php` (доповнити), `phpstan.neon`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `config('rag.*')` ключі: `top_k`, `candidate_limit`, `max_distance`, `chunk.target_tokens`, `chunk.overlap_tokens`, `embedder.url`, `embedder.timeout_embed`, `embedder.timeout_extract`, `embedding_dim`, `models.answer`, `models.helper`, `prompts.answer`, `prompts.rewrite`, `prompts.grounding`, `anthropic.api_key`.

- [x] **Step 1: Створити скелет у порожній тимчасовій папці й перенести в корінь**

```bash
cd /Users/ok/PhpstormProjects/HurmaSystem
composer create-project laravel/laravel:^13.0 tmp-skeleton --no-interaction --prefer-dist
rsync -a --exclude .git tmp-skeleton/ ./
rm -rf tmp-skeleton
cat >> .gitignore <<'EOF'
.idea/
/storage/app/knowledge/
/services/embedder/.venv/
/services/embedder/__pycache__/
EOF
php artisan key:generate
php artisan install:api --no-interaction
composer require laravel/sanctum:^4.0 anthropic-ai/sdk:^0.54
composer require --dev pestphp/pest:^5.0 pestphp/pest-plugin-laravel:^5.0 larastan/larastan:^3.0 --with-all-dependencies
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
vendor/bin/pest --init
```

Очікування: `php artisan --version` показує `Laravel Framework 13.x`, `vendor/bin/pest --version` показує 5.x.

- [x] **Step 2: config/rag.php**

```php
<?php

return [
    'top_k' => (int) env('RAG_TOP_K', 8),
    'candidate_limit' => (int) env('RAG_CANDIDATE_LIMIT', 100),
    'max_distance' => (float) env('RAG_MAX_DISTANCE', 0.35),
    'embedding_dim' => 384,
    'chunk' => [
        'target_tokens' => 400,
        'overlap_tokens' => 60,
        'max_tokens' => 600,
    ],
    'embedder' => [
        'url' => env('EMBEDDER_URL', 'http://127.0.0.1:8100'),
        'timeout_embed' => 30,
        'timeout_extract' => 120,
        'batch_size' => 32,
    ],
    'models' => [
        'answer' => env('RAG_MODEL_ANSWER', 'claude-sonnet-5-5'),
        'helper' => env('RAG_MODEL_HELPER', 'claude-haiku-4-5'),
    ],
    'prompts' => [
        'answer' => 'answer.v1',
        'rewrite' => 'rewrite.v1',
        'grounding' => 'grounding.v1',
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
    'upload' => [
        'max_bytes' => 20 * 1024 * 1024,
        'mimes' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/markdown', 'text/plain'],
    ],
];
```

- [x] **Step 3: .env.example і .env.testing**

Додати в `.env.example` (значення порожні або локальні):
```
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mentor
DB_USERNAME=mentor
DB_PASSWORD=
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:5173
SESSION_DOMAIN=localhost
EMBEDDER_URL=http://127.0.0.1:8100
ANTHROPIC_API_KEY=
RAG_MODEL_ANSWER=claude-sonnet-5-5
RAG_MODEL_HELPER=claude-haiku-4-5
```

`.env.testing` (комітиться, без секретів). `SANCTUM_STATEFUL_DOMAINS=localhost` потрібен, щоб тести логіну з заголовком `Referer: http://localhost` отримували сесію:
```
APP_ENV=testing
APP_URL=http://localhost
SANCTUM_STATEFUL_DOMAINS=localhost
APP_KEY=base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleTEyMzQ=
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=mentor_test
DB_USERNAME=mentor
DB_PASSWORD=mentor
QUEUE_CONNECTION=sync
CACHE_STORE=array
SESSION_DRIVER=array
MAIL_MAILER=array
EMBEDDER_URL=http://embedder.invalid
ANTHROPIC_API_KEY=
```

Скопіювати `.env.example` у `.env` локально й заповнити `DB_PASSWORD=mentor`, `DB_PORT=3307` (порт із Compose у Task 3).

- [x] **Step 4: tests/TestCase.php з прив'язкою фейків**

Фейки з'являться в Task 6 і Task 11, тому тут лише каркас, який розширимо:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindFakes();
    }

    protected function bindFakes(): void
    {
        // Task 6 and Task 11 add bindings here.
    }
}
```

У `tests/Pest.php` переконатися, що `pest()->extend(Tests\TestCase::class)->use(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');`.

- [x] **Step 5: phpstan.neon**

```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    paths: [app, config, database, routes]
    level: 6
```

- [x] **Step 6: Smoke-тест конфігу**

`tests/Unit/ConfigTest.php`:
```php
<?php

it('exposes rag config defaults', function () {
    expect(config('rag.top_k'))->toBe(8)
        ->and(config('rag.candidate_limit'))->toBe(100)
        ->and(config('rag.max_distance'))->toBe(0.35)
        ->and(config('rag.embedding_dim'))->toBe(384)
        ->and(config('rag.models.answer'))->toBe('claude-sonnet-5-5')
        ->and(config('rag.models.helper'))->toBe('claude-haiku-4-5');
});
```

Run: `php artisan test --filter=ConfigTest`. Expected: PASS (Unit-тести не потребують бази).

- [x] **Step 7: Коміт**

```bash
git add -A
git commit -m "chore: scaffold Laravel 13 with Pest 5, Sanctum, Anthropic SDK, rag config"
```

---

### Task 3: Docker Compose (mariadb, redis, embedder) і Python-сайдкар ✅ (09105d8, рев'ю прийнято)

**Files:**
- Create: `docker-compose.yml`, `services/embedder/app.py`, `services/embedder/requirements.txt`, `services/embedder/Dockerfile`, `services/embedder/tests/test_api.py`, `services/embedder/pytest.ini`, `Makefile`

**Interfaces:**
- Produces: HTTP-контракт сайдкара з розділу 9 спеки: `POST /embed {texts, kind}` → `{vectors, model, dim}`; `POST /extract-text` multipart → `{pages:[{page,text}], meta:{pages_count,title}}`; `GET /health`. MariaDB на `127.0.0.1:3307`, Redis на `6379`, embedder на `8100`.

- [x] **Step 1: docker-compose.yml**

```yaml
services:
  mariadb:
    image: mariadb:11.8
    environment:
      MARIADB_ROOT_PASSWORD: root
      MARIADB_DATABASE: mentor
      MARIADB_USER: mentor
      MARIADB_PASSWORD: mentor
    ports: ["3307:3306"]
    volumes:
      - mariadb-data:/var/lib/mysql
      - ./docker/mariadb/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 20

  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

  embedder:
    build: ./services/embedder
    ports: ["8100:8100"]
    healthcheck:
      test: ["CMD", "python", "-c", "import urllib.request,sys; sys.exit(0 if urllib.request.urlopen('http://127.0.0.1:8100/health').status == 200 else 1)"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 40s

volumes:
  mariadb-data:
```

`docker/mariadb/init.sql` створює тестову базу й дає права:
```sql
CREATE DATABASE IF NOT EXISTS mentor_test;
GRANT ALL PRIVILEGES ON mentor_test.* TO 'mentor'@'%';
FLUSH PRIVILEGES;
```

- [x] **Step 2: Makefile**

```makefile
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
```

- [x] **Step 3: Тести сайдкара (падають, бо app.py ще немає)**

`services/embedder/requirements.txt`:
```
fastapi==0.115.*
uvicorn[standard]==0.30.*
python-multipart==0.0.*
fastembed==0.8.*
pymupdf==1.24.*
python-docx==1.1.*
pytest==8.*
httpx==0.27.*
```

`services/embedder/pytest.ini`:
```ini
[pytest]
testpaths = tests
```

`services/embedder/tests/test_api.py`:
```python
import io
import math

import fitz  # pymupdf
from docx import Document as DocxDocument
from fastapi.testclient import TestClient

from app import app

client = TestClient(app)


def test_health_reports_model_and_dim():
    r = client.get("/health")
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "ok"
    assert body["dim"] == 384
    assert body["model"] == "intfloat/multilingual-e5-small"


def test_embed_returns_normalized_384_vectors():
    r = client.post("/embed", json={"texts": ["Політика відпусток", "Vacation policy"], "kind": "passage"})
    assert r.status_code == 200
    body = r.json()
    assert body["dim"] == 384
    assert len(body["vectors"]) == 2
    for v in body["vectors"]:
        assert len(v) == 384
        assert abs(math.sqrt(sum(x * x for x in v)) - 1.0) < 1e-3


def test_query_and_passage_embeddings_differ_for_same_text():
    q = client.post("/embed", json={"texts": ["відпустка"], "kind": "query"}).json()["vectors"][0]
    p = client.post("/embed", json={"texts": ["відпустка"], "kind": "passage"}).json()["vectors"][0]
    assert q != p


def test_embed_rejects_empty_list_and_bad_kind():
    assert client.post("/embed", json={"texts": [], "kind": "query"}).status_code == 422
    assert client.post("/embed", json={"texts": ["a"], "kind": "other"}).status_code == 422


def _pdf_bytes(pages_text):
    doc = fitz.open()
    for text in pages_text:
        page = doc.new_page()
        page.insert_text((72, 72), text)
    return doc.tobytes()


def test_extract_text_from_pdf_returns_pages():
    pdf = _pdf_bytes(["Перша сторінка про відпустки", "Second page about onboarding"])
    r = client.post("/extract-text", files={"file": ("doc.pdf", pdf, "application/pdf")})
    assert r.status_code == 200
    body = r.json()
    assert body["meta"]["pages_count"] == 2
    assert "відпустки" in body["pages"][0]["text"]
    assert body["pages"][1]["page"] == 2


def test_extract_text_from_docx_returns_single_page():
    d = DocxDocument()
    d.add_paragraph("Онбординг")
    d.add_paragraph("Перший день")
    buf = io.BytesIO()
    d.save(buf)
    r = client.post(
        "/extract-text",
        files={"file": ("doc.docx", buf.getvalue(), "application/vnd.openxmlformats-officedocument.wordprocessingml.document")},
    )
    assert r.status_code == 200
    body = r.json()
    assert body["meta"]["pages_count"] == 1
    assert body["pages"][0]["text"] == "Онбординг\n\nПерший день"


def test_extract_text_rejects_png():
    r = client.post("/extract-text", files={"file": ("x.png", b"\x89PNG\r\n", "image/png")})
    assert r.status_code == 422
```

- [x] **Step 4: Запустити тести, переконатися, що падають**

```bash
cd services/embedder && python3 -m venv .venv && . .venv/bin/activate && pip install -r requirements.txt && pytest -q
```
Expected: FAIL з `ModuleNotFoundError: No module named 'app'`.

- [x] **Step 5: services/embedder/app.py**

```python
import io
from typing import Literal

import fitz
from docx import Document as DocxDocument
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastembed import TextEmbedding
from fastembed.common.model_description import ModelSource, PoolingType
from pydantic import BaseModel, Field

MODEL_NAME = "intfloat/multilingual-e5-small"
DIM = 384
MAX_TEXTS = 64
MAX_TEXT_CHARS = 8000
MAX_FILE_BYTES = 20 * 1024 * 1024
PDF_MIME = "application/pdf"
DOCX_MIME = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"

TextEmbedding.add_custom_model(
    model=MODEL_NAME,
    pooling=PoolingType.MEAN,
    normalization=True,
    sources=ModelSource(hf=MODEL_NAME),
    dim=DIM,
    model_file="onnx/model.onnx",
)
_model = TextEmbedding(model_name=MODEL_NAME)

app = FastAPI(title="embedder", version="1.0")


class EmbedRequest(BaseModel):
    texts: list[str] = Field(min_length=1, max_length=MAX_TEXTS)
    kind: Literal["query", "passage"]


class EmbedResponse(BaseModel):
    vectors: list[list[float]]
    model: str
    dim: int


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "dim": DIM}


@app.post("/embed", response_model=EmbedResponse)
def embed(req: EmbedRequest):
    for t in req.texts:
        if len(t) > MAX_TEXT_CHARS:
            raise HTTPException(status_code=422, detail=f"text longer than {MAX_TEXT_CHARS} chars")
    prefixed = [f"{req.kind}: {t}" for t in req.texts]
    vectors = [v.tolist() for v in _model.embed(prefixed)]
    return EmbedResponse(vectors=vectors, model=MODEL_NAME, dim=DIM)


@app.post("/extract-text")
async def extract_text(file: UploadFile = File(...)):
    data = await file.read()
    if len(data) > MAX_FILE_BYTES:
        raise HTTPException(status_code=413, detail="file too large")
    name = (file.filename or "").lower()
    if file.content_type == PDF_MIME or name.endswith(".pdf"):
        return _extract_pdf(data)
    if file.content_type == DOCX_MIME or name.endswith(".docx"):
        return _extract_docx(data)
    raise HTTPException(status_code=422, detail="unsupported file type, expected pdf or docx")


def _extract_pdf(data: bytes):
    try:
        doc = fitz.open(stream=data, filetype="pdf")
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=422, detail=f"cannot open pdf: {exc}") from exc
    pages = [{"page": i + 1, "text": page.get_text("text").strip()} for i, page in enumerate(doc)]
    title = (doc.metadata or {}).get("title") or None
    return {"pages": pages, "meta": {"pages_count": len(pages), "title": title}}


def _extract_docx(data: bytes):
    try:
        d = DocxDocument(io.BytesIO(data))
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=422, detail=f"cannot open docx: {exc}") from exc
    paragraphs = [p.text.strip() for p in d.paragraphs if p.text.strip()]
    text = "\n\n".join(paragraphs)
    return {"pages": [{"page": 1, "text": text}], "meta": {"pages_count": 1, "title": d.core_properties.title or None}}
```

- [x] **Step 6: Запустити тести**

Run: `cd services/embedder && pytest -q`. Expected: 7 passed (перший запуск завантажує модель з Hugging Face, це 1–2 хвилини).

- [x] **Step 7: Dockerfile з вшитою моделлю**

```dockerfile
FROM python:3.12-slim
WORKDIR /srv
ENV PIP_NO_CACHE_DIR=1 FASTEMBED_CACHE_PATH=/srv/models
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY app.py .
# Download the model at build time so the container starts without network.
RUN python -c "import app"
EXPOSE 8100
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8100"]
```

Run: `docker compose up -d --build && sleep 60 && curl -s localhost:8100/health`. Expected: `{"status":"ok","model":"intfloat/multilingual-e5-small","dim":384}`. Перевірка MariaDB: `docker compose exec mariadb mariadb -umentor -pmentor -e "SELECT VEC_DISTANCE_COSINE(VEC_FromText('[1,0]'), VEC_FromText('[0,1]'))"`. Expected: 1.

- [x] **Step 8: Коміт**

```bash
git add docker-compose.yml docker Makefile services/embedder
git commit -m "feat(embedder): fastapi sidecar with /embed and /extract-text, compose for mariadb/redis"
```

---

### Task 4: Міграції, enums, моделі, фабрики ✅ (b47f7d0, рев'ю прийнято)

**Files:**
- Create: 9 міграцій у `database/migrations/`, `app/Knowledge/Enums/{DocumentStatus,AudienceType,IngestionStep,FailureCode}.php`, `app/Chat/Enums/MessageStatus.php`, `app/Knowledge/Models/{Department,Document,DocumentChunk,IngestionRun}.php`, `app/Chat/Models/{Conversation,Message,MessageCitation}.php`, `app/Insights/Models/KnowledgeGap.php`, фабрики `database/factories/{DepartmentFactory,DocumentFactory,ConversationFactory}.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, міграція users
- Test: `tests/Feature/Knowledge/SchemaTest.php`

**Interfaces:**
- Produces: моделі з розділу 6 спеки. Ключові сигнатури: `Document::visibilityWhere(Builder|QueryBuilder $q, User $user, string $table = 'documents'): void`, `Document::scopeVisibleTo(Builder $q, User $user)`, `Document::scopeReady(Builder $q)`, `User::isHrAdmin(): bool`. Enum `DocumentStatus` зі значеннями `uploaded, extracting, chunking, embedding, ready, failed`. Enum `FailureCode`: `no_text_layer, extractor_unavailable, embedder_unavailable, unsupported_format, unknown`.

- [x] **Step 1: Enums**

`app/Knowledge/Enums/DocumentStatus.php`:
```php
<?php

namespace App\Knowledge\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Extracting = 'extracting';
    case Chunking = 'chunking';
    case Embedding = 'embedding';
    case Ready = 'ready';
    case Failed = 'failed';

    /** Order in the pipeline; used by jobs for idempotency checks. */
    public function order(): int
    {
        return match ($this) {
            self::Uploaded => 0, self::Extracting => 1, self::Chunking => 2,
            self::Embedding => 3, self::Ready => 4, self::Failed => 99,
        };
    }
}
```

`AudienceType`: cases `All = 'all'`, `Department = 'department'`, `Role = 'role'`. `IngestionStep`: `Extract = 'extract'`, `Chunk = 'chunk'`, `Embed = 'embed'`. `FailureCode`: `NoTextLayer = 'no_text_layer'`, `ExtractorUnavailable = 'extractor_unavailable'`, `EmbedderUnavailable = 'embedder_unavailable'`, `UnsupportedFormat = 'unsupported_format'`, `Unknown = 'unknown'`. `App\Chat\Enums\MessageStatus`: `Streaming = 'streaming'`, `Completed = 'completed'`, `Failed = 'failed'`, `NoAnswer = 'no_answer'`.

- [x] **Step 2: Міграції**

Доповнити міграцію users (`0001_01_01_000000_create_users_table.php`) полями після `password`:
```php
$table->string('role', 20)->default('employee');
$table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
$table->string('job_role', 100)->nullable();
```
Для цього таблиця `departments` має створюватись раніше: нова міграція `0001_01_01_000000_create_departments_table.php` (та сама мітка часу, але ім'я `create_departments` сортується перед `create_users`, перевірити `php artisan migrate --pretend`), з `id, name string unique, timestamps`.

`2026_10_06_000010_create_documents_table.php`:
```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('original_filename');
    $table->string('mime', 120);
    $table->unsignedBigInteger('size_bytes');
    $table->char('sha256', 64)->unique();
    $table->string('storage_path');
    $table->string('audience_type', 20);
    $table->string('audience_value', 100)->nullable();
    $table->string('status', 20)->default('uploaded');
    $table->string('failure_code', 40)->nullable();
    $table->text('failure_message')->nullable();
    $table->unsignedSmallInteger('version')->default(1);
    $table->unsignedInteger('chunks_count')->default(0);
    $table->foreignId('uploaded_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    $table->index(['status', 'deleted_at']);
});
```

`2026_10_06_000020_create_document_chunks_table.php` (єдина міграція з raw DDL):
```php
public function up(): void
{
    DB::statement(<<<'SQL'
        CREATE TABLE document_chunks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            position SMALLINT UNSIGNED NOT NULL,
            page SMALLINT UNSIGNED NULL,
            heading VARCHAR(255) NULL,
            content TEXT NOT NULL,
            token_count SMALLINT UNSIGNED NOT NULL,
            embedding VECTOR(384) NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            VECTOR INDEX (embedding) M=8 DISTANCE=cosine,
            INDEX idx_document_position (document_id, position),
            CONSTRAINT fk_chunks_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);
}

public function down(): void
{
    Schema::dropIfExists('document_chunks');
}
```

`2026_10_06_000030_create_ingestion_runs_table.php`: `id, document_id FK cascade, step string(10), status string(10), attempt tinyInteger default 1, started_at timestamp, finished_at nullable, error text nullable, timestamps`.

`2026_10_06_000040_create_conversations_table.php`: `id, user_id FK cascade, title nullable, last_message_at timestamp nullable, timestamps`.

`2026_10_06_000050_create_messages_table.php`: `id, conversation_id FK cascade, role string(10), content longText, status string(12), rewritten_question text nullable, grounded boolean nullable, model string nullable, input_tokens unsignedInteger nullable, output_tokens unsignedInteger nullable, latency_ms unsignedInteger nullable, best_distance decimal(6,4) nullable, timestamps`.

`2026_10_06_000060_create_message_citations_table.php`: `id, message_id FK cascade, chunk_id unsignedBigInteger nullable` + `$table->foreign('chunk_id')->references('id')->on('document_chunks')->nullOnDelete();`, `marker tinyInteger, quote text, timestamps`.

`2026_10_06_000070_create_knowledge_gaps_table.php`: `id, question_normalized string unique, question_example text, occurrences unsignedInteger default 1, status string(15) default 'open', resolved_document_id FK nullable nullOnDelete, last_asked_at timestamp, timestamps`.

- [x] **Step 3: Моделі**

`app/Knowledge/Models/Document.php`:
```php
<?php

namespace App\Knowledge\Models;

use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'audience_type' => AudienceType::class,
            'failure_code' => FailureCode::class,
        ];
    }

    protected static function newFactory()
    {
        return \Database\Factories\DocumentFactory::new();
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function ingestionRuns(): HasMany
    {
        return $this->hasMany(IngestionRun::class)->orderBy('id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Ready->value);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        self::visibilityWhere($query, $user, $query->getModel()->getTable());

        return $query;
    }

    /**
     * The single place that encodes audience rules. Works for Eloquent and raw query builders,
     * so ChunkSearchRepository reuses it instead of re-implementing the rules.
     */
    public static function visibilityWhere(Builder|QueryBuilder $query, User $user, string $table = 'documents'): void
    {
        $query->where(function ($q) use ($user, $table) {
            $q->where("$table.audience_type", AudienceType::All->value)
                ->orWhere(function ($q2) use ($user, $table) {
                    $q2->where("$table.audience_type", AudienceType::Department->value)
                        ->where("$table.audience_value", (string) $user->department_id);
                })
                ->orWhere(function ($q3) use ($user, $table) {
                    $q3->where("$table.audience_type", AudienceType::Role->value)
                        ->where("$table.audience_value", (string) $user->job_role);
                });
        });
    }

    public function markFailed(FailureCode $code, string $message): void
    {
        $this->forceFill([
            'status' => DocumentStatus::Failed,
            'failure_code' => $code,
            'failure_message' => mb_substr($message, 0, 2000),
        ])->save();
    }
}
```

Увага до `audience_value` для department: зберігаємо `department_id` як рядок, тому порівняння з `(string) $user->department_id`. Якщо `department_id` або `job_role` у користувача `null`, умова порівнює з `''` і не збігається, це бажана поведінка.

`DocumentChunk`: `$guarded = []`, `$casts` немає (embedding ніколи не читається через Eloquent, тільки через репозиторій), relation `document()`. Додати `protected $hidden = ['embedding'];` щоб вектор не потік у JSON.

`IngestionRun`: `$guarded = []`, casts `step => IngestionStep::class`, `started_at`, `finished_at` datetime, relation `document()`.

`App\Chat\Models\Conversation`: `$guarded = []`, `last_message_at` datetime, relations `user()`, `messages()` (orderBy id).

`App\Chat\Models\Message`: `$guarded = []`, casts `status => MessageStatus::class`, `grounded => boolean`, relation `conversation()`, `citations()` (hasMany MessageCitation, orderBy marker).

`App\Chat\Models\MessageCitation`: `$guarded = []`, relations `message()`, `chunk()` (belongsTo DocumentChunk, 'chunk_id').

`App\Insights\Models\KnowledgeGap`: `$guarded = []`, `last_asked_at` datetime.

`App\Models\User`: додати `role`, `department_id`, `job_role` у `$fillable`, метод:
```php
public function isHrAdmin(): bool
{
    return $this->role === 'hr_admin';
}

public function department(): BelongsTo
{
    return $this->belongsTo(\App\Knowledge\Models\Department::class);
}
```
Прибрати `HasApiTokens`, якщо `install:api` додав, не потрібен для cookie-автентифікації (залишити не шкодить, але тоді не забути про міграцію personal_access_tokens, її лишаємо як є).

- [x] **Step 4: Фабрики**

`DepartmentFactory`: `name => fake()->unique()->randomElement(['Engineering', 'Marketing', 'Sales', 'Support', 'HR'])`.

`UserFactory`: додати стани
```php
public function hrAdmin(): static
{
    return $this->state(fn () => ['role' => 'hr_admin']);
}

public function inDepartment(Department $department, ?string $jobRole = null): static
{
    return $this->state(fn () => ['department_id' => $department->id, 'job_role' => $jobRole]);
}
```

`DocumentFactory`:
```php
public function definition(): array
{
    $sha = hash('sha256', fake()->uuid());
    return [
        'title' => fake()->sentence(3),
        'original_filename' => 'doc.md',
        'mime' => 'text/markdown',
        'size_bytes' => 1024,
        'sha256' => $sha,
        'storage_path' => "knowledge/$sha.md",
        'audience_type' => AudienceType::All,
        'audience_value' => null,
        'status' => DocumentStatus::Ready,
        'uploaded_by' => User::factory()->hrAdmin(),
    ];
}

public function forDepartment(Department $department): static
{
    return $this->state(fn () => ['audience_type' => AudienceType::Department, 'audience_value' => (string) $department->id]);
}

public function forRole(string $role): static
{
    return $this->state(fn () => ['audience_type' => AudienceType::Role, 'audience_value' => $role]);
}

public function status(DocumentStatus $status): static
{
    return $this->state(fn () => ['status' => $status]);
}
```

`ConversationFactory`: `user_id => User::factory()`, `last_message_at => now()`.

- [x] **Step 5: Тест схеми й видимості (падає до реалізації)**

`tests/Feature/Knowledge/SchemaTest.php`:
```php
<?php

use App\Knowledge\Models\Department;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('stores and reads a vector in document_chunks', function () {
    $doc = Document::factory()->create();
    $vector = array_fill(0, 384, 0.0);
    $vector[0] = 1.0;
    DB::insert(
        'INSERT INTO document_chunks (document_id, position, content, token_count, embedding, created_at, updated_at)
         VALUES (?, ?, ?, ?, VEC_FromText(?), NOW(), NOW())',
        [$doc->id, 0, 'hello', 1, json_encode($vector)]
    );
    $distance = DB::selectOne(
        'SELECT VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS d FROM document_chunks WHERE document_id = ?',
        [json_encode($vector), $doc->id]
    )->d;
    expect((float) $distance)->toBeLessThan(0.0001);
});

it('applies audience rules in visibleTo', function () {
    $eng = Department::factory()->create();
    $mkt = Department::factory()->create();
    $user = User::factory()->inDepartment($eng, 'developer')->create();

    $all = Document::factory()->create(['title' => 'all']);
    $engDoc = Document::factory()->forDepartment($eng)->create(['title' => 'eng']);
    $mktDoc = Document::factory()->forDepartment($mkt)->create(['title' => 'mkt']);
    $devDoc = Document::factory()->forRole('developer')->create(['title' => 'dev']);
    $qaDoc = Document::factory()->forRole('qa')->create(['title' => 'qa']);

    $titles = Document::query()->visibleTo($user)->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(['all', 'dev', 'eng']);
});

it('does not match department rule for a user without department', function () {
    $eng = Department::factory()->create();
    $user = User::factory()->create(['department_id' => null, 'job_role' => null]);
    Document::factory()->forDepartment($eng)->create();
    Document::factory()->forRole('qa')->create();

    expect(Document::query()->visibleTo($user)->count())->toBe(0);
});
```

- [x] **Step 6: Запустити міграції й тести**

```bash
docker compose up -d mariadb && php artisan migrate --env=testing && php artisan test --filter=SchemaTest
```
Expected: 3 passed. Якщо `VECTOR` дає синтаксичну помилку, перевірити версію: `docker compose exec mariadb mariadb -V` має показати 11.8.

- [x] **Step 7: Коміт**

```bash
git add -A
git commit -m "feat(knowledge): schema, enums, models with audience visibility (AC-5 scope)"
```

---

### Task 5: Автентифікація Sanctum SPA (login, logout, me) ✅ (d3900a9 + 9c00df3, рев'ю прийнято після 1 раунду)

**Files:**
- Create: `app/Http/Controllers/AuthController.php`, `app/Http/Resources/UserResource.php`
- Modify: `routes/api.php`, `bootstrap/app.php`, `config/cors.php`
- Test: `tests/Feature/Auth/AuthTest.php`

**Interfaces:**
- Produces: маршрути `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/me`. Група маршрутів `Route::prefix('v1')->middleware('auth:sanctum')` у `routes/api.php`, у яку наступні задачі додають ресурси. Формат помилки `{"error": {"code", "message", "details"}}` через `bootstrap/app.php` `withExceptions`.

- [x] **Step 1: Тест**

```php
<?php

use App\Models\User;

it('logs in with valid credentials and returns me', function () {
    $user = User::factory()->hrAdmin()->create(['email' => 'hr@vesna.test', 'password' => 'secret123']);

    $this->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/login', ['email' => 'hr@vesna.test', 'password' => 'secret123'])
        ->assertNoContent();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'hr@vesna.test')
        ->assertJsonPath('data.role', 'hr_admin');
});

it('rejects invalid credentials with error envelope', function () {
    User::factory()->create(['email' => 'u@vesna.test', 'password' => 'secret123']);

    $this->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/login', ['email' => 'u@vesna.test', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('returns 401 envelope for guests', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('logs out', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->postJson('/api/v1/auth/logout')->assertNoContent();
});
```

Run: `php artisan test --filter=AuthTest`. Expected: FAIL (404 на маршрути).

- [x] **Step 2: Контролер і ресурс**

`app/Http/Controllers/AuthController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Невірний email або пароль.']);
        }

        $request->session()->regenerate();

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response();
    }
}
```

`UserResource::toArray`: `id, name, email, role, department_id, job_role`.

- [x] **Step 3: Маршрути, middleware, формат помилок**

`routes/api.php`:
```php
<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
```

`bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
})
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));

    $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
        if ($request->is('api/*')) {
            return response()->json(['error' => [
                'code' => 'validation_failed', 'message' => $e->getMessage(), 'details' => $e->errors(),
            ]], 422);
        }
    });
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        if ($request->is('api/*')) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Потрібен вхід.', 'details' => []]], 401);
        }
    });
    $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
        if ($request->is('api/*')) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => 'Немає доступу.', 'details' => []]], 403);
        }
    });
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
        if ($request->is('api/*')) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Не знайдено.', 'details' => []]], 404);
        }
    });
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, $request) {
        if ($request->is('api/*')) {
            return response()->json(['error' => ['code' => 'rate_limited', 'message' => 'Забагато запитів.', 'details' => []]], 429, $e->getHeaders());
        }
    });
})
```

`config/cors.php`: `paths => ['api/*', 'sanctum/csrf-cookie']`, `allowed_origins => [env('FRONTEND_URL', 'http://localhost:5173')]`, `supports_credentials => true`.

- [x] **Step 4: Запустити тести**

Run: `php artisan test --filter=AuthTest`. Expected: 4 passed.

- [x] **Step 5: Коміт**

```bash
git add -A
git commit -m "feat(auth): sanctum spa login/logout/me with json error envelope"
```

---

### Task 6: Контракти EmbeddingProvider і TextExtractor, HTTP-реалізації, фейки ✅ (95413d5, рев'ю прийнято)

**Files:**
- Create: `app/Knowledge/Contracts/EmbeddingProvider.php`, `app/Knowledge/Contracts/TextExtractor.php`, `app/Knowledge/Embedding/HttpEmbeddingProvider.php`, `app/Knowledge/Embedding/FakeEmbeddingProvider.php`, `app/Knowledge/Embedding/EmbeddingException.php`, `app/Knowledge/Extraction/ExtractedPage.php`, `app/Knowledge/Extraction/HttpTextExtractor.php`, `app/Knowledge/Extraction/LocalTextExtractor.php`, `app/Knowledge/Extraction/CompositeTextExtractor.php`, `app/Knowledge/Extraction/FakeTextExtractor.php`, `app/Knowledge/Extraction/ExtractionException.php`, `app/Providers/RagServiceProvider.php`
- Modify: `bootstrap/providers.php`, `tests/TestCase.php`
- Test: `tests/Unit/Knowledge/FakeEmbeddingProviderTest.php`, `tests/Feature/Knowledge/HttpEmbeddingProviderTest.php`, `tests/Feature/Knowledge/TextExtractorTest.php`

**Interfaces:**
- Produces:
```php
interface EmbeddingProvider {
    /** @param list<string> $texts @return list<list<float>> */
    public function embedPassages(array $texts): array;
    /** @return list<float> */
    public function embedQuery(string $text): array;
}
interface TextExtractor {
    public function supports(string $mime): bool;
    /** @return list<ExtractedPage> */
    public function extract(string $absolutePath, string $mime): array;
}
final readonly class ExtractedPage { public function __construct(public int $page, public string $text) {} }
```
`EmbeddingException` з кодами-константами `UNAVAILABLE`, `BAD_DIMENSION`. `ExtractionException` з `UNAVAILABLE`, `UNSUPPORTED`. `FakeEmbeddingProvider::vectorFor(string $text): array` публічний, щоб тести пошуку могли передбачити вектор.

- [x] **Step 1: Unit-тест фейкового провайдера**

`tests/Unit/Knowledge/FakeEmbeddingProviderTest.php`:
```php
<?php

use App\Knowledge\Embedding\FakeEmbeddingProvider;

it('returns deterministic normalized 384-dim vectors', function () {
    $p = new FakeEmbeddingProvider();
    $a = $p->embedQuery('відпустка');
    $b = $p->embedQuery('відпустка');
    $c = $p->embedQuery('зарплата');

    expect($a)->toHaveCount(384)->toBe($b)->not->toBe($c);
    $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $a)));
    expect(abs($norm - 1.0))->toBeLessThan(1e-6);
});

it('makes similar texts closer than unrelated ones', function () {
    $p = new FakeEmbeddingProvider();
    $cos = fn (array $x, array $y) => array_sum(array_map(fn ($a, $b) => $a * $b, $x, $y));
    $base = $p->embedQuery('політика відпусток: скільки днів відпустки');
    $near = $p->embedQuery('скільки днів відпустки');
    $far = $p->embedQuery('налаштування принтера на четвертому поверсі');

    expect($cos($base, $near))->toBeGreaterThan($cos($base, $far));
});
```

- [x] **Step 2: Реалізація фейка**

Фейк будує вектор із хешів слів (bag of words у 384 кошики), тому схожі тексти близькі, а різні далекі. Це дає реалістичну поведінку пошуку в тестах без моделі.

`app/Knowledge/Embedding/FakeEmbeddingProvider.php`:
```php
<?php

namespace App\Knowledge\Embedding;

use App\Knowledge\Contracts\EmbeddingProvider;

final class FakeEmbeddingProvider implements EmbeddingProvider
{
    public const DIM = 384;

    public function embedPassages(array $texts): array
    {
        return array_map(fn (string $t) => $this->vectorFor($t), $texts);
    }

    public function embedQuery(string $text): array
    {
        return $this->vectorFor($text);
    }

    /** @return list<float> */
    public function vectorFor(string $text): array
    {
        $vector = array_fill(0, self::DIM, 0.0);
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            $hash = crc32($word);
            $vector[$hash % self::DIM] += 1.0;
            $vector[($hash >> 8) % self::DIM] += 0.5;
        }
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vector)));
        if ($norm == 0.0) {
            $vector[0] = 1.0;
            return $vector;
        }

        return array_map(fn ($x) => $x / $norm, $vector);
    }
}
```

Run: `php artisan test --filter=FakeEmbeddingProviderTest`. Expected: 2 passed.

- [x] **Step 3: Тест HTTP-провайдера з Http::fake (включно з перевіркою розміру вектора, Review Focus 5)**

`tests/Feature/Knowledge/HttpEmbeddingProviderTest.php`:
```php
<?php

use App\Knowledge\Embedding\EmbeddingException;
use App\Knowledge\Embedding\HttpEmbeddingProvider;
use Illuminate\Support\Facades\Http;

it('posts passages with kind=passage and returns vectors', function () {
    Http::fake(['embedder.invalid/embed' => Http::response([
        'vectors' => [array_fill(0, 384, 0.1), array_fill(0, 384, 0.2)], 'model' => 'm', 'dim' => 384,
    ])]);

    $vectors = app(HttpEmbeddingProvider::class)->embedPassages(['a', 'b']);

    expect($vectors)->toHaveCount(2);
    Http::assertSent(fn ($req) => $req['kind'] === 'passage' && $req['texts'] === ['a', 'b']);
});

it('uses kind=query for a single query', function () {
    Http::fake(['embedder.invalid/embed' => Http::response(['vectors' => [array_fill(0, 384, 0.1)], 'model' => 'm', 'dim' => 384])]);

    app(HttpEmbeddingProvider::class)->embedQuery('q');

    Http::assertSent(fn ($req) => $req['kind'] === 'query' && $req['texts'] === ['q']);
});

it('throws BAD_DIMENSION when the sidecar returns a wrong vector size', function () {
    Http::fake(['embedder.invalid/embed' => Http::response(['vectors' => [array_fill(0, 768, 0.1)], 'model' => 'm', 'dim' => 768])]);

    expect(fn () => app(HttpEmbeddingProvider::class)->embedQuery('q'))
        ->toThrow(EmbeddingException::class, EmbeddingException::BAD_DIMENSION);
});

it('throws UNAVAILABLE on connection error or 5xx', function () {
    Http::fake(['embedder.invalid/embed' => Http::response('boom', 503)]);

    expect(fn () => app(HttpEmbeddingProvider::class)->embedQuery('q'))
        ->toThrow(EmbeddingException::class, EmbeddingException::UNAVAILABLE);
});
```

- [x] **Step 4: Реалізація HTTP-провайдера і винятку**

```php
<?php

namespace App\Knowledge\Embedding;

use RuntimeException;

final class EmbeddingException extends RuntimeException
{
    public const UNAVAILABLE = 'embedder_unavailable';
    public const BAD_DIMENSION = 'embedder_bad_dimension';

    public function __construct(public readonly string $code_, string $detail = '')
    {
        parent::__construct(trim("$code_ $detail"));
    }
}
```

```php
<?php

namespace App\Knowledge\Embedding;

use App\Knowledge\Contracts\EmbeddingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $dim,
    ) {}

    public function embedPassages(array $texts): array
    {
        return $this->call($texts, 'passage');
    }

    public function embedQuery(string $text): array
    {
        return $this->call([$text], 'query')[0];
    }

    /** @param list<string> $texts @return list<list<float>> */
    private function call(array $texts, string $kind): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/embed', ['texts' => $texts, 'kind' => $kind]);
        } catch (ConnectionException $e) {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, $e->getMessage());
        }

        if ($response->failed()) {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'HTTP '.$response->status());
        }

        $vectors = $response->json('vectors');
        if (! is_array($vectors) || count($vectors) !== count($texts)) {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'malformed response');
        }
        foreach ($vectors as $v) {
            if (! is_array($v) || count($v) !== $this->dim) {
                throw new EmbeddingException(EmbeddingException::BAD_DIMENSION, 'expected '.$this->dim.', got '.count($v));
            }
        }

        return array_map(fn (array $v) => array_map('floatval', array_values($v)), $vectors);
    }
}
```

- [x] **Step 5: Екстрактори і тест**

`ExtractedPage` як у Interfaces. `ExtractionException` за тим самим шаблоном, що `EmbeddingException`, коди `UNAVAILABLE = 'extractor_unavailable'`, `UNSUPPORTED = 'unsupported_format'`.

`LocalTextExtractor`: `supports()` для `text/markdown`, `text/plain`; `extract()` читає файл, прибирає BOM (`\xEF\xBB\xBF`), нормалізує `\r\n` → `\n`, повертає одну сторінку `page=1`.

`HttpTextExtractor`: `supports()` для PDF і DOCX; `extract()` робить `Http::timeout($timeout)->attach('file', file_get_contents($path), basename($path), ['Content-Type' => $mime])->post($base.'/extract-text')`, мапить `pages` у `ExtractedPage`, `ConnectionException` і 5xx → `UNAVAILABLE`, 422 → `UNSUPPORTED`.

`CompositeTextExtractor`: конструктор `(TextExtractor ...$extractors)`, `supports()` якщо хтось підтримує, `extract()` делегує першому, що підтримує, інакше `ExtractionException(UNSUPPORTED)`.

`FakeTextExtractor`: `public array $pagesByPath = []`, `public ?\Throwable $throws = null`; `supports()` завжди true; `extract()` кидає `$throws`, якщо заданий, інакше повертає `$pagesByPath[$path] ?? [new ExtractedPage(1, file_get_contents($path))]`.

`tests/Feature/Knowledge/TextExtractorTest.php`:
```php
<?php

use App\Knowledge\Extraction\CompositeTextExtractor;
use App\Knowledge\Extraction\ExtractionException;
use App\Knowledge\Extraction\HttpTextExtractor;
use App\Knowledge\Extraction\LocalTextExtractor;
use Illuminate\Support\Facades\Http;

it('reads markdown locally, strips BOM and CRLF', function () {
    $path = tempnam(sys_get_temp_dir(), 'md');
    file_put_contents($path, "\xEF\xBB\xBF# Заголовок\r\n\r\nАбзац\r\n");

    $pages = (new LocalTextExtractor())->extract($path, 'text/markdown');

    expect($pages)->toHaveCount(1)->and($pages[0]->text)->toBe("# Заголовок\n\nАбзац\n");
});

it('sends pdf to the sidecar and maps pages', function () {
    Http::fake(['embedder.invalid/extract-text' => Http::response(['pages' => [['page' => 1, 'text' => 'a'], ['page' => 2, 'text' => 'b']], 'meta' => ['pages_count' => 2]])]);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, '%PDF-1.4');

    $pages = app(HttpTextExtractor::class)->extract($path, 'application/pdf');

    expect($pages)->toHaveCount(2)->and($pages[1]->page)->toBe(2)->and($pages[1]->text)->toBe('b');
});

it('throws UNSUPPORTED for unknown mime in composite', function () {
    $composite = new CompositeTextExtractor(new LocalTextExtractor());
    expect(fn () => $composite->extract('/tmp/x', 'image/png'))
        ->toThrow(ExtractionException::class, ExtractionException::UNSUPPORTED);
});
```

- [x] **Step 6: RagServiceProvider і прив'язка фейків у тестах**

`app/Providers/RagServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Contracts\TextExtractor;
use App\Knowledge\Embedding\HttpEmbeddingProvider;
use App\Knowledge\Extraction\CompositeTextExtractor;
use App\Knowledge\Extraction\HttpTextExtractor;
use App\Knowledge\Extraction\LocalTextExtractor;
use Illuminate\Support\ServiceProvider;

class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HttpEmbeddingProvider::class, fn () => new HttpEmbeddingProvider(
            config('rag.embedder.url'), config('rag.embedder.timeout_embed'), config('rag.embedding_dim'),
        ));
        $this->app->bind(EmbeddingProvider::class, HttpEmbeddingProvider::class);

        $this->app->singleton(HttpTextExtractor::class, fn () => new HttpTextExtractor(
            config('rag.embedder.url'), config('rag.embedder.timeout_extract'),
        ));
        $this->app->bind(TextExtractor::class, fn ($app) => new CompositeTextExtractor(
            new LocalTextExtractor(), $app->make(HttpTextExtractor::class),
        ));
    }
}
```
Додати клас у `bootstrap/providers.php`.

`tests/TestCase.php::bindFakes()`:
```php
$this->app->singleton(FakeEmbeddingProvider::class);
$this->app->bind(EmbeddingProvider::class, FakeEmbeddingProvider::class);
$this->app->singleton(FakeTextExtractor::class);
$this->app->bind(TextExtractor::class, FakeTextExtractor::class);
```
Тести, яким потрібна HTTP-реалізація, резолвлять конкретний клас (`app(HttpEmbeddingProvider::class)`), як у тестах вище.

- [x] **Step 7: Запустити всі тести задачі**

Run: `php artisan test --filter='FakeEmbeddingProviderTest|HttpEmbeddingProviderTest|TextExtractorTest'`. Expected: 9 passed.

- [x] **Step 8: Коміт**

```bash
git add -A
git commit -m "feat(knowledge): embedding and extraction contracts with http and fake implementations"
```

---

### Task 7: Chunker ✅ (26b4a1a + ccfbca5, рев'ю прийнято після 1 раунду)

**Files:**
- Create: `app/Knowledge/Chunking/Chunk.php`, `app/Knowledge/Chunking/Chunker.php`
- Test: `tests/Unit/Knowledge/ChunkerTest.php`

**Interfaces:**
- Produces:
```php
final readonly class Chunk { public function __construct(public int $position, public ?int $page, public ?string $heading, public string $content, public int $tokenCount) {} }
final class Chunker {
    public function __construct(int $targetTokens = 400, int $overlapTokens = 60, int $maxTokens = 600) {}
    /** @param list<ExtractedPage> $pages @return list<Chunk> */
    public function chunk(array $pages): array;
    public static function estimateTokens(string $text): int; // ceil(mb_strlen/4)
}
```

- [x] **Step 1: Тести**

```php
<?php

use App\Knowledge\Chunking\Chunker;
use App\Knowledge\Extraction\ExtractedPage;

function words(int $n, string $w = 'слово'): string
{
    return implode(' ', array_fill(0, $n, $w));
}

it('returns no chunks for empty pages', function () {
    expect((new Chunker())->chunk([new ExtractedPage(1, "  \n ")]))->toBe([]);
});

it('keeps a short document as one chunk with page and heading', function () {
    $chunks = (new Chunker())->chunk([new ExtractedPage(1, "# Відпустки\n\nКожен має 24 дні.")]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->position)->toBe(0)
        ->and($chunks[0]->page)->toBe(1)
        ->and($chunks[0]->heading)->toBe('Відпустки')
        ->and($chunks[0]->content)->toContain('24 дні')
        ->and($chunks[0]->tokenCount)->toBe(Chunker::estimateTokens($chunks[0]->content));
});

it('splits long text into chunks near the target size with overlap', function () {
    $paragraphs = [];
    for ($i = 0; $i < 12; $i++) {
        $paragraphs[] = "Абзац $i. ".words(60);
    }
    $chunks = (new Chunker(400, 60, 600))->chunk([new ExtractedPage(1, implode("\n\n", $paragraphs))]);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $c) {
        expect($c->tokenCount)->toBeLessThanOrEqual(600);
    }
    // overlap: the start of chunk 1 repeats the tail of chunk 0
    $tail = mb_substr($chunks[0]->content, -100);
    expect(mb_strpos($chunks[1]->content, mb_substr($tail, 0, 40)))->not->toBeFalse();
});

it('starts a new chunk at each markdown heading and tracks the heading', function () {
    $text = "# Розділ A\n\n".words(50)."\n\n## Розділ B\n\n".words(50);
    $chunks = (new Chunker())->chunk([new ExtractedPage(1, $text)]);

    expect(array_map(fn ($c) => $c->heading, $chunks))->toBe(['Розділ A', 'Розділ B']);
});

it('splits an oversized paragraph by sentences', function () {
    $sentence = words(30).'. ';
    $huge = str_repeat($sentence, 40); // ~1200 words, far above max
    $chunks = (new Chunker(400, 60, 600))->chunk([new ExtractedPage(1, $huge)]);

    expect(count($chunks))->toBeGreaterThan(2);
    foreach ($chunks as $c) {
        expect($c->tokenCount)->toBeLessThanOrEqual(600);
    }
});

it('records the page where each chunk starts', function () {
    $chunks = (new Chunker())->chunk([new ExtractedPage(1, 'Сторінка один.'), new ExtractedPage(2, 'Сторінка два.')]);
    // both tiny paragraphs merge into one chunk that starts on page 1
    expect($chunks)->toHaveCount(1)->and($chunks[0]->page)->toBe(1);

    $chunks = (new Chunker())->chunk([new ExtractedPage(1, words(380)), new ExtractedPage(2, words(380))]);
    expect($chunks[0]->page)->toBe(1)->and(end($chunks)->page)->toBe(2);
});

it('treats CRLF and BOM input like LF input', function () {
    $lf = "# Заголовок\n\nАбзац один.\n\nАбзац два.";
    $crlf = "\xEF\xBB\xBF# Заголовок\r\n\r\nАбзац один.\r\n\r\nАбзац два.";
    $a = (new Chunker())->chunk([new ExtractedPage(1, $lf)]);
    $b = (new Chunker())->chunk([new ExtractedPage(1, $crlf)]);

    expect(array_map(fn ($c) => [$c->heading, $c->content], $b))->toBe(array_map(fn ($c) => [$c->heading, $c->content], $a));
});

it('detects numbered and uppercase headings in plain text from pdf', function () {
    $text = "1. ЗАГАЛЬНІ ПОЛОЖЕННЯ\n".words(30)."\n\nПОРЯДОК НАДАННЯ\n".words(30);
    $chunks = (new Chunker())->chunk([new ExtractedPage(1, $text)]);

    expect($chunks[0]->heading)->toBe('1. ЗАГАЛЬНІ ПОЛОЖЕННЯ');
});
```

Run: `php artisan test --filter=ChunkerTest`. Expected: FAIL (клас відсутній).

- [x] **Step 2: Реалізація**

Алгоритм: нормалізувати текст кожної сторінки (BOM, CRLF), розбити на блоки по порожніх рядках, кожен блок класифікувати як заголовок або абзац, абзаци понад `maxTokens` різати по реченнях, далі жадібно збирати блоки у чанк до `targetTokens`; новий заголовок завжди закриває поточний чанк; при відкритті наступного чанка додавати хвіст попереднього на `overlapTokens * 4` символів.

```php
<?php

namespace App\Knowledge\Chunking;

use App\Knowledge\Extraction\ExtractedPage;

final class Chunker
{
    public function __construct(
        private readonly int $targetTokens = 400,
        private readonly int $overlapTokens = 60,
        private readonly int $maxTokens = 600,
    ) {}

    public static function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /** @param list<ExtractedPage> $pages @return list<Chunk> */
    public function chunk(array $pages): array
    {
        $blocks = $this->blocks($pages);
        $chunks = [];
        $buffer = '';
        $bufferPage = null;
        $heading = null;
        $bufferHeading = null;
        $prevTail = '';

        $flush = function () use (&$chunks, &$buffer, &$bufferPage, &$bufferHeading, &$prevTail) {
            $content = trim($buffer);
            if ($content !== '') {
                $chunks[] = new Chunk(count($chunks), $bufferPage, $bufferHeading, $content, self::estimateTokens($content));
                $prevTail = mb_substr($content, -($this->overlapTokens * 4));
            }
            $buffer = '';
            $bufferPage = null;
        };

        foreach ($blocks as [$type, $text, $page]) {
            if ($type === 'heading') {
                $flush();
                $heading = $text;
                $prevTail = '';
                continue;
            }
            foreach ($this->splitOversized($text) as $piece) {
                $candidate = $buffer === '' ? $piece : $buffer."\n\n".$piece;
                if ($buffer !== '' && self::estimateTokens($candidate) > $this->targetTokens) {
                    $flush();
                    $candidate = $prevTail !== '' ? $prevTail."\n\n".$piece : $piece;
                }
                if ($buffer === '') {
                    $bufferPage = $page;
                    $bufferHeading = $heading;
                }
                $buffer = $candidate;
            }
        }
        $flush();

        return $chunks;
    }

    /** @param list<ExtractedPage> $pages @return list<array{0:string,1:string,2:int}> */
    private function blocks(array $pages): array
    {
        $blocks = [];
        foreach ($pages as $page) {
            $text = preg_replace("/^\xEF\xBB\xBF/", '', $page->text) ?? $page->text;
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            foreach (preg_split("/\n\s*\n/u", $text) ?: [] as $raw) {
                $block = trim($raw);
                if ($block === '') {
                    continue;
                }
                // a heading line followed by body text inside one block (common in PDF text)
                $lines = explode("\n", $block);
                if (count($lines) > 1 && $this->isHeading($lines[0])) {
                    $blocks[] = ['heading', $this->cleanHeading($lines[0]), $page->page];
                    $block = trim(implode("\n", array_slice($lines, 1)));
                    if ($block === '') {
                        continue;
                    }
                } elseif ($this->isHeading($block)) {
                    $blocks[] = ['heading', $this->cleanHeading($block), $page->page];
                    continue;
                }
                $blocks[] = ['paragraph', $block, $page->page];
            }
        }

        return $blocks;
    }

    private function isHeading(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || mb_strlen($line) > 80 || str_contains($line, "\n")) {
            return false;
        }
        if (preg_match('/^#{1,6}\s+\S/u', $line)) {
            return true;
        }
        if (preg_match('/^\d+(\.\d+)*\.?\s+\S/u', $line) && ! str_ends_with($line, '.')) {
            return true;
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';

        return mb_strlen($letters) >= 4 && $letters === mb_strtoupper($letters);
    }

    private function cleanHeading(string $line): string
    {
        return trim(preg_replace('/^#{1,6}\s+/u', '', trim($line)) ?? $line);
    }

    /** @return list<string> */
    private function splitOversized(string $paragraph): array
    {
        if (self::estimateTokens($paragraph) <= $this->maxTokens) {
            return [$paragraph];
        }
        $sentences = preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph];
        $pieces = [];
        $current = '';
        foreach ($sentences as $s) {
            $candidate = $current === '' ? $s : $current.' '.$s;
            if ($current !== '' && self::estimateTokens($candidate) > $this->targetTokens) {
                $pieces[] = $current;
                $candidate = $s;
            }
            $current = $candidate;
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
```

- [x] **Step 3: Запустити тести**

Run: `php artisan test --filter=ChunkerTest`. Expected: 8 passed. Якщо тест перекриття падає, перевірити, що `prevTail` скидається лише на заголовку.

- [x] **Step 4: Коміт**

```bash
git add app/Knowledge/Chunking tests/Unit/Knowledge/ChunkerTest.php
git commit -m "feat(knowledge): deterministic chunker with headings, overlap and sentence split"
```

---

### Task 8: Ланцюжок інжесту: ChunkWriter, три job-и, DocumentIngestionService

**Files:**
- Create: `app/Knowledge/Ingestion/ChunkWriter.php`, `app/Knowledge/Ingestion/DocumentIngestionService.php`, `app/Knowledge/Ingestion/IngestionArtifacts.php`, `app/Knowledge/Jobs/ExtractDocumentText.php`, `app/Knowledge/Jobs/ChunkDocument.php`, `app/Knowledge/Jobs/EmbedDocumentChunks.php`
- Test: `tests/Feature/Knowledge/IngestionChainTest.php`, `tests/Feature/Knowledge/Jobs/ExtractDocumentTextTest.php`, `tests/Feature/Knowledge/Jobs/EmbedDocumentChunksTest.php`

**Interfaces:**
- Consumes: `TextExtractor`, `EmbeddingProvider`, `Chunker`, моделі з Task 4.
- Produces:
```php
final class DocumentIngestionService {
    public function dispatchChain(Document $document): void;      // Bus::chain of the three jobs on queue 'ingestion'
}
final class IngestionArtifacts {                                  // paths of intermediate json on the 'local' disk
    public static function pagesPath(Document $d): string;        // knowledge/{sha256}.pages.json
    public static function chunksPath(Document $d): string;       // knowledge/{sha256}.chunks.json
}
final class ChunkWriter {
    /** @param list<Chunk> $chunks @param list<list<float>> $vectors */
    public function insertBatch(Document $document, array $chunks, array $vectors): void; // one transaction, raw INSERT with VEC_FromText
}
```
Кожен job: `public int $tries = 3; public array $backoff = [10, 30, 90]; public function __construct(public int $documentId) { $this->onQueue('ingestion'); }`.

- [ ] **Step 1: Тест усього ланцюжка (sync queue у .env.testing)**

`tests/Feature/Knowledge/IngestionChainTest.php`:
```php
<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\IngestionStep;
use App\Knowledge\Extraction\ExtractedPage;
use App\Knowledge\Extraction\FakeTextExtractor;
use App\Knowledge\Ingestion\DocumentIngestionService;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('runs extract -> chunk -> embed and marks the document ready (AC-1)', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create([
        'storage_path' => 'knowledge/abc.md', 'mime' => 'text/markdown',
    ]);
    Storage::disk('local')->put('knowledge/abc.md', "# Відпустки\n\nКожен має 24 дні.\n\n# Лікарняні\n\nПотрібна довідка.");

    app(DocumentIngestionService::class)->dispatchChain($doc);

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Ready)
        ->and($doc->chunks_count)->toBe(2)
        ->and(DB::table('document_chunks')->where('document_id', $doc->id)->count())->toBe(2)
        ->and($doc->ingestionRuns->pluck('step')->map->value->all())->toBe(['extract', 'chunk', 'embed'])
        ->and($doc->ingestionRuns->pluck('status')->unique()->all())->toBe(['done']);
});

it('uses the extractor for pdf pages and keeps page numbers on chunks', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create([
        'storage_path' => 'knowledge/x.pdf', 'mime' => 'application/pdf',
    ]);
    Storage::disk('local')->put('knowledge/x.pdf', '%PDF');
    $fake = app(FakeTextExtractor::class);
    $fake->pagesByPath[Storage::disk('local')->path('knowledge/x.pdf')] = [
        new ExtractedPage(1, 'Сторінка один про відпустки.'),
        new ExtractedPage(2, 'Сторінка два про лікарняні.'),
    ];

    app(DocumentIngestionService::class)->dispatchChain($doc);

    $pages = DB::table('document_chunks')->where('document_id', $doc->id)->pluck('page')->all();
    expect($doc->refresh()->status)->toBe(DocumentStatus::Ready)->and($pages)->toContain(1);
});
```

- [ ] **Step 2: Тести job-ів окремо (failed-гілки, AC-3, Review Focus 1)**

`tests/Feature/Knowledge/Jobs/ExtractDocumentTextTest.php`:
```php
<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Knowledge\Extraction\ExtractedPage;
use App\Knowledge\Extraction\ExtractionException;
use App\Knowledge\Extraction\FakeTextExtractor;
use App\Knowledge\Jobs\ExtractDocumentText;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('marks no_text_layer when extractor returns only empty pages', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create(['storage_path' => 'knowledge/scan.pdf', 'mime' => 'application/pdf']);
    Storage::disk('local')->put('knowledge/scan.pdf', '%PDF');
    app(FakeTextExtractor::class)->pagesByPath[Storage::disk('local')->path('knowledge/scan.pdf')] = [new ExtractedPage(1, '  '), new ExtractedPage(2, '')];

    $job = new ExtractDocumentText($doc->id);
    try {
        $job->handle(app(\App\Knowledge\Contracts\TextExtractor::class));
    } catch (\Throwable $e) {
        $job->failed($e);
    }

    expect($doc->refresh()->status)->toBe(DocumentStatus::Failed)
        ->and($doc->failure_code)->toBe(FailureCode::NoTextLayer);
});

it('marks extractor_unavailable in failed() when the sidecar is down', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create(['storage_path' => 'knowledge/a.pdf', 'mime' => 'application/pdf']);
    Storage::disk('local')->put('knowledge/a.pdf', '%PDF');
    app(FakeTextExtractor::class)->throws = new ExtractionException(ExtractionException::UNAVAILABLE, 'down');

    $job = new ExtractDocumentText($doc->id);
    expect(fn () => $job->handle(app(\App\Knowledge\Contracts\TextExtractor::class)))->toThrow(ExtractionException::class);
    $job->failed(new ExtractionException(ExtractionException::UNAVAILABLE, 'down'));

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Failed)
        ->and($doc->failure_code)->toBe(FailureCode::ExtractorUnavailable)
        ->and($doc->ingestionRuns->last()->status)->toBe('failed');
});

it('exits silently when the document was deleted', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create();
    $doc->delete();

    (new ExtractDocumentText($doc->id))->handle(app(\App\Knowledge\Contracts\TextExtractor::class));

    expect(Document::withTrashed()->find($doc->id)->status)->toBe(DocumentStatus::Uploaded);
});
```

`tests/Feature/Knowledge/Jobs/EmbedDocumentChunksTest.php`:
```php
<?php

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Embedding\EmbeddingException;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Knowledge\Ingestion\IngestionArtifacts;
use App\Knowledge\Jobs\EmbedDocumentChunks;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function putChunks(Document $doc, int $n): void
{
    $chunks = [];
    for ($i = 0; $i < $n; $i++) {
        $chunks[] = ['position' => $i, 'page' => 1, 'heading' => null, 'content' => "chunk $i", 'tokenCount' => 2];
    }
    Storage::disk('local')->put(IngestionArtifacts::chunksPath($doc), json_encode($chunks));
}

it('embeds in batches of 32 and sets chunks_count', function () {
    config(['rag.embedder.batch_size' => 32]);
    $doc = Document::factory()->status(DocumentStatus::Chunking)->create();
    putChunks($doc, 70);

    (new EmbedDocumentChunks($doc->id))->handle(app(EmbeddingProvider::class), app(\App\Knowledge\Ingestion\ChunkWriter::class));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Ready)
        ->and($doc->chunks_count)->toBe(70)
        ->and(DB::table('document_chunks')->where('document_id', $doc->id)->count())->toBe(70);
});

it('marks embedder_unavailable on provider failure', function () {
    $doc = Document::factory()->status(DocumentStatus::Chunking)->create();
    putChunks($doc, 3);
    $this->app->bind(EmbeddingProvider::class, fn () => new class implements EmbeddingProvider {
        public function embedPassages(array $texts): array { throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'down'); }
        public function embedQuery(string $text): array { throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'down'); }
    });

    $job = new EmbedDocumentChunks($doc->id);
    expect(fn () => $job->handle(app(EmbeddingProvider::class), app(\App\Knowledge\Ingestion\ChunkWriter::class)))->toThrow(EmbeddingException::class);
    $job->failed(new EmbeddingException(EmbeddingException::UNAVAILABLE, 'down'));

    expect($doc->refresh()->failure_code)->toBe(FailureCode::EmbedderUnavailable);
});
```

Run: `php artisan test --filter='IngestionChainTest|ExtractDocumentTextTest|EmbedDocumentChunksTest'`. Expected: FAIL (класи відсутні).

- [ ] **Step 3: IngestionArtifacts, ChunkWriter, DocumentIngestionService**

```php
<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Models\Document;

final class IngestionArtifacts
{
    public static function pagesPath(Document $d): string
    {
        return "knowledge/{$d->sha256}.pages.json";
    }

    public static function chunksPath(Document $d): string
    {
        return "knowledge/{$d->sha256}.chunks.json";
    }
}
```

```php
<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\DB;

final class ChunkWriter
{
    /** @param list<Chunk> $chunks @param list<list<float>> $vectors */
    public function insertBatch(Document $document, array $chunks, array $vectors): void
    {
        if (count($chunks) !== count($vectors)) {
            throw new \InvalidArgumentException('chunks and vectors length mismatch');
        }
        DB::transaction(function () use ($document, $chunks, $vectors) {
            $now = now();
            foreach ($chunks as $i => $chunk) {
                DB::insert(
                    'INSERT INTO document_chunks (document_id, position, page, heading, content, token_count, embedding, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, VEC_FromText(?), ?, ?)',
                    [$document->id, $chunk->position, $chunk->page, $chunk->heading, $chunk->content, $chunk->tokenCount,
                        json_encode($vectors[$i]), $now, $now]
                );
            }
        });
    }
}
```

```php
<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Jobs\ChunkDocument;
use App\Knowledge\Jobs\EmbedDocumentChunks;
use App\Knowledge\Jobs\ExtractDocumentText;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\Bus;

final class DocumentIngestionService
{
    public function dispatchChain(Document $document): void
    {
        Bus::chain([
            new ExtractDocumentText($document->id),
            new ChunkDocument($document->id),
            new EmbedDocumentChunks($document->id),
        ])->onQueue('ingestion')->dispatch();
    }
}
```

- [ ] **Step 4: Спільна база job-ів і три job-и**

Щоб не дублювати, абстрактний `app/Knowledge/Jobs/IngestionJob.php`:
```php
<?php

namespace App\Knowledge\Jobs;

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Knowledge\Enums\IngestionStep;
use App\Knowledge\Embedding\EmbeddingException;
use App\Knowledge\Extraction\ExtractionException;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\IngestionRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class IngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    protected ?IngestionRun $run = null;

    public function __construct(public int $documentId)
    {
        $this->onQueue('ingestion');
    }

    abstract protected function step(): IngestionStep;

    abstract protected function statusWhileRunning(): DocumentStatus;

    /** Returns null when the job must exit silently (deleted, or already past this step). */
    protected function begin(): ?Document
    {
        $document = Document::query()->find($this->documentId);
        if ($document === null || $document->status->order() > $this->statusWhileRunning()->order()) {
            return null;
        }
        $document->forceFill(['status' => $this->statusWhileRunning()])->save();
        $this->run = $document->ingestionRuns()->create([
            'step' => $this->step(), 'status' => 'running', 'attempt' => $this->attempts() ?: 1, 'started_at' => now(),
        ]);

        return $document;
    }

    protected function finish(Document $document, DocumentStatus $next, array $extra = []): void
    {
        $this->run?->forceFill(['status' => 'done', 'finished_at' => now()])->save();
        $document->forceFill(array_merge(['status' => $next], $extra))->save();
    }

    public function failed(Throwable $e): void
    {
        $document = Document::query()->find($this->documentId);
        if ($document === null) {
            return;
        }
        $document->ingestionRuns()->where('status', 'running')->update([
            'status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
        $document->markFailed($this->failureCode($e), $e->getMessage());
    }

    protected function failureCode(Throwable $e): FailureCode
    {
        return match (true) {
            $e instanceof NoTextLayerException => FailureCode::NoTextLayer,
            $e instanceof ExtractionException && $e->code_ === ExtractionException::UNSUPPORTED => FailureCode::UnsupportedFormat,
            $e instanceof ExtractionException => FailureCode::ExtractorUnavailable,
            $e instanceof EmbeddingException => FailureCode::EmbedderUnavailable,
            default => FailureCode::Unknown,
        };
    }
}
```

`app/Knowledge/Jobs/NoTextLayerException.php`: `final class NoTextLayerException extends \RuntimeException {}`.

`ExtractDocumentText`:
```php
final class ExtractDocumentText extends IngestionJob
{
    protected function step(): IngestionStep { return IngestionStep::Extract; }
    protected function statusWhileRunning(): DocumentStatus { return DocumentStatus::Extracting; }

    public function handle(TextExtractor $extractor): void
    {
        $document = $this->begin();
        if ($document === null) {
            return;
        }
        $disk = Storage::disk('local');
        $pages = $extractor->extract($disk->path($document->storage_path), $document->mime);
        $nonEmpty = array_filter($pages, fn (ExtractedPage $p) => trim($p->text) !== '');
        if ($nonEmpty === []) {
            throw new NoTextLayerException('document has no extractable text');
        }
        $disk->put(IngestionArtifacts::pagesPath($document), json_encode(
            array_map(fn (ExtractedPage $p) => ['page' => $p->page, 'text' => $p->text], array_values($pages)),
            JSON_UNESCAPED_UNICODE
        ));
        $this->finish($document, DocumentStatus::Extracting);
    }
}
```
Увага: `NoTextLayerException` не має ретраїв сенсу, тому в `handle` перед киданням викликати `$this->fail($e)` замість `throw`, щоб черга не повторювала: замінити `throw new NoTextLayerException(...)` на
```php
$e = new NoTextLayerException('document has no extractable text');
$this->fail($e);
throw $e;
```
У sync-черзі `fail()` викликає `failed()`; тест Step 2 також викликає `failed()` явно у catch, `markFailed` ідемпотентний, тож подвійний виклик безпечний.

`ChunkDocument`:
```php
final class ChunkDocument extends IngestionJob
{
    protected function step(): IngestionStep { return IngestionStep::Chunk; }
    protected function statusWhileRunning(): DocumentStatus { return DocumentStatus::Chunking; }

    public function handle(Chunker $chunker): void
    {
        $document = $this->begin();
        if ($document === null) {
            return;
        }
        $disk = Storage::disk('local');
        $pages = array_map(
            fn (array $p) => new ExtractedPage((int) $p['page'], (string) $p['text']),
            json_decode($disk->get(IngestionArtifacts::pagesPath($document)), true, flags: JSON_THROW_ON_ERROR)
        );
        $chunks = $chunker->chunk($pages);
        $disk->put(IngestionArtifacts::chunksPath($document), json_encode(array_map(fn (Chunk $c) => [
            'position' => $c->position, 'page' => $c->page, 'heading' => $c->heading, 'content' => $c->content, 'tokenCount' => $c->tokenCount,
        ], $chunks), JSON_UNESCAPED_UNICODE));
        $this->finish($document, DocumentStatus::Chunking);
    }
}
```
`Chunker` резолвиться з контейнера: у `RagServiceProvider::register` додати
```php
$this->app->bind(Chunker::class, fn () => new Chunker(
    config('rag.chunk.target_tokens'), config('rag.chunk.overlap_tokens'), config('rag.chunk.max_tokens'),
));
```

`EmbedDocumentChunks`:
```php
final class EmbedDocumentChunks extends IngestionJob
{
    protected function step(): IngestionStep { return IngestionStep::Embed; }
    protected function statusWhileRunning(): DocumentStatus { return DocumentStatus::Embedding; }

    public function handle(EmbeddingProvider $embeddings, ChunkWriter $writer): void
    {
        $document = $this->begin();
        if ($document === null) {
            return;
        }
        $raw = json_decode(Storage::disk('local')->get(IngestionArtifacts::chunksPath($document)), true, flags: JSON_THROW_ON_ERROR);
        $chunks = array_map(fn (array $c) => new Chunk($c['position'], $c['page'], $c['heading'], $c['content'], $c['tokenCount']), $raw);

        // idempotency for retries: drop partial rows from a previous attempt
        DB::table('document_chunks')->where('document_id', $document->id)->delete();

        foreach (array_chunk($chunks, (int) config('rag.embedder.batch_size')) as $batch) {
            $vectors = $embeddings->embedPassages(array_map(fn (Chunk $c) => $c->content, $batch));
            $writer->insertBatch($document, $batch, $vectors);
        }
        $this->finish($document, DocumentStatus::Ready, ['chunks_count' => count($chunks)]);
    }
}
```

- [ ] **Step 5: Запустити тести**

Run: `php artisan test --filter='IngestionChainTest|ExtractDocumentTextTest|EmbedDocumentChunksTest'`. Expected: 7 passed. Якщо `Bus::chain` у sync-черзі не виконує наступні job-и, перевірити, що `QUEUE_CONNECTION=sync` у `.env.testing`.

- [ ] **Step 6: Коміт**

```bash
git add -A
git commit -m "feat(knowledge): ingestion chain extract->chunk->embed with runs log and failure codes (AC-1, AC-3)"
```

---

### Task 9: API документів: завантаження, список, перегляд, Policy

**Files:**
- Create: `app/Knowledge/Http/DocumentController.php`, `app/Knowledge/Http/StoreDocumentRequest.php`, `app/Knowledge/Http/DocumentResource.php`, `app/Knowledge/Policies/DocumentPolicy.php`, `app/Knowledge/Ingestion/DocumentUploader.php`
- Modify: `routes/api.php`, `app/Providers/AppServiceProvider.php` (реєстрація Policy через `Gate::policy`)
- Test: `tests/Feature/Knowledge/DocumentApiTest.php`

**Interfaces:**
- Consumes: `DocumentIngestionService::dispatchChain`.
- Produces: `DocumentUploader::upload(UploadedFile $file, string $title, AudienceType $type, ?string $value, User $by): array{document: Document, created: bool}`. Маршрути `GET /documents`, `POST /documents`, `GET /documents/{document}`. `POST /documents/{id}/retry`, `DELETE`, `/chunks` лишаються для плану 2.

- [ ] **Step 1: Тести**

```php
<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Jobs\ExtractDocumentText;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->hr = User::factory()->hrAdmin()->create();
});

it('uploads a markdown document, stores it by sha256 and dispatches the chain (AC-1)', function () {
    Bus::fake();
    $file = UploadedFile::fake()->createWithContent('policy.md', "# Відпустки\n\n24 дні.");

    $res = $this->actingAs($this->hr)->postJson('/api/v1/documents', [
        'file' => $file, 'title' => 'Політика відпусток', 'audience_type' => 'all',
    ]);

    $res->assertStatus(202)->assertJsonPath('data.status', 'uploaded')->assertJsonPath('data.title', 'Політика відпусток');
    $doc = Document::first();
    expect($doc->sha256)->toBe(hash('sha256', "# Відпустки\n\n24 дні."))
        ->and($doc->storage_path)->toBe("knowledge/{$doc->sha256}.md");
    Storage::disk('local')->assertExists($doc->storage_path);
    Bus::assertChained([ExtractDocumentText::class, \App\Knowledge\Jobs\ChunkDocument::class, \App\Knowledge\Jobs\EmbedDocumentChunks::class]);
});

it('returns the existing document with 200 for a duplicate sha256 (AC-2)', function () {
    Bus::fake();
    $file = fn () => UploadedFile::fake()->createWithContent('a.md', 'same content');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $file(), 'title' => 'A', 'audience_type' => 'all'])->assertStatus(202);

    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $file(), 'title' => 'B', 'audience_type' => 'all'])
        ->assertOk()->assertJsonPath('data.title', 'A');

    expect(Document::count())->toBe(1);
    Bus::assertChainedTimes(ExtractDocumentText::class, 1);
});

it('requires audience_value for department and role', function () {
    $file = UploadedFile::fake()->createWithContent('a.md', 'x');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $file, 'title' => 'A', 'audience_type' => 'department'])
        ->assertStatus(422)->assertJsonPath('error.details.audience_value.0', fn ($m) => is_string($m));
});

it('rejects unsupported mime and oversized files', function () {
    $png = UploadedFile::fake()->image('x.png');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $png, 'title' => 'A', 'audience_type' => 'all'])->assertStatus(422);

    $big = UploadedFile::fake()->create('big.pdf', 21 * 1024, 'application/pdf');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $big, 'title' => 'A', 'audience_type' => 'all'])->assertStatus(422);
});

it('forbids employees from knowledge endpoints', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee)->getJson('/api/v1/documents')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    $this->actingAs($employee)->postJson('/api/v1/documents', [])->assertForbidden();
});

it('lists documents with status filter and shows one with ingestion runs', function () {
    Document::factory()->count(2)->create(['uploaded_by' => $this->hr->id]);
    $failed = Document::factory()->status(DocumentStatus::Failed)->create(['uploaded_by' => $this->hr->id]);
    $failed->ingestionRuns()->create(['step' => 'extract', 'status' => 'failed', 'attempt' => 3, 'started_at' => now(), 'finished_at' => now(), 'error' => 'down']);

    $this->actingAs($this->hr)->getJson('/api/v1/documents?status=failed')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->hr)->getJson("/api/v1/documents/{$failed->id}")->assertOk()
        ->assertJsonPath('data.ingestion_runs.0.step', 'extract')
        ->assertJsonPath('data.ingestion_runs.0.error', 'down');
});
```

Run: `php artisan test --filter=DocumentApiTest`. Expected: FAIL.

- [ ] **Step 2: Form Request, Policy, Uploader**

`StoreDocumentRequest::rules()`:
```php
return [
    'file' => ['required', 'file', 'max:'.(int) (config('rag.upload.max_bytes') / 1024), 'mimetypes:'.implode(',', config('rag.upload.mimes'))],
    'title' => ['required', 'string', 'max:255'],
    'audience_type' => ['required', Rule::enum(AudienceType::class)],
    'audience_value' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => in_array($this->input('audience_type'), ['department', 'role'], true))],
];
```
`authorize()` повертає `$this->user()->can('create', Document::class)`. Примітка: `mimetypes` для `.md` файлів часто визначається як `text/plain`, тому обидва значення є в `config('rag.upload.mimes')`; реальний mime для збереження беремо з `$file->getMimeType()`, а розширення з `$file->getClientOriginalExtension()`.

`DocumentPolicy`: `viewAny`, `view`, `create`, `update`, `delete` усі повертають `$user->isHrAdmin()`. Зареєструвати в `AppServiceProvider::boot`: `Gate::policy(Document::class, DocumentPolicy::class);`.

`DocumentUploader`:
```php
final class DocumentUploader
{
    public function __construct(private readonly DocumentIngestionService $ingestion) {}

    /** @return array{document: Document, created: bool} */
    public function upload(UploadedFile $file, string $title, AudienceType $type, ?string $value, User $by): array
    {
        $sha = hash_file('sha256', $file->getRealPath());
        $existing = Document::query()->where('sha256', $sha)->first();
        if ($existing !== null) {
            return ['document' => $existing, 'created' => false];
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = "knowledge/$sha.$ext";
        Storage::disk('local')->putFileAs('knowledge', $file, "$sha.$ext");

        $document = Document::query()->create([
            'title' => $title,
            'original_filename' => $file->getClientOriginalName(),
            'mime' => $this->normalizeMime($file->getMimeType() ?? 'application/octet-stream', $ext),
            'size_bytes' => $file->getSize(),
            'sha256' => $sha,
            'storage_path' => $path,
            'audience_type' => $type,
            'audience_value' => $type === AudienceType::All ? null : $value,
            'status' => DocumentStatus::Uploaded,
            'uploaded_by' => $by->id,
        ]);
        $this->ingestion->dispatchChain($document);

        return ['document' => $document, 'created' => true];
    }

    private function normalizeMime(string $mime, string $ext): string
    {
        return match ($ext) {
            'md', 'markdown' => 'text/markdown',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => $mime,
        };
    }
}
```

- [ ] **Step 3: Контролер, ресурс, маршрути**

`DocumentController`:
```php
final class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Document::class);
        $query = Document::query()->latest('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        return DocumentResource::collection($query->paginate(min((int) $request->query('per_page', 25), 100)));
    }

    public function store(StoreDocumentRequest $request, DocumentUploader $uploader): JsonResponse
    {
        $result = $uploader->upload(
            $request->file('file'), $request->string('title'), AudienceType::from($request->input('audience_type')),
            $request->input('audience_value'), $request->user(),
        );
        return DocumentResource::make($result['document'])->response()->setStatusCode($result['created'] ? 202 : 200);
    }

    public function show(Document $document): DocumentResource
    {
        Gate::authorize('view', $document);
        return DocumentResource::make($document->load('ingestionRuns'));
    }
}
```

`DocumentResource::toArray`: `id, title, original_filename, mime, size_bytes, audience_type, audience_value, status, failure_code, failure_message, version, chunks_count, created_at, updated_at`, і `ingestion_runs => $this->whenLoaded('ingestionRuns', fn () => $this->ingestionRuns->map(fn ($r) => ['step' => $r->step->value, 'status' => $r->status, 'attempt' => $r->attempt, 'started_at' => $r->started_at, 'finished_at' => $r->finished_at, 'error' => $r->error]))`.

Маршрути в групі `auth:sanctum`:
```php
Route::get('documents', [DocumentController::class, 'index']);
Route::post('documents', [DocumentController::class, 'store']);
Route::get('documents/{document}', [DocumentController::class, 'show']);
```

- [ ] **Step 4: Запустити тести**

Run: `php artisan test --filter=DocumentApiTest`. Expected: 6 passed. Якщо тест на 403 для `POST` з порожнім тілом повертає 422, перевірити, що `authorize()` у Form Request спрацьовує до валідації (у Laravel так і є).

- [ ] **Step 5: Коміт**

```bash
git add -A
git commit -m "feat(knowledge): document upload/list/show api with policy and sha256 idempotency (AC-1, AC-2)"
```

---

### Task 10: ChunkSearchRepository і кеш ембедингу запиту

**Files:**
- Create: `app/Retrieval/SearchHit.php`, `app/Retrieval/ChunkSearchRepository.php`, `app/Retrieval/QueryEmbeddingCache.php`
- Test: `tests/Feature/Retrieval/ChunkSearchRepositoryTest.php`, `tests/Feature/Retrieval/QueryEmbeddingCacheTest.php`

**Interfaces:**
- Consumes: `Document::visibilityWhere`, `EmbeddingProvider`, `ChunkWriter` (у тестах для наповнення).
- Produces:
```php
final readonly class SearchHit {
    public function __construct(public int $chunkId, public int $documentId, public string $documentTitle, public ?int $page, public ?string $heading, public string $content, public float $distance) {}
}
final class ChunkSearchRepository {
    /** @param list<float> $queryVector @return list<SearchHit> ordered by distance asc */
    public function search(array $queryVector, User $user, int $topK, int $candidateLimit): array;
}
final class QueryEmbeddingCache {
    public function __construct(EmbeddingProvider $provider) {}
    /** @return list<float> */
    public function embedQuery(string $question): array; // Cache::remember("embed:q:".sha1(normalized), 3600)
}
```

- [ ] **Step 1: Тести репозиторію (негативний тест аудиторії, AC-5 на рівні SQL)**

```php
<?php

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Models\Department;
use App\Knowledge\Models\Document;
use App\Models\User;
use App\Retrieval\ChunkSearchRepository;

function seedChunks(Document $doc, array $texts): void
{
    $fake = app(FakeEmbeddingProvider::class);
    $chunks = [];
    foreach ($texts as $i => $t) {
        $chunks[] = new Chunk($i, 1, null, $t, 10);
    }
    app(ChunkWriter::class)->insertBatch($doc, $chunks, $fake->embedPassages($texts));
}

it('returns nearest chunks ordered by distance with document title', function () {
    $doc = Document::factory()->create(['title' => 'Відпустки']);
    seedChunks($doc, ['скільки днів відпустки має працівник', 'як налаштувати принтер', 'оформлення відпустки через HR']);
    $user = User::factory()->create();
    $q = app(FakeEmbeddingProvider::class)->embedQuery('днів відпустки');

    $hits = app(ChunkSearchRepository::class)->search($q, $user, 2, 100);

    expect($hits)->toHaveCount(2)
        ->and($hits[0]->documentTitle)->toBe('Відпустки')
        ->and($hits[0]->distance)->toBeLessThanOrEqual($hits[1]->distance)
        ->and($hits[0]->content)->toContain('відпустки');
});

it('never returns chunks of documents outside the user audience (AC-5)', function () {
    $eng = Department::factory()->create();
    $mkt = Department::factory()->create();
    $user = User::factory()->inDepartment($eng, 'developer')->create();
    $engDoc = Document::factory()->forDepartment($eng)->create();
    $mktDoc = Document::factory()->forDepartment($mkt)->create();
    $qaDoc = Document::factory()->forRole('qa')->create();
    seedChunks($engDoc, ['секретний план інженерів']);
    seedChunks($mktDoc, ['секретний план маркетингу']);
    seedChunks($qaDoc, ['секретний план тестувальників']);
    $q = app(FakeEmbeddingProvider::class)->embedQuery('секретний план');

    $hits = app(ChunkSearchRepository::class)->search($q, $user, 8, 100);

    expect(array_map(fn ($h) => $h->documentId, $hits))->toBe([$engDoc->id]);
});

it('skips documents that are not ready or soft-deleted', function () {
    $user = User::factory()->create();
    $ready = Document::factory()->create();
    $pending = Document::factory()->status(DocumentStatus::Embedding)->create();
    $deleted = Document::factory()->create();
    seedChunks($ready, ['текст готовий']);
    seedChunks($pending, ['текст готовий']);
    seedChunks($deleted, ['текст готовий']);
    $deleted->delete();
    $q = app(FakeEmbeddingProvider::class)->embedQuery('текст готовий');

    $hits = app(ChunkSearchRepository::class)->search($q, $user, 8, 100);

    expect(array_map(fn ($h) => $h->documentId, $hits))->toBe([$ready->id]);
});

it('returns an empty list when there are no chunks', function () {
    $q = app(FakeEmbeddingProvider::class)->embedQuery('будь-що');
    expect(app(ChunkSearchRepository::class)->search($q, User::factory()->create(), 8, 100))->toBe([]);
});
```

- [ ] **Step 2: Реалізація репозиторію**

```php
<?php

namespace App\Retrieval;

use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only class allowed to run raw SELECTs against document_chunks.
 * MariaDB uses the vector index only for "ORDER BY VEC_DISTANCE_COSINE(...) LIMIT n" with no other
 * predicates, so candidates come from an indexed subquery and audience/status filters apply outside.
 */
final class ChunkSearchRepository
{
    /** @param list<float> $queryVector @return list<SearchHit> */
    public function search(array $queryVector, User $user, int $topK, int $candidateLimit): array
    {
        $candidates = DB::table('document_chunks')
            ->selectRaw('id, VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS distance', [json_encode($queryVector)])
            ->orderBy('distance')
            ->limit(max($candidateLimit, $topK));

        $query = DB::table('document_chunks as c')
            ->joinSub($candidates, 't', 't.id', '=', 'c.id')
            ->join('documents as d', 'd.id', '=', 'c.document_id')
            ->whereNull('d.deleted_at')
            ->where('d.status', 'ready')
            ->select(['c.id', 'c.document_id', 'd.title', 'c.page', 'c.heading', 'c.content', 't.distance'])
            ->orderBy('t.distance')
            ->limit($topK);

        Document::visibilityWhere($query, $user, 'd');

        return array_map(fn (object $row) => new SearchHit(
            (int) $row->id, (int) $row->document_id, (string) $row->title,
            $row->page === null ? null : (int) $row->page, $row->heading, (string) $row->content, (float) $row->distance,
        ), $query->get()->all());
    }
}
```

Run: `php artisan test --filter=ChunkSearchRepositoryTest`. Expected: 4 passed.

- [ ] **Step 3: Кеш ембедингу запиту**

Тест `tests/Feature/Retrieval/QueryEmbeddingCacheTest.php`:
```php
<?php

use App\Knowledge\Contracts\EmbeddingProvider;
use App\Retrieval\QueryEmbeddingCache;

it('embeds once and serves repeated and differently-spaced questions from cache', function () {
    $calls = 0;
    $this->app->bind(EmbeddingProvider::class, fn () => new class($calls) implements EmbeddingProvider {
        public function __construct(private int &$calls) {}
        public function embedPassages(array $texts): array { return []; }
        public function embedQuery(string $text): array { $this->calls++; return array_fill(0, 384, 0.5); }
    });
    $cache = app(QueryEmbeddingCache::class);

    $a = $cache->embedQuery('Скільки днів відпустки?');
    $b = $cache->embedQuery('  скільки  днів відпустки ? ');

    expect($a)->toBe($b)->and($calls)->toBe(1);
});
```

Реалізація:
```php
<?php

namespace App\Retrieval;

use App\Knowledge\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Cache;

final class QueryEmbeddingCache
{
    public function __construct(private readonly EmbeddingProvider $provider) {}

    /** @return list<float> */
    public function embedQuery(string $question): array
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($question))) ?? $question;
        $normalized = trim(preg_replace('/\s+([?!.,])/u', '$1', $normalized) ?? $normalized);

        return Cache::remember('embed:q:'.sha1($normalized), 3600, fn () => $this->provider->embedQuery($question));
    }
}
```
Увага: `CACHE_STORE=array` у тестах, тому кеш живе в межах одного процесу тесту. Цього достатньо.

Run: `php artisan test --filter=QueryEmbeddingCacheTest`. Expected: 1 passed.

- [ ] **Step 4: Коміт**

```bash
git add -A
git commit -m "feat(retrieval): indexed candidate search with audience filter and query embedding cache (AC-5)"
```

---

### Task 11: LlmClient: контракт, промпти v1, Anthropic-реалізація, фейк, snapshot-тест

**Files:**
- Create: `app/Chat/Contracts/LlmClient.php`, `app/Chat/Llm/AnswerResult.php`, `app/Chat/Llm/GroundingResult.php`, `app/Chat/Llm/LlmException.php`, `app/Chat/Llm/PromptLoader.php`, `app/Chat/Llm/PromptBuilder.php`, `app/Chat/Llm/AnthropicLlmClient.php`, `app/Chat/Llm/FakeLlmClient.php`, `resources/prompts/answer.v1.md`, `resources/prompts/rewrite.v1.md`, `resources/prompts/grounding.v1.md`
- Modify: `app/Providers/RagServiceProvider.php`, `tests/TestCase.php`
- Test: `tests/Unit/Chat/PromptSnapshotTest.php`, `tests/Unit/Chat/FakeLlmClientTest.php`

**Interfaces:**
- Consumes: `SearchHit`.
- Produces:
```php
interface LlmClient {
    /** Streams the answer; $onDelta receives each text fragment. */
    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult;
    public function rewriteQuestion(string $system, string $user): string;
    public function checkGrounding(string $system, string $user): GroundingResult;
}
final readonly class AnswerResult { public function __construct(public string $text, public int $inputTokens, public int $outputTokens, public string $stopReason, public string $model) {} }
final class GroundingResult implements StructuredOutputModel { public bool $grounded; public string $reason; }
final class LlmException extends RuntimeException { const UNAVAILABLE='llm_unavailable'; const REFUSAL='llm_refusal'; const TRUNCATED='llm_truncated'; public function __construct(public readonly string $code_, string $detail='') }
final class PromptLoader { public function load(string $name): string; } // reads resources/prompts/{config('rag.prompts.'.$name)}.md
final class PromptBuilder {
    public function __construct(PromptLoader $loader) {}
    /** @param list<SearchHit> $hits @return array{system: string, user: string} */
    public function answer(string $question, array $hits): array;
    /** @param list<array{role: string, content: string}> $history */
    public function rewrite(string $question, array $history): array;
    public function grounding(string $answer, array $hits): array;
}
final class FakeLlmClient implements LlmClient {
    public string $nextAnswer = 'Відповідь [1].'; public ?string $nextRewrite = null; public bool $nextGrounded = true; public ?LlmException $throws = null;
    public array $calls = []; // ['streamAnswer' => [...], ...]
}
```

- [ ] **Step 1: Промпти v1**

`resources/prompts/answer.v1.md`:
```markdown
Ти — наставник для нових співробітників компанії. Відповідаєш лише на основі наданих фрагментів документів компанії.

Правила:
1. Кожне твердження у відповіді підтверджуй маркером джерела у квадратних дужках, наприклад [1] або [2][3]. Використовуй лише номери з наданого списку фрагментів.
2. Якщо фрагменти не містять відповіді на питання, напиши одним реченням, що в базі знань цього немає, і не вигадуй нічого.
3. Відповідай тією мовою, якою поставлено питання.
4. Не додавай дат, чисел чи назв, яких немає у фрагментах.
5. Будь коротким: 2–6 речень, списки лише якщо у фрагменті список.
```

`resources/prompts/rewrite.v1.md`:
```markdown
Переформулюй останнє питання користувача так, щоб воно було самодостатнім без історії розмови. Збережи мову питання. Поверни лише переформульоване питання одним рядком, без пояснень.
```

`resources/prompts/grounding.v1.md`:
```markdown
Перевір, чи кожне твердження у відповіді підтверджується наданими фрагментами. Відповідь вважається grounded, якщо вона не містить фактів, яких немає у фрагментах, або якщо вона чесно каже, що інформації немає. Поверни grounded true/false і коротку причину одним реченням.
```

- [ ] **Step 2: Snapshot-тест збірки промпту і тест фейка**

`tests/Unit/Chat/PromptSnapshotTest.php`:
```php
<?php

use App\Chat\Llm\PromptBuilder;
use App\Chat\Llm\PromptLoader;
use App\Retrieval\SearchHit;

it('builds the answer prompt deterministically', function () {
    $builder = new PromptBuilder(new PromptLoader());
    $hits = [
        new SearchHit(55, 3, 'Політика відпусток', 2, 'Тривалість', 'Кожен працівник має 24 календарні дні відпустки.', 0.12),
        new SearchHit(56, 3, 'Політика відпусток', 3, null, 'Заявку подають за 14 днів.', 0.2),
    ];

    $prompt = $builder->answer('Скільки днів відпустки?', $hits);

    expect($prompt['system'])->toMatchSnapshot()
        ->and($prompt['user'])->toMatchSnapshot();
});

it('builds rewrite and grounding prompts deterministically', function () {
    $builder = new PromptBuilder(new PromptLoader());
    $hits = [new SearchHit(1, 1, 'Док', null, null, 'Текст фрагмента.', 0.1)];

    expect($builder->rewrite('а скільки для нових?', [['role' => 'user', 'content' => 'Скільки днів відпустки?'], ['role' => 'assistant', 'content' => '24 дні [1].']])['user'])->toMatchSnapshot()
        ->and($builder->grounding('24 дні [1].', $hits)['user'])->toMatchSnapshot();
});
```

`tests/Unit/Chat/FakeLlmClientTest.php`:
```php
<?php

use App\Chat\Llm\FakeLlmClient;
use App\Chat\Llm\LlmException;

it('streams the configured answer word by word and records the call', function () {
    $fake = new FakeLlmClient();
    $fake->nextAnswer = 'Один два [1].';
    $deltas = [];

    $result = $fake->streamAnswer('sys', 'usr', fn (string $d) => $deltas[] = $d);

    expect(implode('', $deltas))->toBe('Один два [1].')
        ->and(count($deltas))->toBeGreaterThan(1)
        ->and($result->text)->toBe('Один два [1].')
        ->and($result->stopReason)->toBe('end_turn')
        ->and($fake->calls['streamAnswer'][0]['user'])->toBe('usr');
});

it('throws the configured exception', function () {
    $fake = new FakeLlmClient();
    $fake->throws = new LlmException(LlmException::UNAVAILABLE, 'down');
    expect(fn () => $fake->streamAnswer('s', 'u', fn () => null))->toThrow(LlmException::class);
});
```

Run: `php artisan test --filter='PromptSnapshotTest|FakeLlmClientTest'`. Expected: FAIL.

- [ ] **Step 3: PromptLoader, PromptBuilder**

```php
final class PromptLoader
{
    public function load(string $name): string
    {
        $file = config("rag.prompts.$name");
        $path = resource_path("prompts/$file.md");
        if (! is_file($path)) {
            throw new \RuntimeException("prompt file not found: $path");
        }

        return rtrim(file_get_contents($path));
    }
}
```

```php
final class PromptBuilder
{
    public function __construct(private readonly PromptLoader $loader) {}

    /** @param list<SearchHit> $hits @return array{system: string, user: string} */
    public function answer(string $question, array $hits): array
    {
        return ['system' => $this->loader->load('answer'), 'user' => "Фрагменти:\n".$this->fragments($hits)."\n\nПитання: $question"];
    }

    /** @param list<array{role: string, content: string}> $history @return array{system: string, user: string} */
    public function rewrite(string $question, array $history): array
    {
        $lines = array_map(fn (array $m) => ($m['role'] === 'user' ? 'Користувач' : 'Наставник').': '.$m['content'], $history);

        return ['system' => $this->loader->load('rewrite'), 'user' => "Історія:\n".implode("\n", $lines)."\n\nОстаннє питання: $question"];
    }

    /** @param list<SearchHit> $hits @return array{system: string, user: string} */
    public function grounding(string $answer, array $hits): array
    {
        return ['system' => $this->loader->load('grounding'), 'user' => "Фрагменти:\n".$this->fragments($hits)."\n\nВідповідь:\n$answer"];
    }

    /** @param list<SearchHit> $hits */
    private function fragments(array $hits): string
    {
        $out = [];
        foreach (array_values($hits) as $i => $hit) {
            $where = $hit->documentTitle.($hit->page !== null ? ", стор. {$hit->page}" : '').($hit->heading !== null ? ", «{$hit->heading}»" : '');
            $out[] = '['.($i + 1)."] $where: ".trim($hit->content);
        }

        return implode("\n\n", $out);
    }
}
```

- [ ] **Step 4: AnswerResult, GroundingResult, LlmException, FakeLlmClient**

`GroundingResult`:
```php
use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

final class GroundingResult implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'true if every statement is supported by the fragments or the answer honestly says the info is missing')]
    public bool $grounded;

    #[Constrained(description: 'one sentence why')]
    public string $reason;
}
```

`FakeLlmClient`:
```php
final class FakeLlmClient implements LlmClient
{
    public string $nextAnswer = 'Відповідь на основі документів [1].';
    public ?string $nextRewrite = null;
    public bool $nextGrounded = true;
    public ?LlmException $throws = null;
    /** @var array<string, list<array<string, string>>> */
    public array $calls = [];

    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult
    {
        $this->record('streamAnswer', $system, $user);
        $this->maybeThrow();
        $parts = preg_split('/(?<=\s)/u', $this->nextAnswer) ?: [$this->nextAnswer];
        foreach ($parts as $part) {
            if ($part !== '') {
                $onDelta($part);
            }
        }

        return new AnswerResult($this->nextAnswer, 1000, 50, 'end_turn', 'fake-answer');
    }

    public function rewriteQuestion(string $system, string $user): string
    {
        $this->record('rewriteQuestion', $system, $user);
        $this->maybeThrow();
        if ($this->nextRewrite !== null) {
            return $this->nextRewrite;
        }
        preg_match('/Останнє питання: (.*)$/su', $user, $m);

        return trim($m[1] ?? $user);
    }

    public function checkGrounding(string $system, string $user): GroundingResult
    {
        $this->record('checkGrounding', $system, $user);
        $this->maybeThrow();
        $r = new GroundingResult();
        $r->grounded = $this->nextGrounded;
        $r->reason = 'fake';

        return $r;
    }

    private function record(string $method, string $system, string $user): void
    {
        $this->calls[$method][] = ['system' => $system, 'user' => $user];
    }

    private function maybeThrow(): void
    {
        if ($this->throws !== null) {
            throw $this->throws;
        }
    }
}
```

- [ ] **Step 5: AnthropicLlmClient**

```php
<?php

namespace App\Chat\Llm;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextDelta;
use App\Chat\Contracts\LlmClient;

final class AnthropicLlmClient implements LlmClient
{
    public function __construct(
        private readonly Client $client,
        private readonly string $answerModel,
        private readonly string $helperModel,
    ) {}

    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult
    {
        $text = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $stopReason = 'end_turn';
        try {
            $stream = $this->client->messages->createStream(
                model: $this->answerModel,
                maxTokens: 2048,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $user]],
                outputConfig: ['effort' => 'low'],
            );
            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $inputTokens = $event->message->usage->inputTokens ?? 0;
                } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $text .= $event->delta->text;
                    $onDelta($event->delta->text);
                } elseif ($event instanceof RawMessageDeltaEvent) {
                    $outputTokens = $event->usage->outputTokens;
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                }
            }
        } catch (APIConnectionException|APIStatusException $e) {
            throw new LlmException(LlmException::UNAVAILABLE, $e->getMessage());
        }

        if ($stopReason === 'refusal') {
            throw new LlmException(LlmException::REFUSAL, 'model refused');
        }
        if ($stopReason === 'max_tokens') {
            throw new LlmException(LlmException::TRUNCATED, 'hit max_tokens');
        }

        return new AnswerResult($text, (int) $inputTokens, (int) $outputTokens, (string) $stopReason, $this->answerModel);
    }

    public function rewriteQuestion(string $system, string $user): string
    {
        $message = $this->create($this->helperModel, 256, $system, $user);
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return trim($block->text);
            }
        }
        throw new LlmException(LlmException::UNAVAILABLE, 'no text block in rewrite response');
    }

    public function checkGrounding(string $system, string $user): GroundingResult
    {
        $message = $this->create($this->helperModel, 256, $system, $user, ['format' => GroundingResult::class]);
        $parsed = $message->parsedOutput();
        if (! $parsed instanceof GroundingResult) {
            throw new LlmException(LlmException::UNAVAILABLE, 'unparsable grounding output');
        }

        return $parsed;
    }

    private function create(string $model, int $maxTokens, string $system, string $user, ?array $outputConfig = null)
    {
        try {
            $args = ['model' => $model, 'maxTokens' => $maxTokens, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]];
            if ($outputConfig !== null) {
                $args['outputConfig'] = $outputConfig;
            }
            $message = $this->client->messages->create(...$args);
        } catch (APIConnectionException|APIStatusException $e) {
            throw new LlmException(LlmException::UNAVAILABLE, $e->getMessage());
        }
        if ($message->stopReason === 'refusal') {
            throw new LlmException(LlmException::REFUSAL, 'model refused');
        }

        return $message;
    }
}
```
Якщо `RawMessageDeltaEvent::$delta->stopReason` або `Anthropic\Core\Exceptions\APIConnectionException` не існують під такими іменами у встановленій версії SDK, подивитися `vendor/anthropic-ai/sdk/src/Messages/RawMessageDeltaEvent/Delta.php` і `vendor/anthropic-ai/sdk/src/Core/Exceptions/` і виправити імпорти, не вигадуючи назв.

- [ ] **Step 6: Прив'язки**

`RagServiceProvider::register`:
```php
$this->app->singleton(AnthropicLlmClient::class, fn () => new AnthropicLlmClient(
    new \Anthropic\Client(apiKey: (string) config('rag.anthropic.api_key')),
    config('rag.models.answer'), config('rag.models.helper'),
));
$this->app->bind(LlmClient::class, AnthropicLlmClient::class);
```
`tests/TestCase.php::bindFakes()` додати:
```php
$this->app->singleton(FakeLlmClient::class);
$this->app->bind(LlmClient::class, FakeLlmClient::class);
```

- [ ] **Step 7: Запустити тести, зафіксувати snapshots**

Run: `php artisan test --filter='PromptSnapshotTest|FakeLlmClientTest'`. Expected: перший запуск створює snapshots у `tests/.pest/snapshots/`, далі PASS. Відкрити snapshot-файли й прочитати, що промпт зібрався як очікувалось. Закомітити snapshots.

- [ ] **Step 8: Коміт**

```bash
git add -A
git commit -m "feat(chat): llm client contract, anthropic streaming client, fake, prompts v1 with snapshots"
```

---

### Task 12: CitationParser

**Files:**
- Create: `app/Chat/CitationParser.php`, `app/Chat/ParsedCitation.php`
- Test: `tests/Unit/Chat/CitationParserTest.php`

**Interfaces:**
- Consumes: `SearchHit`.
- Produces:
```php
final readonly class ParsedCitation { public function __construct(public int $marker, public SearchHit $hit) {} }
final class CitationParser {
    /** @param list<SearchHit> $hits @return array{citations: list<ParsedCitation>, invalidMarkers: list<int>} unique markers in order of first appearance */
    public function parse(string $answer, array $hits): array;
}
```

- [ ] **Step 1: Тести (включно з маркером поза діапазоном, Review Focus 3)**

```php
<?php

use App\Chat\CitationParser;
use App\Retrieval\SearchHit;

function hits(int $n): array
{
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $out[] = new SearchHit($i * 10, 1, 'Док', null, null, "фрагмент $i", 0.1 * $i);
    }
    return $out;
}

it('maps markers to hits in order of first appearance without duplicates', function () {
    $r = (new CitationParser())->parse('Так [2]. Також [1][2], і ще [1].', hits(3));

    expect(array_map(fn ($c) => [$c->marker, $c->hit->chunkId], $r['citations']))->toBe([[2, 20], [1, 10]])
        ->and($r['invalidMarkers'])->toBe([]);
});

it('drops out-of-range and zero markers and reports them', function () {
    $r = (new CitationParser())->parse('Текст [9] і [0] і [1].', hits(2));

    expect(array_map(fn ($c) => $c->marker, $r['citations']))->toBe([1])
        ->and($r['invalidMarkers'])->toBe([9, 0]);
});

it('returns nothing for an answer without markers', function () {
    $r = (new CitationParser())->parse('У базі знань цього немає.', hits(2));
    expect($r['citations'])->toBe([])->and($r['invalidMarkers'])->toBe([]);
});
```

- [ ] **Step 2: Реалізація**

```php
final class CitationParser
{
    public function parse(string $answer, array $hits): array
    {
        preg_match_all('/\[(\d{1,2})\]/u', $answer, $m);
        $seen = [];
        $citations = [];
        $invalid = [];
        foreach ($m[1] as $raw) {
            $n = (int) $raw;
            if ($n < 1 || $n > count($hits)) {
                if (! in_array($n, $invalid, true)) {
                    $invalid[] = $n;
                }
                continue;
            }
            if (isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            $citations[] = new ParsedCitation($n, $hits[$n - 1]);
        }

        return ['citations' => $citations, 'invalidMarkers' => $invalid];
    }
}
```

Run: `php artisan test --filter=CitationParserTest`. Expected: 3 passed.

- [ ] **Step 3: Коміт**

```bash
git add -A
git commit -m "feat(chat): citation parser with out-of-range marker handling (AC-6)"
```

---

### Task 13: AnswerService, SSE, прогалини, ендпоінти розмов і повідомлень

**Files:**
- Create: `app/Insights/QuestionNormalizer.php`, `app/Insights/KnowledgeGapRecorder.php`, `app/Chat/SseWriter.php`, `app/Chat/AnswerService.php`, `app/Chat/Http/ConversationController.php`, `app/Chat/Http/MessageController.php`, `app/Chat/Http/StoreMessageRequest.php`, `app/Chat/Http/ConversationResource.php`, `app/Chat/Http/MessageResource.php`, `app/Chat/Policies/ConversationPolicy.php`
- Modify: `routes/api.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Insights/QuestionNormalizerTest.php`, `tests/Feature/Chat/AnswerFlowTest.php`, `tests/Feature/Chat/ConversationApiTest.php`

**Interfaces:**
- Consumes: `QueryEmbeddingCache`, `ChunkSearchRepository`, `PromptBuilder`, `LlmClient`, `CitationParser`, моделі Chat.
- Produces:
```php
final class QuestionNormalizer { public function normalize(string $q): string; } // lower, collapse spaces, strip trailing punctuation, max 255
final class KnowledgeGapRecorder {
    public function recordNoAnswer(string $question): KnowledgeGap;      // upsert status open, occurrences++
    public function recordNeedsReview(string $question): KnowledgeGap;   // upsert status needs_review (does not downgrade)
}
final class SseWriter {
    public function __construct(callable $emit) {}                      // $emit(string $rawFrame)
    public function event(string $name, array $data): void;             // "event: name\ndata: json\n\n"
}
final class AnswerService {
    /** Runs the whole flow and writes SSE events through $sse. Never throws; errors become an `error` event. */
    public function answer(Conversation $conversation, string $content, SseWriter $sse): Message;
}
```
Маршрути: `GET/POST /conversations`, `GET /conversations/{conversation}/messages`, `POST /conversations/{conversation}/messages` (SSE, `throttle:chat` = 20/хв), `GET /chunks/{id}`.

- [ ] **Step 1: Unit-тест нормалізатора**

```php
<?php

use App\Insights\QuestionNormalizer;

it('normalizes case, whitespace and trailing punctuation', function () {
    $n = new QuestionNormalizer();
    expect($n->normalize('  Скільки  ДНІВ відпустки ?? '))->toBe('скільки днів відпустки')
        ->and($n->normalize('Що з лікарняними.'))->toBe('що з лікарняними')
        ->and(mb_strlen($n->normalize(str_repeat('а', 400))))->toBe(255);
});
```
Реалізація: `mb_strtolower(trim)`, `preg_replace('/\s+/u', ' ')`, `preg_replace('/[\s?!.,;:…]+$/u', '')`, `mb_substr(..., 0, 255)`.

- [ ] **Step 2: Feature-тести потоку відповіді (AC-6, AC-7, AC-8, Review Focus 3, 4)**

`tests/Feature/Chat/AnswerFlowTest.php`:
```php
<?php

use App\Chat\Enums\MessageStatus;
use App\Chat\Llm\FakeLlmClient;
use App\Chat\Llm\LlmException;
use App\Chat\Models\Conversation;
use App\Chat\Models\Message;
use App\Insights\Models\KnowledgeGap;
use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Models\Document;
use App\Models\User;

function seedKnowledge(array $texts, string $title = 'Політика відпусток'): Document
{
    $doc = Document::factory()->create(['title' => $title]);
    $chunks = [];
    foreach ($texts as $i => $t) {
        $chunks[] = new Chunk($i, 1, null, $t, 10);
    }
    app(ChunkWriter::class)->insertBatch($doc, $chunks, app(FakeEmbeddingProvider::class)->embedPassages($texts));
    return $doc;
}

/** @return list<array{event: string, data: array}> */
function sseEvents(string $body): array
{
    $events = [];
    foreach (preg_split("/\n\n+/", trim($body)) as $frame) {
        if (! preg_match('/^event: (\w+)\ndata: (.*)$/s', $frame, $m)) {
            continue;
        }
        $events[] = ['event' => $m[1], 'data' => json_decode($m[2], true)];
    }
    return $events;
}

beforeEach(function () {
    // FakeEmbeddingProvider is a bag-of-words vector, so related texts land around distance 0.3-0.5.
    config(['rag.max_distance' => 0.6]);
    $this->user = User::factory()->create();
    $this->conversation = Conversation::factory()->create(['user_id' => $this->user->id]);
});

it('streams message, tokens, citations and done, and persists citations (AC-6, AC-8)', function () {
    seedKnowledge(['Скільки днів відпустки має працівник: 24 дні.', 'Заявку подають за 14 днів.']);
    app(FakeLlmClient::class)->nextAnswer = 'Працівник має 24 дні відпустки [1]. Заявка за 14 днів [2].';

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'Скільки днів відпустки?']);

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    $events = sseEvents($response->streamedContent());
    $names = array_map(fn ($e) => $e['event'], $events);

    expect($names[0])->toBe('message')
        ->and($names[1])->toBe('token')
        ->and(array_slice($names, -2))->toBe(['citations', 'done'])
        ->and(implode('', array_map(fn ($e) => $e['data']['text'], array_filter($events, fn ($e) => $e['event'] === 'token'))))
            ->toBe('Працівник має 24 дні відпустки [1]. Заявка за 14 днів [2].');

    $citations = $events[count($events) - 2]['data'];
    expect($citations)->toHaveCount(2)->and($citations[0]['marker'])->toBe(1)->and($citations[0]['document_title'])->toBe('Політика відпусток');

    $assistant = Message::where('role', 'assistant')->first();
    expect($assistant->status)->toBe(MessageStatus::Completed)
        ->and($assistant->grounded)->toBeTrue()
        ->and($assistant->citations)->toHaveCount(2)
        ->and($assistant->citations[0]->chunk_id)->toBe($citations[0]['chunk_id'])
        ->and($assistant->input_tokens)->toBe(1000)
        ->and($events[count($events) - 1]['data']['status'])->toBe('completed');
    expect(Message::where('role', 'user')->value('content'))->toBe('Скільки днів відпустки?');
});

it('returns no_answer without calling the answer model when nothing is relevant (AC-7)', function () {
    seedKnowledge(['Налаштування принтера на четвертому поверсі.']);
    config(['rag.max_distance' => 0.2]);

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'Скільки днів відпустки?']);

    $events = sseEvents($response->streamedContent());
    expect(array_map(fn ($e) => $e['event'], $events))->toBe(['message', 'token', 'citations', 'done'])
        ->and($events[3]['data']['status'])->toBe('no_answer')
        ->and(app(FakeLlmClient::class)->calls)->not->toHaveKey('streamAnswer');
    expect(Message::where('role', 'assistant')->value('status'))->toBe(MessageStatus::NoAnswer->value);
    $gap = KnowledgeGap::first();
    expect($gap->question_normalized)->toBe('скільки днів відпустки')->and($gap->occurrences)->toBe(1)->and($gap->status)->toBe('open');

    $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'скільки днів відпустки']);
    expect(KnowledgeGap::count())->toBe(1)->and(KnowledgeGap::first()->occurrences)->toBe(2);
});

it('also returns no_answer when there are no chunks at all', function () {
    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'Є питання?']);
    expect(end(sseEvents($response->streamedContent()))['data']['status'])->toBe('no_answer');
});

it('drops out-of-range markers and marks not grounded when no valid citation remains', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    app(FakeLlmClient::class)->nextAnswer = 'Відпустка 30 днів [7].';

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник']);

    $events = sseEvents($response->streamedContent());
    $citations = $events[count($events) - 2]['data'];
    expect($citations)->toBe([]);
    $assistant = Message::where('role', 'assistant')->first();
    expect($assistant->grounded)->toBeFalse()->and($assistant->citations)->toHaveCount(0);
    expect(KnowledgeGap::first()->status)->toBe('needs_review');
});

it('records needs_review when the grounding check fails', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    $fake = app(FakeLlmClient::class);
    $fake->nextAnswer = 'Відпустка 24 дні [1].';
    $fake->nextGrounded = false;

    $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник']);

    expect(Message::where('role', 'assistant')->value('grounded'))->toBe(0)
        ->and(KnowledgeGap::first()->status)->toBe('needs_review');
});

it('emits an error event and marks the message failed when the llm is unavailable', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    app(FakeLlmClient::class)->throws = new LlmException(LlmException::UNAVAILABLE, 'down');

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник']);

    $events = sseEvents($response->streamedContent());
    expect(array_map(fn ($e) => $e['event'], $events))->toBe(['message', 'error'])
        ->and($events[1]['data']['code'])->toBe('llm_unavailable');
    expect(Message::where('role', 'assistant')->value('status'))->toBe(MessageStatus::Failed->value);
});

it('rewrites the question with history on the second turn', function () {
    seedKnowledge(['Нові працівники отримують відпустку після 6 місяців.']);
    $this->conversation->messages()->create(['role' => 'user', 'content' => 'Скільки днів відпустки?', 'status' => 'completed']);
    $this->conversation->messages()->create(['role' => 'assistant', 'content' => '24 дні [1].', 'status' => 'completed']);
    $fake = app(FakeLlmClient::class);
    $fake->nextRewrite = 'Коли нові працівники отримують відпустку?';

    $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'а для нових?']);

    expect($fake->calls)->toHaveKey('rewriteQuestion')
        ->and(Message::where('role', 'assistant')->latest('id')->value('rewritten_question'))->toBe('Коли нові працівники отримують відпустку?')
        ->and($fake->calls['streamAnswer'][0]['user'])->toContain('Коли нові працівники отримують відпустку?');
});

it('validates content length and does not create messages or call the llm', function () {
    $this->actingAs($this->user)->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => ''])->assertStatus(422);
    $this->actingAs($this->user)->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => str_repeat('a', 2001)])->assertStatus(422);

    expect(Message::count())->toBe(0)->and(app(FakeLlmClient::class)->calls)->toBe([]);
});

it('forbids posting into another user conversation', function () {
    $other = Conversation::factory()->create();
    $this->actingAs($this->user)->postJson("/api/v1/conversations/{$other->id}/messages", ['content' => 'hi'])->assertForbidden();
});
```

`tests/Feature/Chat/ConversationApiTest.php`:
```php
<?php

use App\Chat\Models\Conversation;
use App\Models\User;

it('creates, lists and reads conversations with messages and citations', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/v1/conversations')->assertCreated()->assertJsonPath('data.title', null);
    $conv = Conversation::first();
    $msg = $conv->messages()->create(['role' => 'assistant', 'content' => 'x [1]', 'status' => 'completed']);
    $msg->citations()->create(['marker' => 1, 'chunk_id' => null, 'quote' => 'цитата']);

    $this->actingAs($user)->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user)->getJson("/api/v1/conversations/{$conv->id}/messages")->assertOk()
        ->assertJsonPath('data.0.citations.0.quote', 'цитата')
        ->assertJsonPath('data.0.citations.0.chunk_id', null);
});

it('hides other users conversations', function () {
    $user = User::factory()->create();
    $foreign = Conversation::factory()->create();
    $this->actingAs($user)->getJson("/api/v1/conversations/{$foreign->id}/messages")->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
});
```

Run: `php artisan test --filter='QuestionNormalizerTest|AnswerFlowTest|ConversationApiTest'`. Expected: FAIL.

- [ ] **Step 3: KnowledgeGapRecorder, SseWriter**

```php
final class KnowledgeGapRecorder
{
    public function __construct(private readonly QuestionNormalizer $normalizer) {}

    public function recordNoAnswer(string $question): KnowledgeGap
    {
        return $this->upsert($question, 'open');
    }

    public function recordNeedsReview(string $question): KnowledgeGap
    {
        return $this->upsert($question, 'needs_review');
    }

    private function upsert(string $question, string $status): KnowledgeGap
    {
        $normalized = $this->normalizer->normalize($question);

        return DB::transaction(function () use ($question, $normalized, $status) {
            $gap = KnowledgeGap::query()->where('question_normalized', $normalized)->lockForUpdate()->first();
            if ($gap === null) {
                return KnowledgeGap::query()->create([
                    'question_normalized' => $normalized, 'question_example' => mb_substr($question, 0, 2000),
                    'occurrences' => 1, 'status' => $status, 'last_asked_at' => now(),
                ]);
            }
            $gap->occurrences++;
            $gap->last_asked_at = now();
            if ($gap->status === 'open' && $status === 'needs_review') {
                $gap->status = 'needs_review';
            }
            $gap->save();

            return $gap;
        });
    }
}
```
Статус `resolved` не перезаписується: якщо прогалину закрили документом, а питання знову без відповіді, лічильник зростає, а HR побачить це у плані 2 через `last_asked_at > updated_at`.

```php
final class SseWriter
{
    /** @var callable(string): void */
    private $emit;

    public function __construct(callable $emit)
    {
        $this->emit = $emit;
    }

    public function event(string $name, array $data): void
    {
        ($this->emit)("event: $name\ndata: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n");
    }
}
```

- [ ] **Step 4: AnswerService**

```php
<?php

namespace App\Chat;

use App\Chat\Contracts\LlmClient;
use App\Chat\Enums\MessageStatus;
use App\Chat\Llm\LlmException;
use App\Chat\Llm\PromptBuilder;
use App\Chat\Models\Conversation;
use App\Chat\Models\Message;
use App\Insights\KnowledgeGapRecorder;
use App\Knowledge\Embedding\EmbeddingException;
use App\Retrieval\ChunkSearchRepository;
use App\Retrieval\QueryEmbeddingCache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AnswerService
{
    public const NO_ANSWER_TEXT = 'У базі знань компанії немає інформації з цього питання. Я передав його HR, щоб доповнити документи.';

    public function __construct(
        private readonly QueryEmbeddingCache $embeddings,
        private readonly ChunkSearchRepository $search,
        private readonly PromptBuilder $prompts,
        private readonly LlmClient $llm,
        private readonly CitationParser $citations,
        private readonly KnowledgeGapRecorder $gaps,
    ) {}

    public function answer(Conversation $conversation, string $content, SseWriter $sse): Message
    {
        $startedAt = hrtime(true);
        $history = $conversation->messages()->whereIn('status', ['completed', 'no_answer'])->latest('id')->limit(6)->get()->reverse()
            ->map(fn (Message $m) => ['role' => $m->role, 'content' => $m->content])->values()->all();

        $conversation->messages()->create(['role' => 'user', 'content' => $content, 'status' => MessageStatus::Completed]);
        $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => '', 'status' => MessageStatus::Streaming]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $sse->event('message', ['id' => $assistant->id, 'conversation_id' => $conversation->id]);

        try {
            $question = $content;
            if ($history !== []) {
                $rewrite = $this->prompts->rewrite($content, $history);
                $question = $this->llm->rewriteQuestion($rewrite['system'], $rewrite['user']);
                $assistant->rewritten_question = $question;
            }

            $vector = $this->embeddings->embedQuery($question);
            $hits = $this->search->search($vector, $conversation->user, (int) config('rag.top_k'), (int) config('rag.candidate_limit'));
            $best = $hits === [] ? null : $hits[0]->distance;
            $assistant->best_distance = $best;

            if ($best === null || $best > (float) config('rag.max_distance')) {
                $sse->event('token', ['text' => self::NO_ANSWER_TEXT]);
                $sse->event('citations', []);
                $assistant->forceFill(['content' => self::NO_ANSWER_TEXT, 'status' => MessageStatus::NoAnswer, 'latency_ms' => $this->ms($startedAt)])->save();
                $this->gaps->recordNoAnswer($question);
                $sse->event('done', ['status' => 'no_answer', 'grounded' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'latency_ms' => $assistant->latency_ms]);

                return $assistant;
            }

            $prompt = $this->prompts->answer($question, $hits);
            $result = $this->llm->streamAnswer($prompt['system'], $prompt['user'], fn (string $delta) => $sse->event('token', ['text' => $delta]));

            $parsed = $this->citations->parse($result->text, $hits);
            if ($parsed['invalidMarkers'] !== []) {
                Log::warning('rag.invalid_citation_markers', ['message_id' => $assistant->id, 'markers' => $parsed['invalidMarkers']]);
            }
            $payload = [];
            foreach ($parsed['citations'] as $c) {
                $assistant->citations()->create(['marker' => $c->marker, 'chunk_id' => $c->hit->chunkId, 'quote' => mb_substr($c->hit->content, 0, 500)]);
                $payload[] = ['marker' => $c->marker, 'chunk_id' => $c->hit->chunkId, 'document_id' => $c->hit->documentId,
                    'document_title' => $c->hit->documentTitle, 'page' => $c->hit->page, 'quote' => mb_substr($c->hit->content, 0, 500)];
            }
            $sse->event('citations', $payload);

            $grounded = $parsed['citations'] !== [];
            if ($grounded) {
                $g = $this->prompts->grounding($result->text, $hits);
                $grounded = $this->llm->checkGrounding($g['system'], $g['user'])->grounded;
            }
            if (! $grounded) {
                $this->gaps->recordNeedsReview($question);
            }

            $assistant->forceFill([
                'content' => $result->text, 'status' => MessageStatus::Completed, 'grounded' => $grounded, 'model' => $result->model,
                'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'latency_ms' => $this->ms($startedAt),
            ])->save();
            $sse->event('done', ['status' => 'completed', 'grounded' => $grounded, 'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens, 'latency_ms' => $assistant->latency_ms]);
        } catch (LlmException|EmbeddingException $e) {
            $this->fail($assistant, $sse, $e->code_, $e->getMessage(), $startedAt);
        } catch (Throwable $e) {
            Log::error('rag.answer_failed', ['message_id' => $assistant->id, 'exception' => $e]);
            $this->fail($assistant, $sse, 'internal_error', 'Внутрішня помилка.', $startedAt);
        }

        return $assistant;
    }

    private function fail(Message $assistant, SseWriter $sse, string $code, string $detail, int $startedAt): void
    {
        $assistant->forceFill(['status' => MessageStatus::Failed, 'latency_ms' => $this->ms($startedAt)])->save();
        $sse->event('error', ['code' => $code, 'message' => $this->userMessage($code), 'detail' => mb_substr($detail, 0, 300)]);
    }

    private function userMessage(string $code): string
    {
        return match ($code) {
            'llm_unavailable', 'embedder_unavailable', 'embedder_bad_dimension' => 'Сервіс тимчасово недоступний, спробуйте ще раз за хвилину.',
            'llm_refusal' => 'Модель відмовилась відповідати на це питання.',
            'llm_truncated' => 'Відповідь обірвалась, спробуйте уточнити питання.',
            default => 'Щось пішло не так. Ми вже дивимось.',
        };
    }

    private function ms(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
```
Поведінка «обрив клієнтом» зі спеки (розділ 7.2) реалізується в контролері: якщо `connection_aborted()` стає істинним під час стріму, SseWriter лише перестає писати, а сервіс дописує повідомлення як є. Для плану 1 це покривається тим, що `fail` не викликається, а `done` просто не доходить. Повну обробку з позначкою `client_disconnected` робить план 2 разом з історією розмов в UI.

- [ ] **Step 5: Контролери, Policy, Form Request, ресурси, маршрути**

`ConversationPolicy::view/update(User $user, Conversation $c)`: `$c->user_id === $user->id`. Зареєструвати в `AppServiceProvider`.

`StoreMessageRequest::rules()`: `['content' => ['required', 'string', 'min:1', 'max:2000']]`; `authorize()`: `$this->user()->can('update', $this->route('conversation'))`.

`ConversationController`: `index` (розмови `where user_id` за `last_message_at desc`, пагінація), `store` (створити порожню, 201), `messages(Conversation)` (authorize view, `messages()->with('citations.chunk.document')->orderBy('id')`, `MessageResource::collection`).

`MessageResource::toArray`: `id, role, content, status, grounded, rewritten_question, latency_ms, created_at, citations => map(marker, chunk_id, quote, document_id => chunk?->document_id, document_title => chunk?->document?->title, page => chunk?->page, deleted => chunk === null)`.

`MessageController::store`:
```php
public function store(StoreMessageRequest $request, Conversation $conversation, AnswerService $service): StreamedResponse
{
    $content = $request->string('content')->toString();

    return response()->stream(function () use ($conversation, $content, $service) {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
        }
        $writer = new SseWriter(function (string $frame) {
            if (connection_aborted()) {
                return;
            }
            echo $frame;
            flush();
        });
        $service->answer($conversation, $content, $writer);
    }, 200, [
        'Content-Type' => 'text/event-stream; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-transform',
        'X-Accel-Buffering' => 'no',
        'Connection' => 'keep-alive',
    ]);
}
```
Увага: у тестах `ob_end_flush` на порожньому стеку буферів не викликається, бо `ob_get_level()` повертає 0 після того, як Laravel зібрав відповідь; якщо тест падає з «failed to delete buffer», замінити `ob_end_flush()` на `@ob_end_flush()`.

Rate limiter у `AppServiceProvider::boot`:
```php
RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(20)->by((string) $request->user()?->id));
```

Маршрути (у групі `auth:sanctum`):
```php
Route::get('conversations', [ConversationController::class, 'index']);
Route::post('conversations', [ConversationController::class, 'store']);
Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:chat');
Route::get('chunks/{id}', [ChunkController::class, 'show']);
```

`App\Knowledge\Http\ChunkController::show(Request $request, int $id)`: `DB::table('document_chunks as c')->join('documents as d', ...)->whereNull('d.deleted_at')->where('c.id', $id)` + `Document::visibilityWhere($query, $request->user(), 'd')` + `select(c.id, c.document_id, d.title, c.page, c.heading, c.content)`; 404, якщо немає. Так, це третій raw SELECT до чанків; щоб не порушити правило «raw SELECT лише в репозиторії», додати в `ChunkSearchRepository` метод `findVisible(int $chunkId, User $user): ?SearchHit` (distance = 0.0) і викликати його з контролера. Тест на `GET /chunks/{id}` для чужої аудиторії додає план 2 (AC-5 UI), але метод писати тут.

- [ ] **Step 6: Запустити тести**

Run: `php artisan test --filter='QuestionNormalizerTest|AnswerFlowTest|ConversationApiTest'`. Expected: 13 passed. Потім повний прогін `php artisan test`: усе зелене. `vendor/bin/pint`, `vendor/bin/phpstan analyse`.

- [ ] **Step 7: Коміт**

```bash
git add -A
git commit -m "feat(chat): answer service with sse streaming, citations, grounding and knowledge gaps (AC-6, AC-7, AC-8)"
```

---

### Task 14: Vue 3 SPA: вхід і чат зі стрімінгом та цитатами

**Files:**
- Create: `resources/js/app.js`, `resources/js/router.js`, `resources/js/api.js`, `resources/js/sse.js`, `resources/js/stores/auth.js`, `resources/js/stores/chat.js`, `resources/js/views/LoginView.vue`, `resources/js/views/ChatView.vue`, `resources/js/components/MessageBubble.vue`, `resources/js/components/CitationPanel.vue`, `resources/js/App.vue`, `resources/views/app.blade.php`, `vitest.config.js`
- Modify: `package.json`, `vite.config.js`, `routes/web.php`
- Test: `resources/js/sse.test.js`, `resources/js/stores/chat.test.js`

**Interfaces:**
- Consumes: API розділу 8 (login, me, conversations, messages SSE, chunks).
- Produces: `parseSseChunk(buffer: string): {events: Array<{event: string, data: any}>, rest: string}`; `streamMessage(conversationId, content, handlers: {onEvent})`; Pinia store `useChatStore` з `conversations, current, messages, streaming, error, loadConversations(), openConversation(id), newConversation(), send(content)`.

- [ ] **Step 1: Залежності й конфіг**

```bash
npm install vue@^3.5 vue-router@^4 pinia@^3 vuetify@^3.7 @mdi/font axios
npm install -D @vitejs/plugin-vue vite-plugin-vuetify vitest @vue/test-utils jsdom
```
`vite.config.js`:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

export default defineConfig({
  plugins: [laravel({ input: ['resources/js/app.js'], refresh: true }), vue(), vuetify({ autoImport: true })],
  server: { host: 'localhost', port: 5173 },
});
```
`vitest.config.js`: `export default { test: { environment: 'jsdom', include: ['resources/js/**/*.test.js'] } }`. У `package.json` scripts: `"test": "vitest run"`.

`routes/web.php`: `Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum).*$');`
`resources/views/app.blade.php`: мінімальний HTML з `<div id="app"></div>` і `@vite('resources/js/app.js')`, `<meta name="csrf-token" content="{{ csrf_token() }}">`.

- [ ] **Step 2: Тест парсера SSE (падає)**

`resources/js/sse.test.js`:
```js
import { describe, it, expect } from 'vitest';
import { parseSseChunk } from './sse.js';

describe('parseSseChunk', () => {
  it('parses complete frames and keeps the incomplete tail', () => {
    const input = 'event: message\ndata: {"id":1}\n\nevent: token\ndata: {"text":"Прив"}\n\nevent: tok';
    const { events, rest } = parseSseChunk(input);
    expect(events).toEqual([{ event: 'message', data: { id: 1 } }, { event: 'token', data: { text: 'Прив' } }]);
    expect(rest).toBe('event: tok');
  });

  it('returns no events for an empty buffer', () => {
    expect(parseSseChunk('')).toEqual({ events: [], rest: '' });
  });
});
```

- [ ] **Step 3: api.js і sse.js**

`resources/js/api.js`:
```js
import axios from 'axios';

export const http = axios.create({ baseURL: '/api/v1', withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json' } });

export async function csrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export function errorMessage(err) {
  return err?.response?.data?.error?.message ?? 'Щось пішло не так.';
}
```

`resources/js/sse.js`:
```js
export function parseSseChunk(buffer) {
  const events = [];
  let rest = buffer;
  let idx;
  while ((idx = rest.indexOf('\n\n')) !== -1) {
    const frame = rest.slice(0, idx);
    rest = rest.slice(idx + 2);
    const m = frame.match(/^event: (\w+)\ndata: ([\s\S]*)$/);
    if (m) events.push({ event: m[1], data: JSON.parse(m[2]) });
  }
  return { events, rest };
}

function xsrfToken() {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

export async function streamMessage(conversationId, content, { onEvent }) {
  const res = await fetch(`/api/v1/conversations/${conversationId}/messages`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-XSRF-TOKEN': xsrfToken() },
    body: JSON.stringify({ content }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => null);
    throw new Error(body?.error?.message ?? `HTTP ${res.status}`);
  }
  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    const parsed = parseSseChunk(buffer);
    buffer = parsed.rest;
    parsed.events.forEach(onEvent);
  }
}
```

Run: `npm run test`. Expected: sse.test.js PASS.

- [ ] **Step 4: Тест стора чату (падає)**

`resources/js/stores/chat.test.js`:
```js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('../api.js', () => ({ http: { get: vi.fn(), post: vi.fn() }, errorMessage: (e) => e.message }));
vi.mock('../sse.js', () => ({ streamMessage: vi.fn() }));

import { http } from '../api.js';
import { streamMessage } from '../sse.js';
import { useChatStore } from './chat.js';

beforeEach(() => setActivePinia(createPinia()));

describe('chat store send', () => {
  it('appends user message, streams assistant text, then citations and done', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 7, title: null } } });
    streamMessage.mockImplementation(async (_id, _content, { onEvent }) => {
      onEvent({ event: 'message', data: { id: 42, conversation_id: 7 } });
      onEvent({ event: 'token', data: { text: 'Так ' } });
      onEvent({ event: 'token', data: { text: '[1].' } });
      onEvent({ event: 'citations', data: [{ marker: 1, chunk_id: 5, document_title: 'Док', page: 2, quote: 'цитата' }] });
      onEvent({ event: 'done', data: { status: 'completed', grounded: true } });
    });
    const store = useChatStore();
    await store.newConversation();
    await store.send('Питання?');

    expect(store.messages.map((m) => m.role)).toEqual(['user', 'assistant']);
    expect(store.messages[1].content).toBe('Так [1].');
    expect(store.messages[1].citations[0].document_title).toBe('Док');
    expect(store.messages[1].status).toBe('completed');
    expect(store.streaming).toBe(false);
  });

  it('marks the assistant message failed on error event', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 7 } } });
    streamMessage.mockImplementation(async (_i, _c, { onEvent }) => {
      onEvent({ event: 'message', data: { id: 1 } });
      onEvent({ event: 'error', data: { code: 'llm_unavailable', message: 'Сервіс недоступний' } });
    });
    const store = useChatStore();
    await store.newConversation();
    await store.send('x');

    expect(store.messages[1].status).toBe('failed');
    expect(store.error).toBe('Сервіс недоступний');
  });
});
```

- [ ] **Step 5: Стори**

`resources/js/stores/auth.js`:
```js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http, csrf, errorMessage } from '../api.js';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const error = ref(null);

  async function fetchMe() {
    try { user.value = (await http.get('/me')).data.data; } catch { user.value = null; }
    return user.value;
  }
  async function login(email, password) {
    error.value = null;
    try {
      await csrf();
      await http.post('/auth/login', { email, password });
      await fetchMe();
      return true;
    } catch (e) { error.value = errorMessage(e); return false; }
  }
  async function logout() { await http.post('/auth/logout'); user.value = null; }

  return { user, error, fetchMe, login, logout };
});
```

`resources/js/stores/chat.js`:
```js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http, errorMessage } from '../api.js';
import { streamMessage } from '../sse.js';

export const useChatStore = defineStore('chat', () => {
  const conversations = ref([]);
  const current = ref(null);
  const messages = ref([]);
  const streaming = ref(false);
  const error = ref(null);

  async function loadConversations() {
    conversations.value = (await http.get('/conversations')).data.data;
  }
  async function openConversation(id) {
    current.value = conversations.value.find((c) => c.id === id) ?? { id };
    messages.value = (await http.get(`/conversations/${id}/messages`)).data.data;
  }
  async function newConversation() {
    const { data } = await http.post('/conversations');
    current.value = data.data;
    conversations.value.unshift(data.data);
    messages.value = [];
  }
  async function send(content) {
    if (!current.value) await newConversation();
    error.value = null;
    streaming.value = true;
    messages.value.push({ role: 'user', content, status: 'completed', citations: [] });
    const assistant = { id: null, role: 'assistant', content: '', status: 'streaming', citations: [], grounded: null };
    messages.value.push(assistant);
    try {
      await streamMessage(current.value.id, content, {
        onEvent: ({ event, data }) => {
          if (event === 'message') assistant.id = data.id;
          else if (event === 'token') assistant.content += data.text;
          else if (event === 'citations') assistant.citations = data;
          else if (event === 'done') { assistant.status = data.status; assistant.grounded = data.grounded; }
          else if (event === 'error') { assistant.status = 'failed'; error.value = data.message; }
        },
      });
    } catch (e) {
      assistant.status = 'failed';
      error.value = errorMessage(e);
    } finally {
      streaming.value = false;
    }
  }

  return { conversations, current, messages, streaming, error, loadConversations, openConversation, newConversation, send };
});
```

Run: `npm run test`. Expected: 4 tests PASS.

- [ ] **Step 6: app.js, router, App.vue, views, components**

`resources/js/app.js`:
```js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createVuetify } from 'vuetify';
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import App from './App.vue';
import { router } from './router.js';

createApp(App).use(createPinia()).use(router).use(createVuetify()).mount('#app');
```

`resources/js/router.js`: маршрути `/login` → LoginView, `/` → ChatView з guard: `beforeEach` викликає `useAuthStore().fetchMe()` один раз і редіректить на `/login`, якщо користувача немає.

`App.vue`: `<v-app><v-main><router-view /></v-main></v-app>`.

`LoginView.vue`: `v-card` з полями email і пароль, кнопка «Увійти», показ `auth.error` через `v-alert`. Після успіху `router.push('/')`.

`ChatView.vue`: `v-navigation-drawer` зі списком розмов і кнопкою «Нова розмова»; центр: список `MessageBubble` для `chat.messages`, внизу `v-textarea` + кнопка «Надіслати» (disabled під час `chat.streaming`, Enter без Shift відправляє); праворуч `CitationPanel` для вибраної цитати; `v-alert type="error"` для `chat.error`. У верхній панелі ім'я користувача й кнопка «Вийти».

`MessageBubble.vue`: props `message`; рендерить текст, маркери `[n]` замінені на `v-chip size="x-small"` з кліком `emit('cite', citation)`; для `status === 'no_answer'` сіра підказка «Передано HR», для `grounded === false` бейдж «Потребує перевірки», для `failed` червона іконка.

`CitationPanel.vue`: props `citation`; показує `document_title`, `page`, `quote`. Кнопка «Відкрити фрагмент» вантажить `GET /chunks/{chunk_id}` і показує повний `content`.

- [ ] **Step 7: Ручна перевірка в браузері**

```bash
docker compose up -d && php artisan serve & php artisan queue:work --queue=ingestion & npm run dev
```
Увійти як HR (сідер з Task 15), завантажити документ через `curl` або Task 15 `make demo`, потім як співробітник поставити питання й побачити стрім і цитату. `npm run build` без помилок.

- [ ] **Step 8: Коміт**

```bash
git add -A
git commit -m "feat(web): vue spa with login, streaming chat and citation panel (AC-8 ui)"
```

---

### Task 15: Демо-дані Vesna Tech, журнал сесії, тег віхи

**Files:**
- Create: `database/seeders/DemoSeeder.php`, `database/seeders/demo/01-vacation-policy.md`, `database/seeders/demo/02-onboarding-guide.md`, `database/seeders/demo/03-security-policy.md`, `database/seeders/demo/04-code-review.md`, `database/seeders/demo/05-benefits.md`, `README.md`
- Modify: `database/seeders/DatabaseSeeder.php`, `docs/process/sessions/2026-10-06-plan-1.md`
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Produces: користувачі демо `hr@vesna.test / password` (hr_admin), `dev@vesna.test / password` (Engineering, developer), `pm@vesna.test / password` (Marketing, manager). П'ять Markdown-документів вигаданої компанії, аудиторії: 01, 02, 05 для всіх; 03 для всіх; 04 для відділу Engineering.

- [ ] **Step 1: Тест сідера**

```php
<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('seeds users and ingests five demo documents to ready', function () {
    Storage::fake('local');

    $this->seed(\Database\Seeders\DemoSeeder::class);

    expect(User::where('email', 'hr@vesna.test')->value('role'))->toBe('hr_admin')
        ->and(Document::count())->toBe(5)
        ->and(Document::where('status', DocumentStatus::Ready)->count())->toBe(5)
        ->and(Document::where('title', 'Процес code review')->value('audience_type')->value)->toBe('department');
});
```

- [ ] **Step 2: Документи демо**

Кожен файл 600–1200 слів українською про вигадану компанію Vesna Tech, з заголовками `#`/`##`, без реальних імен і контактів. Теми й ключові факти (вони ж підуть в eval у плані 3):
- `01-vacation-policy.md`: 24 календарні дні, заявка за 14 днів, перенесення до 10 днів, перші 6 місяців пропорційно.
- `02-onboarding-guide.md`: перший день, бадді, доступи за 2 дні, 1:1 з лідом щотижня, випробувальний термін 3 місяці.
- `03-security-policy.md`: менеджер паролів, 2FA обов'язкова, ноутбук із шифруванням, інцидент повідомляти за 1 годину.
- `04-code-review.md`: PR до 400 рядків, два апрува, CI зелений, рев'ю протягом 24 годин, аудиторія Engineering.
- `05-benefits.md`: медстрахування після 3 місяців, бюджет на навчання 500 на рік, спортивна компенсація.

- [ ] **Step 3: DemoSeeder**

```php
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $eng = Department::firstOrCreate(['name' => 'Engineering']);
        $mkt = Department::firstOrCreate(['name' => 'Marketing']);
        $hr = User::firstOrCreate(['email' => 'hr@vesna.test'], ['name' => 'HR Vesna', 'password' => 'password', 'role' => 'hr_admin']);
        User::firstOrCreate(['email' => 'dev@vesna.test'], ['name' => 'Dev Vesna', 'password' => 'password', 'role' => 'employee', 'department_id' => $eng->id, 'job_role' => 'developer']);
        User::firstOrCreate(['email' => 'pm@vesna.test'], ['name' => 'PM Vesna', 'password' => 'password', 'role' => 'employee', 'department_id' => $mkt->id, 'job_role' => 'manager']);

        $docs = [
            ['01-vacation-policy.md', 'Політика відпусток', AudienceType::All, null],
            ['02-onboarding-guide.md', 'Онбординг-гайд', AudienceType::All, null],
            ['03-security-policy.md', 'Політика безпеки', AudienceType::All, null],
            ['04-code-review.md', 'Процес code review', AudienceType::Department, (string) $eng->id],
            ['05-benefits.md', 'Довідник пільг', AudienceType::All, null],
        ];
        $uploader = app(DocumentUploader::class);
        foreach ($docs as [$file, $title, $type, $value]) {
            $path = database_path("seeders/demo/$file");
            $uploaded = new UploadedFile($path, $file, 'text/markdown', null, true);
            $uploader->upload($uploaded, $title, $type, $value, $hr);
        }
    }
}
```
У тесті черга `sync`, тому документи стають `ready` одразу. У dev з Redis-чергою запустити `php artisan queue:work --queue=ingestion`.

- [ ] **Step 4: README.md**

Розділи: що це і для кого (3 речення, лінк на спеку і кейс), швидкий старт (`make up`, `cp .env.example .env`, заповнити `ANTHROPIC_API_KEY`, `php artisan migrate --seed`, `make demo`, `php artisan serve`, `php artisan queue:work --queue=ingestion`, `npm run dev`), демо-логіни, як запускати тести, структура репо, посилання на `docs/`.

- [ ] **Step 5: Журнал сесії та тег**

Доповнити `docs/process/sessions/2026-10-06-plan-1.md` рядками для кожного виправлення, яке відбулось під час виконання задач 2–14 (що агент зробив не так, яке правило додано). Якщо виправлень не було, записати це явно: «виправлень не було, усі задачі пройшли з першої спроби» це теж факт для кейсу.

```bash
php artisan test && (cd services/embedder && pytest -q) && npm run test && npm run build
git add -A
git commit -m "feat(demo): vesna tech seed data, readme, session log for plan 1"
git tag -a v0.1-vertical-slice -m "Vertical slice: ingest -> search -> streamed answer with citations"
```

---

## Перевірка покриття спеки планом 1

| Розділ спеки | Задача |
|---|---|
| 4 AC-1, AC-3 | Task 8, 9 |
| 4 AC-2 | Task 9 (API) |
| 4 AC-5 | Task 4 (scope), Task 10 (SQL); UI і `GET /chunks` тест у плані 2 |
| 4 AC-6, AC-7, AC-8 | Task 12, 13 |
| 4 AC-4, AC-9, AC-10, AC-11, AC-12 | плани 2 і 3 |
| 5 архітектура, ADR | Task 1, 3 |
| 6 модель даних | Task 4 |
| 7.1 інжест | Task 6, 7, 8 |
| 7.2 відповідь | Task 10, 11, 12, 13 |
| 8 API | Task 5, 9, 13 (документи retry/delete/chunks list, gaps, stats у плані 2) |
| 9 сайдкар | Task 3 |
| 10 LLM і промпти | Task 11 |
| 11 безпека | Task 4, 5, 9, 10 (видалення й нічне прибирання у плані 2) |
| 12 збої | Task 6, 8, 13 (client_disconnected повністю у плані 2) |
| 14 тести | усі задачі; evals у плані 3 |
| 15 інфраструктура | Task 3 (частково), решта у плані 3 |
| 16 процес | Task 1, 15 |
