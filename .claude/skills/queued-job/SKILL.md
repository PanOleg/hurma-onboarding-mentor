---
name: queued-job
description: Add a queued job for the ingestion chain with idempotency, retries and ingestion_runs logging, plus tests for the success and failed paths.
---
1. Job class in app/Knowledge/Jobs, implements ShouldQueue, `public int $tries = 3; public array $backoff = [10, 30, 90];`, queue `ingestion`.
2. handle(): reload the Document, return early if deleted_at or status is already past this step; set the step status; open an IngestionRun (status running); do the work; close the run (done); advance status.
3. failed(Throwable $e): close the run with failed + error, set document status failed with a FailureCode.
4. Tests in tests/Feature/Knowledge/Jobs/<Job>Test.php: success path asserts status and run row; failed path binds a fake that throws, calls `$job->failed($e)` and asserts failure_code.
5. `php artisan test --filter=<Job>Test`, pint, commit `feat(knowledge): <job> (AC-n)`.
