<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAiVideoGeneration } from '@/composables/useAiVideoGeneration';
import type { MediaItem } from '@/types/media';

export interface AiVideoOptions {
    enabled: boolean;
    durations: number[];
    aspectRatios: string[];
    limit: number;
    remaining: number;
}

const props = defineProps<{
    postId: string;
    options: AiVideoOptions;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    (e: 'generated', media: MediaItem): void;
}>();

const remaining = ref(props.options.remaining);

const { prompt, aspectRatio, duration, errorMessage, promptError, status, isBusy, isProcessing, canSubmit, submit, resetState, blockDismissWhileBusy } = useAiVideoGeneration({
    postId: props.postId,
    aspectRatios: props.options.aspectRatios,
    durations: props.options.durations,
    onGenerated: (media) => emit('generated', media),
    onStarted: () => {
        remaining.value = Math.max(0, remaining.value - 1);
    },
    onFailed: () => {
        remaining.value = Math.min(props.options.limit, remaining.value + 1);
    },
    onCompleted: () => {
        open.value = false;
    },
});

const durationValue = computed({
    get: () => String(duration.value),
    set: (value: string) => {
        duration.value = Number(value);
    },
});

const orientationLabel = (ratio: string): string => (
    ratio === '9:16' ? 'posts.ai.video.orientation_vertical' : 'posts.ai.video.orientation_horizontal'
);

const hasAllowance = computed(() => remaining.value > 0);

watch(open, (isOpen) => {
    if (!isOpen) {
        if (isProcessing.value) {
            open.value = true;
            return;
        }
        resetState();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="sm:max-w-xl"
            :show-close-button="!isBusy"
            data-testid="ai-video-dialog"
            @pointer-down-outside="blockDismissWhileBusy"
            @escape-key-down="blockDismissWhileBusy"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('posts.ai.video.title') }}</DialogTitle>
                <DialogDescription>{{ $t('posts.ai.video.description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="space-y-2">
                    <Label for="ai-video-prompt">{{ $t('posts.ai.video.prompt_label') }}</Label>
                    <Textarea
                        id="ai-video-prompt"
                        v-model="prompt"
                        :disabled="isBusy"
                        :placeholder="$t('posts.ai.video.prompt_placeholder')"
                        rows="4"
                        data-testid="ai-video-prompt"
                    />
                    <InputError :message="promptError" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="ai-video-orientation">{{ $t('posts.ai.video.orientation_label') }}</Label>
                        <Select v-model="aspectRatio" :disabled="isBusy">
                            <SelectTrigger id="ai-video-orientation" class="w-full" data-testid="ai-video-orientation">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="ratio in options.aspectRatios" :key="ratio" :value="ratio">
                                    {{ $t(orientationLabel(ratio)) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="ai-video-duration">{{ $t('posts.ai.video.duration_label') }}</Label>
                        <Select v-model="durationValue" :disabled="isBusy">
                            <SelectTrigger id="ai-video-duration" class="w-full" data-testid="ai-video-duration">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="seconds in options.durations" :key="seconds" :value="String(seconds)">
                                    {{ $t('posts.ai.video.duration_option', { seconds: String(seconds) }) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <p class="text-sm text-foreground/70" data-testid="ai-video-remaining">
                    {{ $t('posts.ai.video.remaining', { remaining: String(remaining), limit: String(options.limit) }) }}
                </p>

                <p v-if="status === 'processing'" class="text-sm text-foreground/70">
                    {{ $t('posts.ai.video.processing') }}
                </p>
                <p v-if="errorMessage" class="text-sm font-semibold text-rose-700">{{ errorMessage }}</p>
            </div>

            <DialogFooter>
                <Button
                    :loading="isBusy"
                    :disabled="!canSubmit || !hasAllowance"
                    data-testid="ai-video-submit"
                    @click="submit"
                >
                    {{ $t('posts.ai.video.submit') }}
                </Button>
                <Button variant="outline" :disabled="isBusy" @click="open = false">
                    {{ $t('posts.ai.video.cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
