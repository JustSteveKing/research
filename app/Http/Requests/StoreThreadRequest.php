<?php

namespace App\Http\Requests;

use App\Enums\ResearchMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThreadRequest extends FormRequest
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
            'seed_question' => ['required', 'string', 'min:5', 'max:10000'],
            'title' => ['nullable', 'string', 'max:120'],
            'mode' => ['required', Rule::enum(ResearchMode::class)],
            'credit_budget' => ['required', 'integer', 'min:250', 'max:100000'],
            'max_depth' => ['required', 'integer', 'min:0', 'max:10'],
            'auto_follow_up' => ['boolean'],
        ];
    }
}
