<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_published' => ['required', 'boolean'],
            'post_failed' => ['required', 'boolean'],
            'account_disconnected' => ['required', 'boolean'],
            'post_note_added' => ['required', 'boolean'],
            'collaboration' => ['required', 'boolean'],
        ];
    }
}
