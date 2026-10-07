<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Ai\GeneratePostVideoRequest;
use App\Jobs\Ai\GeneratePostVideo;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\Ai\AiVideoClient;
use App\Services\Ai\AiVideoQuota;
use App\Services\Ai\RecordAiUsage;
use App\Support\PostStatusRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PostAiVideoController extends Controller
{
    public function generate(GeneratePostVideoRequest $request, Post $post, AiVideoClient $client): JsonResponse
    {
        $this->authorize('update', $post);

        abort_unless($client->isAvailable(), Response::HTTP_NOT_FOUND);

        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (PostStatusRules::blocksEditing($post)) {
            return response()->json([
                'message' => PostStatusRules::editBlockedMessage(),
                'errors' => ['prompt' => [PostStatusRules::editBlockedMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $gate = Gate::inspect('useAi', $workspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message()], Response::HTTP_PAYMENT_REQUIRED);
        }

        $generationId = $request->string('generation_id')->toString();
        $aspectRatio = $request->string('aspect_ratio')->toString();
        $duration = $request->integer('duration');

        $reserved = $this->reserve($workspace, $client, [
            'generation_id' => $generationId,
            'aspect_ratio' => $aspectRatio,
            'duration' => $duration,
            'resolution' => $client->resolution(),
        ], $user->id, $post->id);

        if (! $reserved) {
            $message = __('posts.ai.video.errors.limit_reached', ['limit' => AiVideoQuota::limit()]);

            return response()->json([
                'message' => $message,
                'errors' => ['prompt' => [$message]],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        GeneratePostVideo::dispatch(
            workspaceId: $workspace->id,
            postId: $post->id,
            userId: $user->id,
            generationId: $generationId,
            prompt: $request->string('prompt')->toString(),
            aspectRatio: $aspectRatio,
            duration: $duration,
        );

        return response()->json([
            'generation_id' => $generationId,
            'channel' => "user.{$user->id}.ai-video.{$generationId}",
            'remaining' => AiVideoQuota::remaining($workspace->account),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Count the generation against the account's monthly limit before it is
     * queued, under a lock so parallel requests cannot both take the last slot.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function reserve(Workspace $workspace, AiVideoClient $client, array $metadata, string $userId, string $postId): bool
    {
        return (bool) Cache::lock("ai_video_quota:{$workspace->account_id}", 10)->block(5, function () use ($workspace, $client, $metadata, $userId, $postId): bool {
            if (AiVideoQuota::remaining($workspace->account) <= 0) {
                return false;
            }

            RecordAiUsage::recordVideo(
                workspace: $workspace,
                provider: AiVideoClient::PROVIDER,
                model: $client->model(),
                userId: $userId,
                postId: $postId,
                metadata: $metadata,
            );

            return true;
        });
    }
}
