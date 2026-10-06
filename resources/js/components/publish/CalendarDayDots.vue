<script setup lang="ts">
import { computed } from 'vue';

import type { CalendarPost } from '@/types/publish';

const props = defineProps<{
    posts: CalendarPost[];
}>();

const MAX_DOTS = 3;

const STATUS_DOTS: Record<string, string> = {
    draft: 'bg-subtle-foreground',
    pending_approval: 'bg-info',
    published: 'bg-success',
    partially_published: 'bg-warning',
    failed: 'bg-destructive',
};

const dots = computed(() =>
    props.posts
        .slice(0, MAX_DOTS)
        .map((post) => ({
            key: post.id,
            color: STATUS_DOTS[post.status] ?? 'bg-primary-text',
        })),
);
</script>

<template>
    <span class="flex h-1.5 items-center justify-center gap-0.5" aria-hidden="true">
        <span
            v-for="dot in dots"
            :key="dot.key"
            class="size-1.5 rounded-full"
            :class="dot.color"
            data-testid="calendar-day-dot"
        />
    </span>
</template>
