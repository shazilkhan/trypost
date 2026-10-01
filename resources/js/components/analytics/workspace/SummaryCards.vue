<script setup lang="ts">
import {
    IconInfoCircle,
    IconTrendingDown,
    IconTrendingUp,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    formatNumberCompact,
    formatPercent,
    formatPercentChange,
} from '@/lib/utils';
import type { AnalyticsReport, SummaryMetric } from '@/types/analytics';

import AnalyticsSection from './AnalyticsSection.vue';

const props = defineProps<{
    report: AnalyticsReport;
    availableMetrics?: SummaryMetric[];
    subtitle?: string;
    subtitleTestid?: string;
}>();

const defaultCards = computed(() => [
    {
        key: 'posts',
        label: 'analytics.dashboard.posts',
        metric: props.report.summary.posts,
        percent: false,
    },
    {
        key: 'followers',
        label: 'analytics.dashboard.total_followers',
        metric: props.report.summary.followers,
        percent: false,
    },
    {
        key: 'reactions',
        label: 'analytics.dashboard.reactions',
        metric: props.report.summary.reactions,
        percent: false,
    },
    {
        key: 'comments',
        label: 'analytics.dashboard.comments',
        metric: props.report.summary.comments,
        percent: false,
    },
    {
        key: 'engagement_rate',
        label: 'analytics.dashboard.engagement_rate',
        metric: props.report.summary.engagement_rate,
        percent: true,
    },
]);

const cards = computed(() =>
    props.availableMetrics
        ? props.availableMetrics.map((key) => ({
              key,
              label: `analytics.channel.metrics.${key}.label`,
              metric: props.report.summary[key],
              percent: key === 'engagement_rate',
          }))
        : defaultCards.value,
);

const testId = (key: string): string =>
    props.availableMetrics ? `insights-card-${key}` : `analytics-summary-${key}`;

const display = (value: number | null, percent: boolean): string =>
    value === null
        ? '—'
        : percent
          ? formatPercent(value)
          : formatNumberCompact(value);

const changeLabel = (key: string, change: number | null): string | null => {
    if (change === null) return null;
    return key === 'followers'
        ? `${change > 0 ? '+' : ''}${formatNumberCompact(change)}`
        : formatPercentChange(change);
};
</script>

<template>
    <AnalyticsSection
        :title="$t('analytics.dashboard.summary')"
        :subtitle="subtitle"
        :subtitle-testid="subtitleTestid"
    >
        <TooltipProvider :delay-duration="200">
            <div
                class="grid grid-cols-2 gap-2 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))]"
                data-testid="analytics-summary"
            >
                <div
                    v-for="card in cards"
                    :key="card.key"
                    :data-testid="testId(card.key)"
                    class="flex min-w-0 flex-col gap-2 rounded-lg border border-border bg-card px-4 py-3"
                >
                    <div class="flex min-h-6 items-center justify-between gap-1">
                        <p class="truncate text-xs text-muted-foreground">
                            {{ $t(card.label) }}
                        </p>
                        <Tooltip v-if="availableMetrics">
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-control hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                                    :aria-label="
                                        $t(
                                            `analytics.channel.metrics.${card.key}.about`,
                                        )
                                    "
                                    :data-testid="`insights-card-${card.key}-about`"
                                >
                                    <IconInfoCircle
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {{
                                    $t(
                                        `analytics.channel.metrics.${card.key}.about`,
                                    )
                                }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span
                            class="font-heading text-xl leading-tight font-medium text-foreground tabular-nums"
                            >{{ display(card.metric.value, card.percent) }}</span
                        >
                        <span
                            v-if="changeLabel(card.key, card.metric.change)"
                            :data-testid="`${testId(card.key)}-change`"
                            class="inline-flex items-center gap-1 text-xs text-foreground tabular-nums"
                        >
                            <IconTrendingUp
                                v-if="card.metric.change! >= 0"
                                class="size-4 shrink-0 text-success-text"
                                aria-hidden="true"
                            />
                            <IconTrendingDown
                                v-else
                                class="size-4 shrink-0 text-destructive-text"
                                aria-hidden="true"
                            />
                            {{ changeLabel(card.key, card.metric.change) }}
                        </span>
                    </div>
                </div>
            </div>
        </TooltipProvider>
        <p class="px-2 pt-3 pb-1 text-xs text-muted-foreground">
            {{ $t('analytics.dashboard.latest_snapshot_hint') }}
        </p>
    </AnalyticsSection>
</template>
