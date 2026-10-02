import { trans } from 'laravel-vue-i18n';

import { formatNumberCompact, formatPercent } from '@/lib/utils';
import type { PublicationMetricFact } from '@/types/analytics';

export const publicationMetricLabel = (key: string): string => {
    const core = `analytics.metrics.${key}`;
    const coreLabel = trans(core);
    if (coreLabel !== core) return coreLabel;

    return trans(`analytics.detail.labels.${key}`);
};

export const formatPublicationMetric = (
    key: string,
    fact: PublicationMetricFact,
): string => {
    if (fact.value === null) return '—';
    if (fact.unit === 'percent') return formatPercent(fact.value);
    if (fact.unit === 'milliseconds') {
        const average = key.includes('average');
        return `${formatNumberCompact(fact.value / (average ? 1000 : 60000))} ${average ? 's' : 'min'}`;
    }
    return formatNumberCompact(fact.value);
};

export const isVisiblePublicationMetric = (
    fact: PublicationMetricFact | undefined,
): fact is PublicationMetricFact =>
    fact?.availability === 'available' && fact.value !== null;

const STORY_METRICS = [
    'views',
    'reach',
    'replies',
    'engagement_rate',
    'reactions',
] as const;

const FEED_METRICS = [
    'reactions',
    'comments',
    'engagement_rate',
    'views',
    'shares',
    'saves',
    'follows',
    'reach',
] as const;

const VIDEO_METRICS = [
    'reactions',
    'comments',
    'engagement_rate',
    'views',
    'shares',
    'saves',
    'watch_time_milliseconds',
    'average_watch_time_milliseconds',
    'reach',
] as const;

const CONTENT_TYPE_METRICS: Record<string, readonly string[]> = {
    story: STORY_METRICS,
    video: VIDEO_METRICS,
    reel: VIDEO_METRICS,
    short: VIDEO_METRICS,
};

const NETWORK_METRICS: Record<string, readonly string[]> = {
    x: ['reposts', 'quotes', 'bookmarks', 'impressions'],
    pinterest: ['pin_clicks', 'outbound_clicks', 'save_rate'],
    youtube: ['subscribers_gained', 'average_percentage_viewed'],
    linkedin: ['clicks'],
    'linkedin-page': ['clicks'],
    facebook: ['clicks'],
    tiktok: [
        'total_play_time_milliseconds',
        'average_video_play_time_milliseconds',
    ],
};

/** The metric keys a post shows for its content type, then its network's extras. */
export const publicationMetricKeys = (
    contentType: string | null | undefined,
    platform: string | null | undefined,
): string[] => [
    ...new Set([
        ...(CONTENT_TYPE_METRICS[contentType ?? ''] ?? FEED_METRICS),
        ...(NETWORK_METRICS[platform ?? ''] ?? []),
    ]),
];

/** The headline metrics a post card or post details band shows, in TryPost order. */
export const compactPublicationMetrics = (detail: {
    publication: { content_type: string; platform: string } | null;
    metrics: Record<string, PublicationMetricFact | undefined>;
}): { key: string; fact: PublicationMetricFact }[] =>
    publicationMetricKeys(
        detail.publication?.content_type,
        detail.publication?.platform,
    ).flatMap((key) => {
        const fact = detail.metrics[key];

        return isVisiblePublicationMetric(fact) ? [{ key, fact }] : [];
    });
