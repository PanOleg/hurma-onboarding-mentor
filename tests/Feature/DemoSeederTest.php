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
