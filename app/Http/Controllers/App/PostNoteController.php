<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\PostNote\NotifyPostNoteAdded;
use App\Events\PostNoteChanged;
use App\Http\Requests\App\PostNote\ReactPostNoteRequest;
use App\Http\Requests\App\PostNote\StorePostNoteRequest;
use App\Http\Requests\App\PostNote\UpdatePostNoteRequest;
use App\Models\Post;
use App\Models\PostNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PostNoteController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $notes = $post->notes()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->latest()
            ->paginate(config('app.pagination.default'));

        return response()->json($notes);
    }

    public function store(StorePostNoteRequest $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        if (data_get($validated, 'parent_id')) {
            $parent = $post->notes()->find(data_get($validated, 'parent_id'));

            if (! $parent) {
                abort(Response::HTTP_NOT_FOUND);
            }

            if ($parent->parent_id !== null) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Cannot reply to a reply.');
            }
        }

        $note = $post->notes()->create([
            'user_id' => $request->user()->id,
            'parent_id' => data_get($validated, 'parent_id'),
            'body' => data_get($validated, 'body'),
        ]);

        $note->load('user');

        NotifyPostNoteAdded::execute($note);
        PostNoteChanged::dispatch($post->id, $post->workspace_id, 'created');

        return response()->json($note, Response::HTTP_CREATED);
    }

    public function update(UpdatePostNoteRequest $request, Post $post, PostNote $note): JsonResponse
    {
        if ($note->post_id !== $post->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($note->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $workspace = $request->user()->currentWorkspace;
        if ($note->post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $note->update(['body' => data_get($validated, 'body')]);

        PostNoteChanged::dispatch($post->id, $post->workspace_id, 'updated');

        return response()->json($note);
    }

    public function destroy(Request $request, Post $post, PostNote $note): JsonResponse
    {
        if ($note->post_id !== $post->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($note->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $workspace = $request->user()->currentWorkspace;
        if ($note->post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $note->delete();
        PostNoteChanged::dispatch($post->id, $post->workspace_id, 'deleted');

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function react(ReactPostNoteRequest $request, Post $post, PostNote $note): JsonResponse
    {
        if ($note->post_id !== $post->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $note->addReaction($request->user()->id, data_get($validated, 'emoji'));
        PostNoteChanged::dispatch($post->id, $post->workspace_id, 'reacted');

        return response()->json($note->fresh());
    }
}
