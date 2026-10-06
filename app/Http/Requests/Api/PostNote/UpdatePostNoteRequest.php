<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PostNote;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
