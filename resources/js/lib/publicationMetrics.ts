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

export const COMPACT_PUBLICATION_METRICS = [
    'views',
    'impressions',
    'reach',
    'comments',
    'engagement_rate',
    'reactions',
    'shares',
] as const;

export type CompactPublicationMetric =
    (typeof COMPACT_PUBLICATION_METRICS)[number];

/** The headline metrics a post card or post details band shows, in TryPost order. */
export const compactPublicationMetrics = (
    metrics: Record<string, PublicationMetricFact | undefined>,
): { key: CompactPublicationMetric; fact: PublicationMetricFact }[] =>
    COMPACT_PUBLICATION_METRICS.flatMap((key) => {
        const fact = metrics[key];

        return isVisiblePublicationMetric(fact) ? [{ key, fact }] : [];
    });
