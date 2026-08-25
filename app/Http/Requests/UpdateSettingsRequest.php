<?php

namespace App\Http\Requests;

use App\Enums\ResearchMode;
use App\Services\Settings\AppSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
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
            // Blank clears the key, which is how a reader removes it.
            'tabstack_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ollama_base_url' => ['sometimes', 'required', 'string', 'url:http,https', 'max:255'],
            'ollama_chat_model' => ['sometimes', 'required', 'string', 'max:120'],
            'ollama_embed_model' => ['sometimes', 'required', 'string', 'max:120'],
            'default_mode' => ['sometimes', 'required', Rule::enum(ResearchMode::class)],
            'default_budget' => ['sometimes', 'required', 'integer', 'min:250', 'max:100000'],
            'max_depth' => ['sometimes', 'required', 'integer', 'min:0', 'max:10'],
        ];
    }

    /**
     * The validated input keyed the way the settings store expects it.
     *
     * @return array<string, string|int|null>
     */
    public function settings(): array
    {
        $map = [
            'tabstack_api_key' => AppSettings::TABSTACK_API_KEY,
            'ollama_base_url' => AppSettings::OLLAMA_BASE_URL,
            'ollama_chat_model' => AppSettings::OLLAMA_CHAT_MODEL,
            'ollama_embed_model' => AppSettings::OLLAMA_EMBED_MODEL,
            'default_mode' => AppSettings::RESEARCH_DEFAULT_MODE,
            'default_budget' => AppSettings::RESEARCH_DEFAULT_BUDGET,
            'max_depth' => AppSettings::RESEARCH_MAX_DEPTH,
        ];

        $settings = [];

        foreach ($this->validated() as $field => $value) {
            $settings[$map[$field]] = is_string($value) ? trim($value) : $value;
        }

        return $settings;
    }
}
