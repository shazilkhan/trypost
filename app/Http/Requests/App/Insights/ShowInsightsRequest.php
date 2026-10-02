<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Insights;

use Illuminate\Foundation\Http\FormRequest;

class ShowInsightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'publication' => ['sometimes', 'required', 'uuid'],
        ];
    }
}
