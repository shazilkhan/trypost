import { shallowRef } from 'vue';

import type { ComposerInitialDraft } from '@/composables/usePostComposition';

export interface PostComposerRequest {
    id: number;
    date: string | null;
    assistant: boolean;
    socialAccountIds: string[];
    draft: ComposerInitialDraft | null;
}

let nextRequestId = 0;

export const postComposerRequest = shallowRef<PostComposerRequest | null>(null);

export const openPostComposer = (
    options: {
        date?: string | null;
        assistant?: boolean;
        socialAccountIds?: string[];
        draft?: ComposerInitialDraft | null;
    } = {},
): void => {
    if (typeof window === 'undefined') return;

    postComposerRequest.value = {
        id: ++nextRequestId,
        date: options.date ?? null,
        assistant: options.assistant ?? false,
        socialAccountIds: options.socialAccountIds ?? [],
        draft: options.draft ?? null,
    };
};

export const closePostComposer = (): void => {
    postComposerRequest.value = null;
};
