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
