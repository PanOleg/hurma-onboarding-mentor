<?php

namespace Database\Factories;

use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Department;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

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
}
