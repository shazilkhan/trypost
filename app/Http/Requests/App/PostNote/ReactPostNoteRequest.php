<?php

declare(strict_types=1);

namespace App\Http\Requests\App\PostNote;

use Illuminate\Foundation\Http\FormRequest;

class ReactPostNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:10'],
        ];
    }
}
