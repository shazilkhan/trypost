<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Idea;

use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class MoveIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('idea'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::move($this->user()->currentWorkspace);
    }
}
