<?php

namespace App\Http\Requests;

use App\Enums\ResearchMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResearchRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:5', 'max:10000'],
            'mode' => ['nullable', Rule::enum(ResearchMode::class)],
        ];
    }
}
