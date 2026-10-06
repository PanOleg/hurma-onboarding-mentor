<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
