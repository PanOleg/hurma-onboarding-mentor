# SDD ledger — plan: docs/superpowers/plans/2026-10-06-mentor-plan-1-vertical-slice.md
Spec: docs/superpowers/specs/2026-10-06-onboarding-mentor-design.md (reachable, binding authority)
Branch: feat/plan-1-vertical-slice (in place, user consent 2026-10-06)

## Pre-flight scan

| Pair / task | Produces vs consumes | Finding |
|---|---|---|
| T2 ↔ T3 | .env.testing DB_PORT=3307, mentor_test db ↔ compose mariadb 3307:3306 + init.sql creates mentor_test | consistent |
| T2 ↔ T6/T11 | tests/TestCase::bindFakes() caркас ↔ T6, T11 додають bindings | consistent |
| T4 ↔ T10/T13 | Document::visibilityWhere(Builder|QueryBuilder, User, table) ↔ T10 passes Query\Builder with 'd', T13 ChunkController via repo | consistent |
| T4 ↔ T8 | DocumentStatus::order(); Extract finishes with status Extracting, Chunk begins if order ≤ Chunking | consistent (Extract→Extracting, Chunk→Chunking, Embed→Ready) |
| T6 ↔ T8 | EmbeddingException/ExtractionException ->code_ ↔ IngestionJob::failureCode() match | consistent |
| T7 ↔ T8 | Chunk(position,page,heading,content,tokenCount) ↔ ChunkDocument json keys and EmbedDocumentChunks rebuild | consistent |
| T8 ↔ T9 | DocumentIngestionService::dispatchChain ↔ DocumentUploader | consistent |
| T10 ↔ T11/T12/T13 | SearchHit positional ctor (chunkId, documentId, documentTitle, page, heading, content, distance) | consistent across tests |
| T11 ↔ T13 | LlmClient methods, FakeLlmClient fields nextAnswer/nextRewrite/nextGrounded/throws/calls | consistent |
| T12 ↔ T13 | CitationParser::parse → {citations, invalidMarkers} | consistent |
| T13 ↔ T14 | SSE event names/payloads message/token/citations/done/error | consistent |
| T5 self | login test needs stateful session | plan patched: Referer header + SANCTUM_STATEFUL_DOMAINS=localhost |
| T13 self | AnswerFlowTest with bag-of-words fake embeddings | plan patched: max_distance 0.6 in beforeEach, overlapping texts |
| T11 self | SDK class names RawMessageDeltaEvent->delta->stopReason, APIConnectionException | flagged in plan: verify against vendor, do not guess |
| T14 self | chat.js pushes plain `assistant` object then mutates local ref; Vue reactivity would not trigger in UI | Ruling: implementer must mutate the reactive element (`const assistant = messages.value[messages.value.length - 1]` after push) — spec 7.2 requires visible streaming — cost if wrong: UI shows no tokens until done |
| Pest global helpers | words(), hits(), seedChunks(), seedKnowledge(), sseEvents(), putChunks() | unique across files |
| Review rubric | no asserting-nothing tests, no mandated duplication found | clean |

Ruling: T14 reactivity fix above — carried into Task 14 dispatch.

