<?php

namespace Database\Seeders;

use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Ingestion\DocumentUploader;
use App\Knowledge\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $eng = Department::firstOrCreate(['name' => 'Engineering']);
        $mkt = Department::firstOrCreate(['name' => 'Marketing']);

        $hr = User::firstOrCreate(
            ['email' => 'hr@vesna.test'],
            ['name' => 'HR Vesna', 'password' => 'password', 'role' => 'hr_admin'],
        );
        User::firstOrCreate(
            ['email' => 'dev@vesna.test'],
            ['name' => 'Dev Vesna', 'password' => 'password', 'role' => 'employee', 'department_id' => $eng->id, 'job_role' => 'developer'],
        );
        User::firstOrCreate(
            ['email' => 'pm@vesna.test'],
            ['name' => 'PM Vesna', 'password' => 'password', 'role' => 'employee', 'department_id' => $mkt->id, 'job_role' => 'manager'],
        );

        $docs = [
            ['01-vacation-policy.md', 'Політика відпусток', AudienceType::All, null],
            ['02-onboarding-guide.md', 'Онбординг-гайд', AudienceType::All, null],
            ['03-security-policy.md', 'Політика безпеки', AudienceType::All, null],
            ['04-code-review.md', 'Процес code review', AudienceType::Department, (string) $eng->id],
            ['05-benefits.md', 'Довідник пільг', AudienceType::All, null],
        ];

        $uploader = app(DocumentUploader::class);
        foreach ($docs as [$file, $title, $type, $value]) {
            $uploaded = new UploadedFile(database_path("seeders/demo/$file"), $file, 'text/markdown', null, true);
            $uploader->upload($uploaded, $title, $type, $value, $hr);
        }
    }
}
