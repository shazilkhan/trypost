<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\IdeaStage;

use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIdeaStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ideaStage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return IdeaRequestRules::updateStage();
    }
}
