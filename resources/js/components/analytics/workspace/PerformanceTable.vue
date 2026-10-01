<script setup lang="ts">
import {
    IconChevronDown,
    IconTrendingDown,
    IconTrendingUp,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import date from '@/date';
import {
    formatNumberCompact,
    formatPercent,
    formatPercentChange,
} from '@/lib/utils';
import type {
    PerformanceRow,
    WorkspaceAnalyticsReport,
} from '@/types/analytics';

import AccountIdentity from './AccountIdentity.vue';
import AnalyticsSection from './AnalyticsSection.vue';

const props = defineProps<{
    rows: WorkspaceAnalyticsReport['performance'];
    range: WorkspaceAnalyticsReport['range'];
    previousRange: WorkspaceAnalyticsReport['previous_range'];
    filtered?: boolean;
}>();
const span = (range: { start: string; end: string }): string =>
    `${date.formatDayMonthYear(range.start)} – ${date.formatDayMonthYear(range.end)}`;
const sortBy = ref<'posts' | 'reactions' | 'comments' | 'engagement_rate'>(
    'posts',
);
const sorted = computed(() =>
    [...props.rows].sort(
        (a, b) =>
            (b[sortBy.value].value ?? -1) - (a[sortBy.value].value ?? -1) ||
            a.social_account_key.localeCompare(b.social_account_key),
    ),
);
const columns = ['posts', 'reactions', 'comments', 'engagement_rate'] as const;
const display = (
    row: PerformanceRow,
    key: (typeof columns)[number],
): string => {
    const value = row[key].value;
    return value === null
        ? '—'
        : key === 'engagement_rate'
          ? formatPercent(value)
          : formatNumberCompact(value);
};
const change = (row: PerformanceRow, key: (typeof columns)[number]): string => {
    const value = row[key].change;
    return value === null ? '—' : formatPercentChange(value);
};
</script>

<template>
    <AnalyticsSection
        :title="$t('analytics.dashboard.performance')"
        :subtitle="
            $t('analytics.ranges.compared_to', {
                current: span(range),
                previous: span(previousRange),
            })
        "
        subtitle-testid="analytics-performance-caption"
    >
        <div
            v-if="rows.length === 0"
            data-testid="analytics-performance-empty"
            class="rounded-lg border border-dashed border-border-strong bg-card px-5 py-10 text-center text-sm text-muted-foreground"
        >
            {{
                $t(
                    filtered
                        ? 'analytics.dashboard.filtered_no_posts'
                        : 'analytics.dashboard.no_performance',
                )
            }}
        </div>
        <div
            v-else
            class="min-w-0 overflow-x-auto rounded-lg border border-border bg-card"
        >
            <Table class="min-w-[720px]">
                <TableHeader>
                    <TableRow class="border-border-strong hover:bg-transparent">
                        <TableHead
                            class="h-12 border-r-0 px-4 py-3 leading-[17.5px]"
                        >
                            {{ $t('analytics.dashboard.channel') }}
                        </TableHead>
                        <TableHead
                            v-for="column in columns"
                            :key="column"
                            class="h-12 w-[14%] border-r-0 px-4 py-3 leading-[17.5px]"
                            :aria-sort="
                                sortBy === column ? 'descending' : 'none'
                            "
                        >
                            <button
                                type="button"
                                class="group inline-flex cursor-pointer items-center gap-1 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                @click="sortBy = column"
                            >
                                {{ $t(`analytics.dashboard.${column}`) }}
                                <IconChevronDown
                                    :class="[
                                        'size-3 transition-opacity',
                                        sortBy === column
                                            ? 'opacity-100'
                                            : 'opacity-30 group-hover:opacity-60',
                                    ]"
                                    aria-hidden="true"
                                />
                            </button>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="row in sorted"
                        :key="row.social_account_key"
                        class="border-border-strong last:border-b-0"
                    >
                        <th
                            scope="row"
                            class="border-r-0 px-4 py-3 text-left font-normal"
                        >
                            <AccountIdentity :account="row" with-avatar />
                        </th>
                        <TableCell
                            v-for="column in columns"
                            :key="column"
                            class="border-r-0 px-4 py-3 tabular-nums"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="text-sm text-foreground">{{
                                    display(row, column)
                                }}</span>
                                <span
                                    v-if="row[column].change !== null"
                                    class="inline-flex items-center gap-1 text-xs text-foreground"
                                >
                                    <IconTrendingUp
                                        v-if="row[column].change! >= 0"
                                        class="size-4 shrink-0 text-success-text"
                                        aria-hidden="true"
                                    />
                                    <IconTrendingDown
                                        v-else
                                        class="size-4 shrink-0 text-destructive-text"
                                        aria-hidden="true"
                                    />
                                    {{ change(row, column) }}
                                </span>
                            </span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AnalyticsSection>
</template>
