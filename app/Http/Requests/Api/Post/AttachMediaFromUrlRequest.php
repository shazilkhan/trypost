<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Support\Requests\Post\PostMediaRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class AttachMediaFromUrlRequest extends FormRequest
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
        return PostMediaRequestRules::attachFromUrl();
    }
}
