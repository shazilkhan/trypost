<script setup lang="ts">
import type { Directive } from 'vue';

import DayHeading from '@/components/publish/DayHeading.vue';
import PostTimelineCard from '@/components/publish/PostTimelineCard.vue';
import QueueSlotRow from '@/components/publish/QueueSlotRow.vue';
import type {
    QueueDropIndicator,
    SortableQueueItem,
} from '@/composables/useSortableQueue';
import type {
    PostCard,
    PostCardMove,
    PublishSocialAccount,
    QueueItem,
    QueuePostPosition,
} from '@/types/publish';

const props = defineProps<{
    date: string;
    items: QueueItem[];
    posts: Record<string, PostCard>;
    channels: Record<string, PublishSocialAccount>;
    positions: Record<string, QueuePostPosition>;
    displayTimezone: string;
    dropIndicator: QueueDropIndicator | null;
    register: (el: HTMLElement, item: SortableQueueItem) => () => void;
}>();

const emit = defineEmits<{
    move: [post: PostCard, direction: PostCardMove];
}>();

const cleanups = new WeakMap<HTMLElement, () => void>();

const unbind = (el: HTMLElement): void => {
    cleanups.get(el)?.();
    cleanups.delete(el);
};

const bind = (el: HTMLElement, item: SortableQueueItem | null): void => {
    if (item) {
        cleanups.set(el, props.register(el, item));
    }
};

const vSortable: Directive<HTMLElement, SortableQueueItem | null> = {
    mounted: (el, { value }) => bind(el, value),
    updated: (el, { value, oldValue }) => {
        if (
            value?.postId === oldValue?.postId &&
            value?.channelId === oldValue?.channelId
        ) {
            return;
        }

        unbind(el);
        bind(el, value);
    },
    unmounted: (el) => unbind(el),
};

const itemKey = (item: QueueItem): string =>
    item.type === 'post'
        ? `post-${item.post_id}`
        : `slot-${item.channel_id}-${item.at}`;

const onMove = (item: QueueItem, direction: PostCardMove): void => {
    const post = item.post_id ? props.posts[item.post_id] : undefined;

    if (post) {
        emit('move', post, direction);
    }
};

const sortableItem = (item: QueueItem): SortableQueueItem | null =>
    item.post_id && props.positions[item.post_id]?.draggable
        ? { postId: item.post_id, channelId: item.channel_id }
        : null;
</script>

<template>
    <section class="flex flex-col gap-6" :data-testid="`queue-day-${date}`">
        <DayHeading :date-key="date" :timezone="displayTimezone" />
        <template v-for="item in items" :key="itemKey(item)">
            <div
                v-if="item.type === 'post' && item.post_id && posts[item.post_id]"
                v-sortable="sortableItem(item)"
                class="relative transition-opacity duration-150 data-dragging:opacity-40"
            >
                <div
                    v-if="dropIndicator?.postId === item.post_id"
                    class="pointer-events-none absolute inset-x-0 h-0.5 rounded-full bg-primary-strong md:left-[103px]"
                    :class="
                        dropIndicator.edge === 'top' ? '-top-[13px]' : '-bottom-[13px]'
                    "
                    :data-testid="`queue-drop-indicator-${item.post_id}`"
                />
                <PostTimelineCard
                    :post="posts[item.post_id]"
                    tab="queue"
                    :display-timezone="displayTimezone"
                    :can-move-up="positions[item.post_id]?.canMoveUp ?? false"
                    :can-move-down="
                        positions[item.post_id]?.canMoveDown ?? false
                    "
                    :draggable="positions[item.post_id]?.draggable ?? false"
                    :movable="item.post_id in positions"
                    @move="(direction) => onMove(item, direction)"
                />
            </div>
            <QueueSlotRow
                v-else-if="item.type === 'slot'"
                :item="item"
                :channel="channels[item.channel_id] ?? null"
                :display-timezone="displayTimezone"
            />
        </template>
    </section>
</template>
