<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFactRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statement' => ['sometimes', 'string', 'min:8', 'max:600'],
            'pinned' => ['sometimes', 'boolean'],
            'dismissed' => ['sometimes', 'boolean'],
            'confidence' => ['sometimes', 'numeric', 'between:0,1'],
        ];
    }
}
