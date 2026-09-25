<script setup lang="ts">
import { IconMessageCircle, IconX } from '@tabler/icons-vue';
import { ref, watch } from 'vue';

import PostNotesPanel from '@/components/posts/editor/PostNotesPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

const props = withDefaults(
    defineProps<{
        postId: string;
        count: number;
        currentUserId: string;
        initialOpen?: boolean;
        highlightNoteId?: string | null;
    }>(),
    {
        initialOpen: false,
        highlightNoteId: null,
    },
);

const open = ref(props.initialOpen);

watch(
    () => props.initialOpen,
    (initialOpen) => {
        open.value = initialOpen;
    },
);
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="gap-1.5"
                :aria-label="$t('notes.title')"
                :aria-expanded="open"
                :data-testid="`post-notes-trigger-${postId}`"
            >
                <IconMessageCircle
                    class="size-4"
                    :class="count ? 'fill-current' : ''"
                />
                <span v-if="count">{{ count }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent align="end" class="w-[min(25rem,calc(100vw-2rem))] p-0">
            <div
                class="flex items-center justify-between border-b border-border px-3 py-2"
            >
                <h3 class="text-sm font-semibold">
                    {{ $t('notes.title') }}
                </h3>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    :aria-label="$t('common.close')"
                    @click="open = false"
                >
                    <IconX class="size-4" />
                </Button>
            </div>
            <div class="h-80 min-h-0">
                <PostNotesPanel
                    v-if="open"
                    :post-id="postId"
                    :current-user-id="currentUserId"
                    :highlight-note-id="highlightNoteId"
                />
            </div>
        </PopoverContent>
    </Popover>
</template>
