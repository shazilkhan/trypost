<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Ai;

use App\Services\Ai\AiVideoClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePostVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
            'aspect_ratio' => ['required', 'string', Rule::in(AiVideoClient::ASPECT_RATIOS)],
            'duration' => ['required', 'integer', Rule::in(app(AiVideoClient::class)->allowedDurations())],
            'generation_id' => ['required', 'string', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('prompt')) {
            $this->merge([
                'prompt' => trim((string) $this->input('prompt')),
            ]);
        }
    }
}
