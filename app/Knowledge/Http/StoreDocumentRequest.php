<?php

namespace App\Knowledge\Http;

use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) (config('rag.upload.max_bytes') / 1024), 'mimetypes:'.implode(',', config('rag.upload.mimes'))],
            'title' => ['required', 'string', 'max:255'],
            'audience_type' => ['required', Rule::enum(AudienceType::class)],
            'audience_value' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => in_array($this->input('audience_type'), ['department', 'role'], true))],
        ];
    }
}
