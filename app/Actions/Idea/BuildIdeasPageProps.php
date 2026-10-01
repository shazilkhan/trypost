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

        $props = [
            'view' => $isGallery ? 'gallery' : 'board',
            'stages' => IdeaStageResource::collection(
                $workspace->ideaStages()->withCount(['ideas' => fn (Builder $ideas) => self::applyLabelFilter($ideas, $request)])->get()
            ),
            'unassigned_count' => self::applyLabelFilter(
                Idea::query()->where('workspace_id', $workspace->id)->whereNull('idea_stage_id'),
                $request,
            )->count(),
            'labels' => $workspace->labels()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => [
                'stages' => $request->stageIds(),
                'labels' => $request->labelIds(),
                'untagged' => $request->untagged(),
            ],
            'editor' => $editor,
        ];

        $query = self::filteredQuery($request, $workspace);

        if ($isGallery) {
            $query->when($request->stageIds() !== [], fn (Builder $builder) => $builder->whereIn('idea_stage_id', $request->stageIds()))
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            $props['ideas'] = Inertia::scroll(fn () => IdeaCardResource::collection(
                $query->paginate((int) config('app.pagination.default'))
            ));

            return $props;
        }

        $props['board'] = fn () => IdeaCardResource::collection(
            $query->orderBy('position')->orderBy('created_at')->orderBy('id')->get()
        );

        return $props;
    }

    /**
     * @return Builder<Idea>
     */
    private static function filteredQuery(ListIdeasRequest $request, Workspace $workspace): Builder
    {
        return self::applyLabelFilter(
            Idea::query()->where('workspace_id', $workspace->id)->with('labels:id'),
            $request,
        );
    }

    /**
     * @param  Builder<Idea>  $query
     * @return Builder<Idea>
     */
    private static function applyLabelFilter(Builder $query, ListIdeasRequest $request): Builder
    {
        $labelIds = $request->labelIds();
        $untagged = $request->untagged();

        return $query->when($labelIds !== [] || $untagged, function (Builder $builder) use ($labelIds, $untagged): void {
            $builder->where(function (Builder $inner) use ($labelIds, $untagged): void {
                if ($labelIds !== []) {
                    $inner->whereHas('labels', fn (Builder $labels) => $labels->whereIn('workspace_labels.id', $labelIds));
                }

                if ($untagged) {
                    $inner->orWhereDoesntHave('labels');
                }
            });
        });
    }
}
