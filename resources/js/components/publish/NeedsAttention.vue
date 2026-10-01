<script setup lang="ts">
import { IconAlertTriangle } from '@tabler/icons-vue';

import PostTimelineCard from '@/components/publish/PostTimelineCard.vue';
import type { PostCard } from '@/types/publish';

defineProps<{
    posts: PostCard[];
    displayTimezone: string;
}>();
</script>

<template>
    <section class="flex flex-col gap-6" data-testid="needs-attention">
        <h2
            class="flex items-center gap-1.5 text-base leading-5 font-emphasis text-destructive-text"
        >
            <IconAlertTriangle class="size-4" aria-hidden="true" />
            {{ $t('posts.publish.needs_attention') }}
        </h2>
        <PostTimelineCard
            v-for="post in posts"
            :key="post.id"
            :post="post"
            tab="queue"
            :display-timezone="displayTimezone"
        />
    </section>
</template>
