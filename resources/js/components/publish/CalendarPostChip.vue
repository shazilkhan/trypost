<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconRepeat } from '@tabler/icons-vue';
import { computed } from 'vue';

import PostScheduleModeBadge from '@/components/posts/PostScheduleModeBadge.vue';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import date from '@/date';
import { isImage } from '@/lib/mediaType';
import { edit as editPost } from '@/routes/app/posts';
import { PostStatus } from '@/types/post';
import type { CalendarPost } from '@/types/publish';

const props = defineProps<{
    post: CalendarPost;
    timezone: string;
}>();

const MAX_LOGOS = 4;

const EDITABLE_STATUSES: readonly string[] = [
    PostStatus.Draft,
    PostStatus.Scheduled,
    PostStatus.PendingApproval,
];

const STATUS_CLASSES: Record<string, string> = {
    draft: 'border-dashed',
    partially_published: 'border-warning/40 bg-warning/5',
    failed: 'border-destructive/40 bg-destructive/5',
};

const isEditable = computed(() =>
    EDITABLE_STATUSES.includes(props.post.status),
);

const visibleTargets = computed(() =>
    props.post.post_platforms.slice(
        0,
        props.post.post_platforms.length > MAX_LOGOS
            ? MAX_LOGOS - 1
            : MAX_LOGOS,
    ),
);

const hiddenTargets = computed(
    () => props.post.post_platforms.length - visibleTargets.value.length,
);

const channels = computed(() =>
    props.post.post_platforms
        .map(
            (target) =>
                target.social_account?.display_label ??
                getPlatformLabel(target.platform),
        )
        .join(', '),
);

const time = computed(() =>
    date.formatTimeInTimezone(props.post.calendar_at, props.timezone),
);

const thumbnail = computed(
    () => (props.post.media ?? []).find(isImage)?.url ?? null,
);

const scheduleMode = computed(() =>
    props.post.status === PostStatus.Scheduled
        ? (props.post.schedule_mode ?? null)
        : null,
);
</script>

<template>
    <component
        :is="isEditable ? Link : 'div'"
        :href="isEditable ? editPost.url(post.id) : undefined"
        class="flex h-7 min-w-0 shrink-0 items-center gap-1 rounded-lg border border-border-strong bg-card px-1 transition-control"
        :class="[
            STATUS_CLASSES[post.status] ?? '',
            isEditable ? 'hover:bg-secondary' : '',
        ]"
        :title="`${channels} · ${post.content?.trim() || $t('calendar.no_content')}`"
        :data-testid="`calendar-post-${post.id}`"
    >
        <img
            v-for="target in visibleTargets"
            :key="target.id"
            :src="getPlatformLogo(target.platform)"
            :alt="getPlatformLabel(target.platform)"
            class="size-4 shrink-0 rounded-sm"
        />
        <span v-if="hiddenTargets > 0" class="text-xs text-muted-foreground"
            >+{{ hiddenTargets }}</span
        >
        <span
            class="truncate text-xs font-medium text-muted-foreground"
            >{{ time }}</span
        >
        <IconRepeat
            v-if="scheduleMode && post.recurrence_frequency"
            class="size-3 shrink-0 text-muted-foreground"
            :aria-label="$t('posts.recurrence.marker')"
            :data-testid="`calendar-post-recurring-${post.id}`"
        />
        <PostScheduleModeBadge
            v-else-if="scheduleMode"
            :post-id="post.id"
            :mode="scheduleMode"
            compact
            plain
        />
        <img
            v-if="thumbnail"
            :src="thumbnail"
            alt=""
            loading="lazy"
            class="ms-auto size-5 shrink-0 rounded object-cover"
        />
    </component>
</template>
