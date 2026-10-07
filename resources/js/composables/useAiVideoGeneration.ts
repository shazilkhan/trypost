import { useHttp, usePage } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { subscribePrivateChannel } from '@/composables/echo/subscribePrivateChannel';
import { extractErrorMessage } from '@/lib/httpError';
import { video as generatePostAiVideo } from '@/routes/app/posts/ai';
import type { MediaItem } from '@/types/media';

type GenerationStatus = 'idle' | 'starting' | 'processing';

interface GenerationEvent {
    media: MediaItem | null;
    error?: string | null;
}

interface UseAiVideoGenerationOptions {
    postId: string;
    aspectRatios: string[];
    durations: number[];
    onGenerated: (media: MediaItem) => void;
    onStarted?: () => void;
    onFailed?: () => void;
    onCompleted?: () => void;
}

const aiVideoGenerationChannel = (userId: string, generationId: string): string => `user.${userId}.ai-video.${generationId}`;

// The provider can take up to six minutes; leave room for the queue and the download.
const GENERATION_TIMEOUT_MS = 480_000;

export const useAiVideoGeneration = (options: UseAiVideoGenerationOptions) => {
    const page = usePage();

    const defaultAspectRatio = (): string => options.aspectRatios[0] ?? '9:16';
    const defaultDuration = (): number => Math.max(...options.durations, 0);

    const prompt = ref('');
    const aspectRatio = ref(defaultAspectRatio());
    const duration = ref(defaultDuration());
    const errorMessage = ref<string | null>(null);
    const promptError = ref<string | undefined>(undefined);
    const status = ref<GenerationStatus>('idle');

    const httpGenerate = useHttp<{ prompt: string; aspect_ratio: string; duration: number; generation_id: string }>({
        prompt: '',
        aspect_ratio: '',
        duration: 0,
        generation_id: '',
    });

    let subscribedChannel: string | null = null;
    let generationTimeout: ReturnType<typeof setTimeout> | null = null;
    let unmounted = false;

    const isBusy = computed(() => status.value !== 'idle');
    const isProcessing = computed(() => status.value === 'processing');
    const normalizedPrompt = computed(() => prompt.value.trim());
    const canSubmit = computed(() => normalizedPrompt.value.length > 0 && !isBusy.value);

    const unsubscribe = () => {
        if (subscribedChannel) {
            echo().leave(`private-${subscribedChannel}`);
            subscribedChannel = null;
        }
    };

    const clearGenerationTimeout = () => {
        if (generationTimeout !== null) {
            clearTimeout(generationTimeout);
            generationTimeout = null;
        }
    };

    const setIdleWithError = (message: string) => {
        errorMessage.value = message;
        status.value = 'idle';
    };

    const resetState = () => {
        prompt.value = '';
        aspectRatio.value = defaultAspectRatio();
        duration.value = defaultDuration();
        errorMessage.value = null;
        promptError.value = undefined;
        status.value = 'idle';
        clearGenerationTimeout();
        unsubscribe();
    };

    const blockDismissWhileBusy = (event: Event) => {
        if (isBusy.value) {
            event.preventDefault();
        }
    };

    const handleGenerationResult = (event: GenerationEvent) => {
        clearGenerationTimeout();

        if (event.error || !event.media) {
            setIdleWithError(event.error ?? trans('posts.ai.video.errors.failed'));
            unsubscribe();
            options.onFailed?.();
            return;
        }

        toast.success(trans('posts.ai.video.success'));

        options.onGenerated(event.media);

        resetState();
        options.onCompleted?.();
    };

    const subscribe = (channel: string): Promise<boolean> => {
        subscribedChannel = channel;

        return subscribePrivateChannel(channel, (ch) => {
            ch.listen('.ai.video.generated', (event: GenerationEvent) => handleGenerationResult(event));
        });
    };

    const submit = async () => {
        const promptValue = normalizedPrompt.value;

        if (!promptValue) {
            errorMessage.value = null;
            promptError.value = trans('posts.ai.video.errors.required');
            return;
        }

        errorMessage.value = null;
        promptError.value = undefined;
        status.value = 'starting';

        const generationId = crypto.randomUUID();
        const channel = aiVideoGenerationChannel(String(page.props.auth.user.id), generationId);

        try {
            const subscribed = await subscribe(channel);

            if (unmounted) {
                unsubscribe();
                return;
            }

            if (!subscribed) {
                throw new Error('Channel subscription failed');
            }

            httpGenerate.prompt = promptValue;
            httpGenerate.aspect_ratio = aspectRatio.value;
            httpGenerate.duration = duration.value;
            httpGenerate.generation_id = generationId;
            await httpGenerate.post(generatePostAiVideo.url({ post: options.postId }));

            if (httpGenerate.hasErrors) {
                unsubscribe();
                status.value = 'idle';
                promptError.value = httpGenerate.errors.prompt
                    ?? httpGenerate.errors.aspect_ratio
                    ?? httpGenerate.errors.duration
                    ?? trans('posts.ai.video.errors.start_failed');
                return;
            }

            status.value = 'processing';
            options.onStarted?.();

            clearGenerationTimeout();
            generationTimeout = setTimeout(() => {
                setIdleWithError(trans('posts.ai.video.errors.timeout'));
                unsubscribe();
            }, GENERATION_TIMEOUT_MS);
        } catch (error: unknown) {
            clearGenerationTimeout();
            unsubscribe();
            setIdleWithError(extractErrorMessage(error) ?? trans('posts.ai.video.errors.start_failed'));
        }
    };

    onUnmounted(() => {
        unmounted = true;
        clearGenerationTimeout();
        unsubscribe();
    });

    return {
        prompt,
        aspectRatio,
        duration,
        errorMessage,
        promptError,
        status,
        isBusy,
        isProcessing,
        canSubmit,
        submit,
        resetState,
        blockDismissWhileBusy,
    };
};
