<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Analytics\BuildWorkspaceAnalyticsReport;
use App\Actions\Analytics\ListAvailableChannelMetrics;
use App\Actions\Analytics\ReadPublicationAnalytics;
use App\Actions\Analytics\ResolveAnalyticsChannelFilter;
use App\Actions\Analytics\ResolveAnalyticsLabelFilter;
use App\Actions\Analytics\ResolveAnalyticsRangePreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsReportRequest;
use App\Http\Requests\App\Analytics\ShowAnalyticsRequest;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function show(ShowAnalyticsRequest $request, string $post, ReadPublicationAnalytics $analytics): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $record = Post::query()->whereBelongsTo($workspace)->findOrFail($post);

        return Inertia::render('analytics/Publications/Show', [
            'detail' => $analytics->latestForPostPublication($record, $request->validated('publication')),
        ]);
    }

    public function index(
        AnalyticsReportRequest $request,
        BuildWorkspaceAnalyticsReport $analytics,
        ResolveAnalyticsRangePreset $presets,
        ResolveAnalyticsLabelFilter $labelFilter,
        ResolveAnalyticsChannelFilter $channelFilter,
        ListAvailableChannelMetrics $availableMetrics,
    ): Response {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        ['range' => $range, 'selection' => $selection, 'clamped' => $clamped] = $presets->selection($request->validated(), $request->user()->timezone);
        ['labels' => $labels, 'selected' => $labelIds] = $labelFilter->execute($workspace, $request->collect('labels'));
        ['channels' => $channels, 'selected' => $selectedChannels, 'keys' => $channelKeys] = $channelFilter->execute($workspace, $request->collect('channels'));
        $untagged = $request->boolean('untagged');
        $report = $analytics->forSelection($workspace, $selection, $channelKeys, $clamped, $labelIds, $untagged);
        $single = $selectedChannels->count() === 1 ? $selectedChannels->first() : null;

        return Inertia::render('analytics/Index', [
            'labels' => $labels,
            'channels' => $channels,
            'availableMetrics' => $single === null ? null : $availableMetrics->handle($single, data_get($channelKeys, $single->id)),
            'report' => [
                ...$report,
                'filters' => [
                    'range' => $range,
                    'start' => data_get($report, 'range.start'),
                    'end' => data_get($report, 'range.end'),
                    'labels' => $labelIds,
                    'untagged' => $untagged,
                    'channels' => $selectedChannels->pluck('id')->all(),
                ],
            ],
        ]);
    }
}
