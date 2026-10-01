<script setup lang="ts">
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import date from '@/date';
import dayjs from '@/dayjs';
import {
    formatPublicationMetric,
    isVisiblePublicationMetric,
    publicationMetricLabel,
} from '@/lib/publicationMetrics';
import type { PublicationAnalyticsDetail } from '@/types/analytics';

import AnalyticsSection from './AnalyticsSection.vue';

const props = defineProps<{ detail: PublicationAnalyticsDetail }>();

const groups = [
    {
        label: 'analytics.detail.engagement',
        keys: [
            'reactions',
            'comments',
            'replies',
            'shares',
            'reposts',
            'quotes',
            'saves',
            'bookmarks',
            'engagements',
            'total_interactions',
            'engagement_rate',
            'clicks',
            'link_clicks',
            'pin_clicks',
            'pin_click_rate',
            'outbound_clicks',
            'outbound_click_rate',
            'save_rate',
            'follows',
            'profile_visits',
            'profile_activity',
        ],
    },
    {
        label: 'analytics.detail.exposure',
        keys: [
            'views',
            'video_views',
            'impressions',
            'reach',
            'total_audience',
            'engaged_audience',
            'unique_viewers',
        ],
    },
    {
        label: 'analytics.detail.video',
        keys: [
            'watch_time_milliseconds',
            'average_watch_time_milliseconds',
            'total_play_time_milliseconds',
            'average_video_play_time_milliseconds',
            'average_percentage_viewed',
            'skip_rate',
            'engaged_views',
            'video_views_10_seconds',
            'video_views_95_percent',
            'video_quartile_25',
            'video_quartile_50',
            'video_quartile_75',
            'video_quartile_100',
            'subscribers_gained',
            'subscribers_lost',
            'story_navigation',
            'story_taps_forward',
            'story_taps_back',
            'story_exits',
            'story_swipes_forward',
        ],
    },
] as const;

const visibleGroups = computed(() =>
    groups
        .map((group) => ({
            ...group,
            metrics: group.keys.flatMap((key) => {
                const fact = props.detail.metrics[key];
                return isVisiblePublicationMetric(fact) ? [{ key, fact }] : [];
            }),
        }))
        .filter((group) => group.metrics.length > 0),
);

const stale = computed(() =>
    props.detail.snapshot?.collected_at
        ? dayjs().diff(dayjs(props.detail.snapshot.collected_at), 'hour') > 48
        : false,
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div
            v-if="detail.snapshot"
            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
        >
            <span
                >{{ $t('analytics.detail.last_collected') }}
                <time :datetime="detail.snapshot.collected_at || undefined">{{
                    detail.snapshot.collected_at
                        ? date.formatDateTime(detail.snapshot.collected_at)
                        : detail.snapshot.date
                }}</time></span
            >
            <Badge v-if="stale" variant="warning" class="h-6 px-2">{{
                $t('analytics.detail.stale')
            }}</Badge>
        </div>

        <p
            v-if="visibleGroups.length === 0"
            class="rounded-xl border border-dashed border-border-strong bg-card px-6 py-12 text-center text-sm text-muted-foreground"
        >
            {{ $t('analytics.detail.awaiting_metrics') }}
        </p>
        <AnalyticsSection
            v-for="group in visibleGroups"
            :key="group.label"
            :title="$t(group.label)"
        >
            <div
                class="grid grid-cols-2 gap-2 sm:grid-cols-[repeat(auto-fill,minmax(200px,1fr))]"
            >
                <div
                    v-for="metric in group.metrics"
                    :key="metric.key"
                    :data-testid="`analytics-metric-${metric.key}`"
                    class="flex min-w-0 flex-col gap-2 rounded-lg border border-border bg-card px-4 py-3"
                    :title="
                        metric.fact.time_basis
                            ? $t(
                                  `analytics.detail.time_basis.${metric.fact.time_basis}`,
                              )
                            : undefined
                    "
                >
                    <p class="flex min-h-6 items-center text-xs text-muted-foreground">
                        {{ publicationMetricLabel(metric.key) }}
                    </p>
                    <p
                        class="font-heading text-xl leading-tight font-medium break-words text-foreground tabular-nums"
                    >
                        {{ formatPublicationMetric(metric.key, metric.fact) }}
                    </p>
                    <p
                        v-if="
                            metric.fact.precision &&
                            metric.fact.precision !== 'exact'
                        "
                        class="-mt-1 text-xs text-muted-foreground"
                    >
                        {{ $t('analytics.detail.estimated') }}
                    </p>
                </div>
            </div>
        </AnalyticsSection>
    </div>
</template>
