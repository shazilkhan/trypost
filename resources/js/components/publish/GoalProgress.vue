<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { settings } from '@/routes/app/channels';

const props = defineProps<{
    channelId: string;
    sent: number;
    goal: number;
}>();

const percent = computed(() =>
    Math.min(100, Math.round((props.sent / Math.max(props.goal, 1)) * 100)),
);
</script>

<template>
    <Link
        :href="settings.url(channelId)"
        class="flex min-w-0 items-center gap-1 rounded-md text-sm text-muted-foreground transition-control hover:text-foreground"
        data-testid="publish-goal-progress"
    >
        <svg
            viewBox="0 0 12 12"
            class="size-3 shrink-0 -rotate-90"
            aria-hidden="true"
        >
            <circle
                cx="6"
                cy="6"
                r="5"
                fill="none"
                stroke-width="1.5"
                class="stroke-border-strong"
            />
            <circle
                v-if="percent > 0"
                cx="6"
                cy="6"
                r="5"
                fill="none"
                stroke-width="1.5"
                stroke-linecap="round"
                pathLength="100"
                :stroke-dasharray="`${percent} 100`"
                class="stroke-success"
            />
        </svg>
        <span class="truncate">{{
            $t('posts.publish.goal', {
                sent: String(sent),
                goal: String(goal),
            })
        }}</span>
    </Link>
</template>
