<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Idea;

use App\Models\Idea;
use App\Support\Requests\Idea\IdeaRequestRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIdeaRequest extends FormRequest
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
        /** @var Idea $idea */
        $idea = $this->route('idea');

        return IdeaRequestRules::attributes(
            $this->user()->currentWorkspace,
            collect($idea->media ?? [])->pluck('id')->filter()->values()->all(),
        );
    }

    public function withValidator(Validator $validator): void
    {
        IdeaRequestRules::rejectEmptyIdea($validator, $this->all(), $this->route('idea'));
    }
}
