<?php

namespace App\Chat\Http;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('conversation'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['content' => ['required', 'string', 'min:1', 'max:2000']];
    }
}
