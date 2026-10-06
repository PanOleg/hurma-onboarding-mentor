---
name: prompt-change
description: Change an LLM prompt safely: new versioned file, snapshot test, eval run, evals log entry.
---
1. Copy resources/prompts/<name>.vN.md to <name>.v(N+1).md and edit the new file only.
2. Point config/rag.php `prompts.<name>` to the new version.
3. Update tests/Unit/Chat/PromptSnapshotTest.php: run `php artisan test --filter=PromptSnapshotTest`, review the diff, accept with `--update-snapshots` only after reading it.
4. Run `php artisan rag:eval` (real providers, costs money, ask before running) and append a row to docs/04-evals.md: date, prompt versions, threshold, models, recall@5, citation_validity, grounded_rate, no_answer_precision.
5. Commit `prompt(<name>): vN+1 <why>`.
