<?php

namespace App\Http\Requests;

use App\Enums\ResearchMode;
use App\Enums\WatchCadence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThreadRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:120'],
            'mode' => ['sometimes', Rule::enum(ResearchMode::class)],
            'credit_budget' => ['sometimes', 'integer', 'min:250', 'max:100000'],
            'max_depth' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'auto_follow_up' => ['sometimes', 'boolean'],
            'watch_cadence' => ['sometimes', 'nullable', Rule::enum(WatchCadence::class)],
        ];
    }
}
