<script setup lang="ts">
import {
    IconArrowBarToUp,
    IconArrowDown,
    IconArrowsMaximize,
    IconArrowUp,
    IconCopyPlus,
    IconDotsVertical,
    IconFileArrowLeft,
    IconSend,
    IconTrash,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PostStatus, ScheduleMode } from '@/types/post';
import type { PostCard, PostCardMenuAction } from '@/types/publish';

const props = defineProps<{
    post: PostCard;
    testKey: string;
    canMoveUp?: boolean;
    canMoveDown?: boolean;
    movable?: boolean;
}>();

const emit = defineEmits<{ select: [action: PostCardMenuAction] }>();

const isScheduled = computed(
    () => props.post.status === PostStatus.Scheduled,
);
const isDraft = computed(() => props.post.status === PostStatus.Draft);
const isQueued = computed(
    () =>
        isScheduled.value &&
        props.post.schedule_mode === ScheduleMode.Queue &&
        props.movable !== false,
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="icon"
                class="data-[state=open]:bg-accent"
                :aria-label="$t('posts.table.actions')"
                :data-testid="`post-card-menu-${testKey}`"
            >
                <IconDotsVertical class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :data-testid="`post-card-menu-content-${testKey}`"
        >
            <DropdownMenuItem
                v-if="isDraft"
                :data-testid="`post-publish-now-${testKey}`"
                @click="emit('select', 'publish_now')"
            >
                <IconSend class="size-4" />
                {{ $t('posts.publish.actions.publish_now') }}
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="isScheduled"
                :data-testid="`post-move-drafts-${testKey}`"
                @click="emit('select', 'move_drafts')"
            >
                <IconFileArrowLeft class="size-4" />
                {{ $t('posts.publish.actions.move_to_drafts') }}
            </DropdownMenuItem>
            <DropdownMenuItem
                :data-testid="`post-duplicate-${testKey}`"
                @click="emit('select', 'duplicate')"
            >
                <IconCopyPlus class="size-4" />
                {{ $t('posts.publish.actions.duplicate') }}
            </DropdownMenuItem>
            <DropdownMenuItem
                :data-testid="`post-details-open-${testKey}`"
                @click="emit('select', 'details')"
            >
                <IconArrowsMaximize class="size-4" />
                {{ $t('posts.show.title') }}
            </DropdownMenuItem>
            <template v-if="isQueued">
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    :data-testid="`post-move-top-${testKey}`"
                    @click="emit('select', 'move_top')"
                >
                    <IconArrowBarToUp class="size-4" />
                    {{ $t('posts.publish.actions.move_to_top') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    :disabled="!canMoveUp"
                    :data-testid="`post-move-up-${testKey}`"
                    @click="emit('select', 'move_up')"
                >
                    <IconArrowUp class="size-4" />
                    {{ $t('posts.publish.actions.move_up') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    :disabled="!canMoveDown"
                    :data-testid="`post-move-down-${testKey}`"
                    @click="emit('select', 'move_down')"
                >
                    <IconArrowDown class="size-4" />
                    {{ $t('posts.publish.actions.move_down') }}
                </DropdownMenuItem>
            </template>
            <template v-if="post.can_delete">
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    :data-testid="`post-delete-${testKey}`"
                    @click="emit('select', 'delete')"
                >
                    <IconTrash class="size-4" />
                    {{ $t('posts.publish.actions.delete') }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
