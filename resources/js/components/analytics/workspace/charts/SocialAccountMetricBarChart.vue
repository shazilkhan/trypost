<script setup lang="ts">
import {
    VisAxis,
    VisAxisSelectors,
    VisStackedBar,
    VisStackedBarSelectors,
    VisXYContainer,
} from '@unovis/vue';
import { computed } from 'vue';

import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import { accountColor } from '@/lib/analyticsColors';
import type { AccountIdentityData } from '@/types/analytics';

import {
    formatCountTick,
    socialAccountChartConfig,
} from './socialAccountChart';

const props = defineProps<{
    rows: { account: AccountIdentityData; value: number | null }[];
    colors: Record<string, string>;
    valueLabel?: string;
    detail?: (index: number) => string | null;
}>();

type BarPoint = { index: number; value: number };
const chartData = computed<BarPoint[]>(() =>
    props.rows.map((row, index) => ({
        index,
        value: row.value ?? 0,
    })),
);
const chartConfig = computed(() =>
    socialAccountChartConfig(
        props.rows.map((row) => row.account),
        props.colors,
    ),
);
const chartHeight = computed(
    () => `${Math.max(160, props.rows.length * 37 + 60)}px`,
);
const indexAccessor = (point: BarPoint): number => point.index;
const valueAccessor = (point: BarPoint): number => point.value;
const colorAccessor = (point: BarPoint): string => {
    const key = props.rows[point.index]?.account.social_account_key;
    return (key && props.colors[key]) || accountColor(point.index);
};
const formatAccount = (tick: number | Date): string => {
    const index = typeof tick === 'number' ? Math.round(tick) : 0;
    const account = props.rows[index]?.account;
    if (!account) return '';

    const label = account.username
        ? `@${account.username}`
        : account.name || getPlatformLabel(account.platform);
    return label.length > 12 ? `${label.slice(0, 11)}…` : label;
};
const categoryTicks = computed(() => props.rows.map((_, index) => index));
const detailFormatter = (key: string): string | null =>
    props.detail?.(Number(key.replace('account_', ''))) ?? null;
const tooltipTemplate = computed(() =>
    componentToString(chartConfig.value, ChartTooltipContent, {
        detailFormatter,
    }),
);
const tooltipTriggers = computed(() => ({
    [VisStackedBarSelectors.bar]: (bar: {
        datum: BarPoint;
    }): string | undefined => {
        const point = bar.datum;
        return tooltipTemplate.value?.({
            [`account_${point.index}`]: props.rows[point.index]?.value,
        });
    },
}));
const tickAttributes = {
    [VisAxisSelectors.tick]: {
        'data-account-tick': (tick: number): string => String(Math.round(tick)),
    },
};
const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
const LOGO_SIZE = 12;
const decorateTicks = (svg: SVGSVGElement): void => {
    svg.querySelectorAll('[data-account-logo]').forEach((logo) =>
        logo.remove(),
    );
    svg.querySelectorAll<SVGGElement>('g[data-account-tick]').forEach(
        (tick) => {
            const account =
                props.rows[Number(tick.getAttribute('data-account-tick'))]
                    ?.account;

            if (!account) {
                return;
            }

            const logo = document.createElementNS(SVG_NAMESPACE, 'image');
            logo.setAttribute('href', getPlatformLogo(account.platform));
            logo.setAttribute('x', String(-LOGO_SIZE - 6));
            logo.setAttribute('y', String(-LOGO_SIZE / 2));
            logo.setAttribute('width', String(LOGO_SIZE));
            logo.setAttribute('height', String(LOGO_SIZE));
            logo.setAttribute('data-account-logo', '');
            logo.setAttribute('data-testid', 'analytics-axis-logo');
            logo.setAttribute('aria-label', getPlatformLabel(account.platform));
            tick.appendChild(logo);
        },
    );
};
const barAttributes = {
    [VisStackedBarSelectors.bar]: { 'data-testid': 'analytics-account-bar' },
};
</script>

<template>
    <ChartContainer
        :config="chartConfig"
        class="w-full"
        :style="{ height: chartHeight }"
        data-testid="accounts-unovis-bar-chart"
    >
        <VisXYContainer
            :data="chartData"
            y-direction="south"
            :padding="{ top: 12, right: 16, bottom: 0, left: 0 }"
            :on-render-complete="decorateTicks"
        >
            <VisStackedBar
                :x="indexAccessor"
                :y="valueAccessor"
                :color="colorAccessor"
                orientation="horizontal"
                :rounded-corners="2"
                :bar-padding="0.27"
                :bar-max-width="27"
                :attributes="barAttributes"
            />
            <VisAxis
                type="x"
                :tick-format="formatCountTick"
                :num-ticks="5"
                :label="valueLabel"
                label-font-size="12px"
                label-color="var(--muted-foreground)"
                :grid-line="true"
                :domain-line="false"
                :tick-line="false"
            />
            <VisAxis
                type="y"
                :tick-format="formatAccount"
                :tick-values="categoryTicks"
                :tick-padding="LOGO_SIZE + 12"
                :attributes="tickAttributes"
                :grid-line="false"
                :domain-line="false"
                :tick-line="false"
            />
            <ChartTooltip :triggers="tooltipTriggers" />
        </VisXYContainer>
    </ChartContainer>
</template>
