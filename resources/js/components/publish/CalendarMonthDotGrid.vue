<script setup lang="ts">
import CalendarDayDots from '@/components/publish/CalendarDayDots.vue';
import dayjs from '@/dayjs';
import type { CalendarPost } from '@/types/publish';

const props = defineProps<{
    weeks: dayjs.Dayjs[][];
    month: dayjs.Dayjs;
    posts: Record<string, CalendarPost[]>;
    selectedKey: string;
    todayKey: string;
}>();

const emit = defineEmits<{
    select: [key: string];
}>();

const dayKey = (day: dayjs.Dayjs): string => day.format('YYYY-MM-DD');

const isCurrentMonth = (day: dayjs.Dayjs): boolean =>
    day.month() === props.month.month();

const select = (day: dayjs.Dayjs): void => {
    emit('select', dayKey(day));
};
</script>

<template>
    <div class="px-2 pt-1 pb-2" data-testid="calendar-month-dots">
        <div class="grid grid-cols-7" aria-hidden="true">
            <span
                v-for="day in weeks[0]"
                :key="dayKey(day)"
                class="truncate py-2 text-center text-xs font-medium text-muted-foreground capitalize"
                >{{ day.format('dd') }}</span
            >
        </div>
        <div
            v-for="(week, index) in weeks"
            :key="index"
            class="grid grid-cols-7"
        >
            <button
                v-for="day in week"
                :key="dayKey(day)"
                type="button"
                class="flex min-w-0 flex-col items-center gap-1 rounded-lg hover:bg-secondary active:bg-secondary py-1 transition-control focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                :aria-pressed="selectedKey === dayKey(day)"
                :aria-label="day.format('dddd, LL')"
                :data-testid="`calendar-month-day-${dayKey(day)}`"
                @click="select(day)"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-full text-sm font-medium tabular-nums"
                    :class="{
                        'bg-primary text-primary-foreground':
                            selectedKey === dayKey(day),
                        'text-primary-text ring-1 ring-primary-text ring-inset':
                            selectedKey !== dayKey(day) &&
                            todayKey === dayKey(day),
                        'text-subtle-foreground':
                            selectedKey !== dayKey(day) &&
                            todayKey !== dayKey(day) &&
                            !isCurrentMonth(day),
                        'text-foreground':
                            selectedKey !== dayKey(day) &&
                            todayKey !== dayKey(day) &&
                            isCurrentMonth(day),
                    }"
                    >{{ day.format('D') }}</span
                >
                <CalendarDayDots :posts="posts[dayKey(day)] ?? []" />
            </button>
        </div>
    </div>
</template>
