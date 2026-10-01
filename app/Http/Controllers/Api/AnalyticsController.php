<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Analytics\BuildWorkspaceAnalyticsReport;
use App\Actions\Analytics\ReadPublicationAnalytics;
use App\Actions\Analytics\ResolveAnalyticsRangePreset;
use App\Http\Requests\AnalyticsReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(
        AnalyticsReportRequest $request,
        BuildWorkspaceAnalyticsReport $analytics,
        ResolveAnalyticsRangePreset $presets,
    ): JsonResponse {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        if (! $request->has('range')) {
            return response()->json($analytics->forSelection($workspace, $request->validated()));
        }

        ['selection' => $selection, 'clamped' => $clamped] = $presets->selection($request->validated(), $request->user()->timezone);

        return response()->json($analytics->forSelection($workspace, $selection, clampToBounds: $clamped));
    }

    public function showPublication(Request $request, string $publication, ReadPublicationAnalytics $analytics): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        return response()->json($analytics->latestForWorkspacePublication($workspace, $publication));
    }
}
