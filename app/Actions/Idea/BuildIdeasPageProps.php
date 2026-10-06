<?php

declare(strict_types=1);

namespace App\Actions\Idea;

use App\Http\Requests\App\Idea\ListIdeasRequest;
use App\Http\Resources\App\IdeaCardResource;
use App\Http\Resources\App\IdeaStageResource;
use App\Models\Idea;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;

class BuildIdeasPageProps
{
    /**
     * @param  array<string, mixed>|null  $editor
     * @return array<string, mixed>
     */
    public static function execute(ListIdeasRequest $request, Workspace $workspace, ?array $editor = null): array
    {
        $isGallery = $request->isGallery();
        $filters = [
            'stages' => $request->stageIds(),
            'labels' => $request->labelIds(),
            'untagged' => $request->untagged(),
            'unassigned' => $request->unassigned(),
        ];

        $props = [
            'view' => $isGallery ? 'gallery' : 'board',
            'stages' => IdeaStageResource::collection(
                $workspace->ideaStages()->withCount(['ideas' => fn (Builder $ideas) => ListIdeas::applyLabelFilter($ideas, $filters)])->get()
            ),
            'unassigned_count' => ListIdeas::applyLabelFilter(
                Idea::query()->where('workspace_id', $workspace->id)->whereNull('idea_stage_id'),
                $filters,
            )->count(),
            'labels' => $workspace->labels()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => $filters,
            'editor' => $editor,
        ];

        if ($isGallery) {
            $props['ideas'] = Inertia::scroll(fn () => IdeaCardResource::collection(
                ListIdeas::query($workspace, $filters)->paginate((int) config('app.pagination.default'))
            ));

            return $props;
        }

        $props['board'] = fn () => IdeaCardResource::collection(
            ListIdeas::board($workspace, $filters)->get()
        );

        return $props;
    }
}
