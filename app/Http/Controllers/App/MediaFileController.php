<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a media file from the app's own origin, so the media editor can draw
 * it on a canvas and export the result: a file served from the storage host
 * without CORS headers would taint the canvas.
 */
class MediaFileController extends Controller
{
    public function show(Request $request, Media $media): StreamedResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('createPost', $workspace);
        abort_unless($media->workspace_id === $workspace->id && Storage::exists($media->path), 404);

        return Storage::response($media->path, headers: [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
