<script setup lang="ts">
import CalendarDayDots from '@/components/publish/CalendarDayDots.vue';
import dayjs from '@/dayjs';
import type { CalendarPost } from '@/types/publish';

defineProps<{
    days: dayjs.Dayjs[];
    posts: Record<string, CalendarPost[]>;
    selectedKey: string;
    todayKey: string;
}>();

const emit = defineEmits<{
    select: [key: string];
}>();

const dayKey = (day: dayjs.Dayjs): string => day.format('YYYY-MM-DD');

const select = (day: dayjs.Dayjs): void => {
    emit('select', dayKey(day));
};
</script>

<template>
    <div class="grid grid-cols-7 gap-1 px-2 py-2" data-testid="calendar-week-strip">
        <button
            v-for="day in days"
            :key="dayKey(day)"
            type="button"
            class="flex min-w-0 flex-col items-center gap-1 rounded-lg hover:bg-secondary active:bg-secondary py-1.5 transition-control focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
            :aria-pressed="selectedKey === dayKey(day)"
            :aria-label="day.format('dddd, LL')"
            :data-testid="`calendar-strip-day-${dayKey(day)}`"
            @click="select(day)"
        >
            <span
                class="w-full truncate text-center text-xs font-medium capitalize"
                :class="
                    selectedKey === dayKey(day) || todayKey === dayKey(day)
                        ? 'text-primary-text'
                        : 'text-muted-foreground'
                "
                >{{ day.format('dd') }}</span
            >
            <span
                class="inline-flex size-8 items-center justify-center rounded-full text-sm font-medium tabular-nums"
                :class="{
                    'bg-primary text-primary-foreground':
                        selectedKey === dayKey(day),
                    'text-primary-text ring-1 ring-primary-text ring-inset':
                        selectedKey !== dayKey(day) && todayKey === dayKey(day),
                    'text-foreground':
                        selectedKey !== dayKey(day) && todayKey !== dayKey(day),
                }"
                >{{ day.format('D') }}</span
            >
            <CalendarDayDots :posts="posts[dayKey(day)] ?? []" />
        </button>
    </div>
</template>