## Progress
Task 1: BASE 53947d9, brief wrote /Users/ok/PhpstormProjects/HurmaSystem/.superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-1-brief.md: 140 lines
Task 1: review approved. Ruling: visibilityWhere vs visibleTo is not a conflict — Task 4 defines both by design (Eloquent scope `visibleTo()` delegates to static `visibilityWhere()` which also serves raw query builders); CLAUDE.md names the lower-level one on purpose. No doc change; carry the clarification into Task 4 dispatch — cost if wrong: an agent creates a duplicate method, caught by Task 4 review.
Task 1: minor (deferred): workflow.md vue-screen check `npm run test -- <store>` vs SKILL.md `npm run test`; ADR registry numbered 5.3 after section 7; wording in ADR 0005 («по мірі» → «у міру») and ADR 0002 («єдине місце»); session log has an unrequested role signature.
Task 1: complete (commits 53947d9..c1e9a18, review clean)
Task 2: BASE c1e9a18, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-2-brief.md
Task 2: implementer DONE_WITH_CONCERNS (commit 1d92d97). Deviations: phpunit ^13.2 added (Pest 5 needs PHPUnit 13, skeleton pins ^12.5); Unit tests extend TestCase in Pest.php; CLAUDE.md restored after rsync; skeleton AGENTS.md + README.md committed; HasApiTokens not added (cookie auth). Review dispatched.
Task 2: review Needs fixes — Important: stock AGENTS.md (Laravel Boost bootstrap) conflicts with CLAUDE.md. Minor: .gitignore `/vendor` vs `/vendor/`; stock helpers in tests/Pest.php; stock README (replaced in Task 15); APP_NAME=Laravel in .env.example. ⚠️ items verified by controller: CLAUDE.md header intact, 0 vendor/node_modules files tracked. Review head: 1d92d97.
Task 2: fix round 1/5 dispatched (resume implementer): delete AGENTS.md, literal gitignore entries.
Task 2: fix round 1/5 (2 addressed, 0 open — AGENTS.md deleted, gitignore literal; commits 1d92d97..b004aae)
Task 2: minor (deferred): stock helpers in tests/Pest.php (something(), toBeOne); APP_NAME=Laravel in .env.example; stock README replaced in Task 15.
Task 2: complete (commits c1e9a18..b004aae, review clean)
Task 3: BASE b004aae, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-3-brief.md
Task 3: implementer DONE_WITH_CONCERNS (commit 09105d8). Deviations: pytest.ini pythonpath=., PDF fixture font switched to cjk for Cyrillic, .pytest_cache ignored; 9 third-party DeprecationWarnings; image 1.43 GB; tests ran in local py3.14 venv, not in the 3.12 image. Review dispatched.
Task 3: review approved. ⚠️ resolved by controller: implementer's compose verification ran against the built image (compose up --build → /health JSON, VEC_DISTANCE_COSINE=1), so the image serves /health with the baked model; py3.14 vs 3.12 risk accepted (pins target 3.12, import succeeded in image).
Task 3: minor (deferred): no tests for 8000-char 422, 64-item cap, 413; query-vs-passage test only asserts inequality; no services/embedder/.dockerignore (build context includes .venv); pytest/httpx in runtime image (plan-mandated); page.get_text outside try (damaged PDF → 500); FASTEMBED_CACHE_PATH not set for local runs.
Task 3: complete (commits b004aae..09105d8, review clean)
Task 4: BASE f7956dc, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-4-brief.md
Task 4: implementer DONE_WITH_CONCERNS (commit b47f7d0). Deviations: phpunit.xml sqlite overrides removed so tests hit MariaDB 3307; phpstan generics PHPDoc added; User uses #[Fillable] attribute; Department model $guarded=[]. Review dispatched.
Task 4: review approved. ⚠️ resolved by controller: .env.testing is committed (not ignored) and points at 127.0.0.1:3307 mentor_test; tests/Pest.php applies RefreshDatabase to Feature.
Task 4: minor (deferred): phpunit.xml does not pin DB_DATABASE=mentor_test (fallback to .env could wipe dev DB if .env.testing missing); `role` in User #[Fillable] allows privilege escalation via mass assignment; DepartmentFactory unique()->randomElement exhausts after 5; no FK-behavior tests (cascade chunks, nullOnDelete citations); ingestion_runs.attempt signed, status free string.
Task 4: complete (commits f7956dc..b47f7d0, review clean)
Task 5: BASE 1f67ed0, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-5-brief.md
Task 5: implementer DONE_WITH_CONCERNS (commit d3900a9). Deviations: logout test needs Referer header too (brief omitted it); kept skeleton shouldRenderJsonWhen; cors published. Review dispatched.
Task 5: review Needs fixes — Important (plan-mandated): renderer for AuthorizationException never fires because Handler::render() runs prepareException() (→ AccessDeniedHttpException) before renderViaCallbacks(); verified by controller in vendor Handler.php. Ruling: plan text was wrong; match Symfony AccessDeniedHttpException (keep AuthorizationException too, harmless) and add a 403 envelope test — cost if wrong: policies in Tasks 9/13 return Laravel default JSON instead of the envelope, caught by Task 9 tests. Minor: logout test only asserts 204; login test does not prove cookie session; no tests for 429/404; validation messages English (locale).
Task 5: fix round 1/5 dispatched (resume implementer). Review head: d3900a9.
Task 5: fix round 1/5 (1 addressed, 0 open — forbidden renderer now AccessDeniedHttpException + test; commits d3900a9..9c00df3)
Task 5: complete (commits 1f67ed0..9c00df3, review clean)
Task 6: BASE fd2d471, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-6-brief.md
Task 6: implementer DONE (commit 95413d5). Extra hardening: non-array vector item → UNAVAILABLE; unreadable file → UNAVAILABLE in extractors. Review dispatched.
Task 6: review approved. Ruling: lone `\r` not normalized in LocalTextExtractor is Minor (brief said CRLF only; Chunker in Task 7 normalizes \r too) — deferred. ⚠️ resolved: config('rag.*') and embedder.invalid exist from Task 2.
Task 6: minor (deferred): lone CR in LocalTextExtractor; HttpTextExtractor casts `detail` to string (FastAPI 422 returns list → Array to string); malformed page entry → TypeError not UNAVAILABLE; no ConnectionException test, no extractor 422/5xx tests, no Local-before-Http order test.
Task 6: complete (commits fd2d471..95413d5, review clean)
Task 7: BASE af3285f, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-7-brief.md
Task 7: implementer DONE_WITH_CONCERNS (commit 26b4a1a). Deviation: overlap tail dropped when tail+piece > maxTokens. Gap: a single sentence longer than maxTokens without delimiters is not hard-split. Review dispatched.
Task 7: review Needs fixes — Important: (1) delimiter-less text > maxTokens not hard-split; (2) overlap test vacuous (plan-mandated); (3) numbered-heading rule turns lines like «24 календарні дні» into headings and drops them from content (plan-mandated). Rulings: (1) add fallback splits newline → `;` → whitespace → hard char split, plus test; (2) rewrite the test with distinct words per paragraph and assert chunk 1 starts with chunk 0's tail; (3) require a dot after the number in the numbered-heading rule AND prepend the heading line to the content of the first chunk under it so no text is ever lost from embeddings — cost if wrong: slightly longer first chunks and changed snapshot inputs later. Minor deferred: tail dropped (not trimmed) for 540–600-token pieces; tail may start mid-word; heading only detected on first line of a block; heading-only doc → 0 chunks; preg_split false → silent 0 chunks; no determinism/tail-drop tests.
Task 7: fix round 1/5 dispatched (resume implementer). Review head: 26b4a1a.
Task 7: fix round 1/5 implemented (commit ccfbca5): fallback splits, heading in content, strict overlap test with ltrim, 10 tests. Re-review dispatched.
Task 7: fix round 1/5 (3 addressed, 0 open — fallback splits, heading in content, strict overlap test; commits 26b4a1a..ccfbca5)
Task 7: minor (deferred): «24 календарні дні щороку.» test line does not exercise the regex change (ends with dot); no heading+max-piece combined test; tail dropped not trimmed for 540–600-token pieces; heading-only doc → 0 chunks; preg_split false → silent 0 chunks.
Task 7: complete (commits cc05774..ccfbca5, review clean)
Task 8: BASE 71fe88c, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-8-brief.md
Task 8: implementer DONE (commit 5cb8318). phpstan fixes: @property DocumentStatus docblock, string casts on Storage::get. Review dispatched.
Task 8: review Needs fixes — Important: (1) ingestion_runs row of a failed-then-retried attempt stays `running` forever (nothing closes it on throw; failed() only runs after the last attempt and marks all running rows with the last error); (2) "embeds in batches of 32" test asserts only the final count (plan-mandated). Ruling: (1) close the current run as failed with the error at the point of throw (shared guard in IngestionJob), rethrow; (2) spy provider recording batch sizes [32,32,6]; also apply fail() for non-retryable ExtractionException::UNSUPPORTED (Minor from review, cheap, avoids 3 wasted attempts) — cost if wrong: an unsupported-format doc that would have succeeded on retry (none realistic). Minor deferred: deleted-doc test should assert no run rows/artifacts; failed() updates all running rows regardless of step; $this->run unusable in failed() on fresh instance (moot after fix); memory for large docs; casts inconsistency.
Task 8: fix round 1/5 dispatched (resume implementer). Review head: 5cb8318.
Task 8: fix round 1/5 implemented (commit 34e44ca): guarded() closes run rows, spy batch test, fail() on UNSUPPORTED. Re-review dispatched.
Task 8: fix round 1/5 (3 addressed, 0 open — guarded() run rows, spy batch test, fail() on UNSUPPORTED; commits 5cb8318..34e44ca)
Task 8: minor (deferred): unsupported-format test calls failed() by hand so it does not guard the non-retry behaviour; deleted-doc test asserts only status; failed() ignores step; memory for large docs.
Task 8: complete (commits 3d32925..34e44ca, review clean)
Task 9: BASE 6087a4f, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-9-brief.md
Task 9: implementer DONE (commit 53ca19f). Deviations: Bus::assertChainedTimes does not exist → assertDispatchedTimes; @property IngestionStep docblock; per_page floor 1. Review dispatched.
Task 9: review approved. ⚠️ resolved: 403 envelope test passed (AccessDeniedHttpException renderer from Task 5); factory states, ingestionRuns, chunks_count exist from Task 4.
Task 9: minor (deferred): race on concurrent duplicate upload → UniqueConstraintViolation 500 (data stays consistent); orphaned file on failed create (content-addressed, harmless); status filter unvalidated; no tests for audience_value nulling, per_page cap, show 403.
Task 9: complete (commits 1a212e5..53ca19f, review clean)
Task 10: BASE 53ca19f, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-10-brief.md
Task 10: implementer DONE_WITH_CONCERNS (commit ec2499c). Deviation: cache test closure captured counter by value (plan bug) → use (&$calls). Review dispatched.
Task 10: review approved. ⚠️ resolved: visibilityWhere groups OR conditions in a closure (Task 4 review confirmed); FakeEmbeddingProvider yields distinct vectors (Task 6 unit test).
Task 10: minor (deferred): punctuation trim covers only ?!., ; recall limit when top-100 candidates are all invisible (known trade-off r03); seedChunks() global helper placement (plan-mandated).
Task 10: complete (commits 8998c72..ec2499c, review clean)
Task 11: BASE ec2499c, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-11-brief.md
Task 11: implementer DONE (commit 7b09985). AnthropicLlmClient verified by lint/phpstan/vendor grep only (no key in tests). Arrow-fn by-value capture bit the plan's fake test again → use (&$deltas). Review dispatched.
Task 11: review approved (reviewer verified every SDK call shape against vendor v0.54.0; APITimeoutException extends APIConnectionException). ⚠️ resolved: config('rag.models/prompts') from Task 2; live API untested → risk r37 (smoke after Task 15).
Task 11: minor (deferred): create() does not map max_tokens for helper calls; parsedOutput() error shape reported generically; no tests for nextRewrite/checkGrounding paths of the fake; partial text discarded on mid-stream failure.
Task 11: complete (commits 63b01a4..7b09985, review clean)
Task 12: BASE 77de65d, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-12-brief.md
Task 12: implementer DONE (commit 58eaa7f, haiku). Review dispatched.
Task 12: review approved, no findings.
Task 12: complete (commits 91ec137..58eaa7f, review clean)
Task 13: BASE 58eaa7f, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-13-brief.md
Task 13: implementer DONE_WITH_CONCERNS (commit 8a989b8). Deviations: streamedContent() added to 3 posts (stream body only runs when read); ob flush guarded by !runningUnitTests(); history query latest('id') after relation orderBy('id') picked oldest 6 → reorder('id','desc') (plan bug); phpstan needs --memory-limit=1G. Review dispatched.
Task 13: reviewer dispatched (opus) on review-093a008..8a989b8.diff; dispatch message was cut short by the harness but the agent is running.
Task 13: review Needs fixes — Important (both plan defects): (1) grounding/gap failure after streaming marks the already-shown answer failed with empty content (spec 7.2 says grounding must not block the text); (2) no ignore_user_abort(true) → on client disconnect the script dies at first write, message stays `streaming` forever. Ruling: (1) persist content/metrics right after streamAnswer; grounding and gap recording best-effort in own try/catch (grounded=false + needs_review on grounding failure, log on gap failure); FakeLlmClient gets per-method `throwsOn`; new test; (2) ignore_user_abort(true) in the stream closure — cost if wrong: a disconnected client's request keeps running to completion (seconds), acceptable. Also bundled cheap minors: drop `detail` from the error payload (log it), `whereNumber('id')` on chunks route, `deleted` flag also when chunk->document is null. Deferred minors: findVisible lacks status=ready filter; gaps store rewritten question; refusal-without-markers path; runningUnitTests() in controller; no test for GET /chunks/{id} (plan 2).
Task 13: fix round 1/5 dispatched (resume implementer). Review head: 8a989b8.
Task 13: fix round 1/5 implemented (commit c51bd7e). Re-review dispatched.
Task 13: fix round 1/5 (5 addressed, 0 open — early persist, best-effort grounding/gaps, ignore_user_abort, error payload, whereNumber, deleted flag; commits 8a989b8..c51bd7e)
Task 13: minor (deferred): DB failure in citation insert/final save after early persist still flips status to failed (acceptable); findVisible lacks status=ready filter; gaps store rewritten question; refusal-without-markers → needs_review (r42); runningUnitTests() in controller; no GET /chunks/{id} test (plan 2).
Task 13: complete (commits 093a008..c51bd7e, review clean)
Task 14: BASE 7009f93, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-14-brief.md
Task 14: implementer DONE_WITH_CONCERNS (commit e4149e7). vite-plugin-vuetify works on Vite 8.3; npm audit 2 critical (uninvestigated); no manual browser check. Review dispatched.
Task 14: review approved with 1 Important (plan-mandated): parseSseChunk handles only LF frames and single data: line. Ruling: our SseWriter (Task 13) emits LF-only frames so no runtime defect in this system; still fix cheaply (normalize \r\n → \n before parsing, test) and bundle the user-facing Minor "stream ends without done/error leaves status streaming" (mark failed after the read loop) — cost if wrong: none material. Deferred minors: draft cleared before send; error not cleared on conversation switch; bubble key changes when id arrives (remount); store tests do not guard reactivity ruling; no try/catch in logout/openConversation, no 401 redirect; 100vh layout; route regex excludes /apple*; npm audit (dev-only, r43). ⚠️ resolved: GET messages returns citations/status/grounded (Task 13 MessageResource).
Task 14: fix round 1/5 dispatched (resume implementer). Review head: e4149e7.
Task 14: fix round 1/5 implemented (commit 12758f5). Re-review dispatched.
Task 14: fix round 1/5 (2 addressed, 0 open — CRLF normalization + split-frame test, stuck status → failed; commits e4149e7..12758f5)
Task 14: complete (commits a5e2b3e..12758f5, review clean)
Task 15: BASE 12758f5, brief .superpowers/sdd/2026-10-06-mentor-plan-1-vertical-slice/task-15-brief.md
Task 15: implementer DONE_WITH_CONCERNS (commit 5b8fd75, tag v0.1-vertical-slice). pytest not on PATH (python3 -m pytest used); ANTHROPIC_API_KEY absent in .env → smoke not run. Review dispatched.
Task 15: review approved. Ruling: tag was created with the user's global git identity (different name/work email); re-created locally with the project identity (same commit 5b8fd75, same message) — cost if wrong: none, tag not pushed. Minor (deferred): README hint `python3 -m pytest`; make demo idempotency sentence; seeder does not say queue:work needed (README does); totals-line clause "0 задач повернуто більше ніж один раз" unproven (true per ledger: every task closed within 1 round).
Task 15: complete (commits d2edda8..5b8fd75, review clean)
ALL 15 TASKS COMPLETE. Final whole-branch review next (MERGE_BASE 04fd94a).
Final review dispatched (opus) on review-04fd94a..67cdcc7.diff with final-review-deferred.md.
FINAL REVIEW (opus): Needs fixes, 0 Critical. Important: I1 README quick start broken as written (.env.example DB_PORT 3306/empty password vs compose 3307/mentor; REDIS_CLIENT=phpredis without extension or predis → Class "Redis" not found breaks login/queue/cache; SANCTUM_STATEFUL_DOMAINS lacks 127.0.0.1:8000 and README never names the URL); I2 AC-1 20 MB limit unreachable with stock php.ini (2M/8M), PostTooLargeException returns 413 outside envelope. Cheap guard: pin DB_DATABASE=mentor_test in phpunit.xml. Recommended: GET /chunks/{id} negative test; CLAUDE.md wording for ChunkWriter INSERT and MessageResource history citations. Minor: history citations skip visibilityWhere; extension/mime consistency on upload; queue timeout 60s vs extract 120s; failed rewrite fails the answer; PHP_CLI_SERVER_WORKERS; ports on 0.0.0.0; README/Makefile inaccuracies (tests need redis, pytest venv, first-run model download, make eval missing); session log says 7 rounds but 6; seeder 2 employees vs spec 3; GET messages unpaginated; weak PDF-pages test.
Rulings: ONE fix wave — I1 (a,b,c) + APP_NAME; I2 via Makefile `serve` target with ini overrides + README note + PostTooLargeException → envelope 413; phpunit pin; /chunks/{id} negative test; CLAUDE.md names ChunkWriter and records MessageResource history-citation exception (visibility filter on history deferred to plan 2 — cost if wrong: stale title/page visible after an audience change, no new content disclosure); README fixes; session log 7→6; queue $timeout=150 on ingestion jobs + retry_after 180 (cheap, prevents `unknown` failures on slow PDFs). Everything else triaged per reviewer into plan 2/3 or dropped.
FINAL FIX WAVE: commit 1b5657b (9 items), 70/7/7 green. Scoped re-review dispatched.
FINAL FIX WAVE re-review: all 9 addressed, no new breakage. Final whole-branch review CLEAN. Plan 1 complete.
