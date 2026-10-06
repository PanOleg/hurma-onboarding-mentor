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
