<script setup lang="ts">
import { computed } from 'vue';

import PostMetricsBand from '@/components/publish/PostMetricsBand.vue';
import type {
    PublicationAnalyticsDetail,
    UnsupportedPublicationAnalytics,
} from '@/types/analytics';

const props = defineProps<{
    detail?: PublicationAnalyticsDetail | UnsupportedPublicationAnalytics;
}>();
const savedDetail = computed((): PublicationAnalyticsDetail | null =>
    props.detail && 'available' in props.detail && props.detail.available
        ? props.detail
        : null,
);
</script>

<template>
    <section v-if="savedDetail" :aria-label="$t('posts.show.metrics')">
        <PostMetricsBand :detail="savedDetail" class="px-6" />
    </section>
</template>
