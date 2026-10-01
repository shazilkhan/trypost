<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconActivity,
    IconArrowUp,
    IconChartBar,
    IconEye,
    IconMessage,
    IconShare3,
    IconThumbUp,
} from '@tabler/icons-vue';
import { computed, type HTMLAttributes } from 'vue';

import { Button } from '@/components/ui/button';
import {
    compactPublicationMetrics,
    type CompactPublicationMetric,
    formatPublicationMetric,
    publicationMetricLabel,
} from '@/lib/publicationMetrics';
import { cn } from '@/lib/utils';
import { show as showPublication } from '@/routes/app/analytics/publications';
import type { PublicationAnalyticsDetail } from '@/types/analytics';

const props = withDefaults(
    defineProps<{
        detail: PublicationAnalyticsDetail;
        limit?: number;
        insightsTestId?: string;
        metricsTestId?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        limit: undefined,
        insightsTestId: undefined,
        metricsTestId: undefined,
        class: undefined,
    },
);

const METRIC_ICONS: Record<CompactPublicationMetric, typeof IconEye> = {
    views: IconEye,
    impressions: IconEye,
    reach: IconArrowUp,
    reactions: IconThumbUp,
    comments: IconMessage,
    shares: IconShare3,
    engagement_rate: IconActivity,
};

const metrics = computed(() =>
    compactPublicationMetrics(props.detail.metrics).slice(0, props.limit),
);

const insightsUrl = computed(() =>
    showPublication.url(props.detail.publication.id),
);
</script>

<template>
    <div
        :class="
            cn(
                'flex items-center gap-4 border-t border-border-strong px-4 py-2',
                props.class,
            )
        "
    >
        <dl
            v-if="metrics.length"
            class="flex min-w-0 flex-1 gap-8 overflow-x-auto py-3"
            :data-testid="metricsTestId"
        >
            <div
                v-for="metric in metrics"
                :key="metric.key"
                class="flex shrink-0 flex-col gap-1"
            >
                <dt
                    class="inline-flex items-center gap-1 text-sm font-medium text-foreground"
                >
                    <component
                        :is="METRIC_ICONS[metric.key]"
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ publicationMetricLabel(metric.key) }}
                </dt>
                <dd class="text-sm leading-none font-strong tabular-nums">
                    {{ formatPublicationMetric(metric.key, metric.fact) }}
                </dd>
            </div>
        </dl>
        <p v-else class="min-w-0 flex-1 py-3 text-sm text-muted-foreground">
            {{ $t('analytics.detail.awaiting_metrics') }}
        </p>
        <Button as-child variant="outline" size="icon" class="shrink-0">
            <Link
                :href="insightsUrl"
                :aria-label="$t('channels.insights')"
                :title="$t('channels.insights')"
                :data-testid="insightsTestId"
            >
                <IconChartBar class="size-4" />
            </Link>
        </Button>
    </div>
</template>
