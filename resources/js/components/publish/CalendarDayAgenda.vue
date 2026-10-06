<script setup lang="ts">
import { IconCalendarPlus, IconPlus } from '@tabler/icons-vue';
import { computed } from 'vue';

import CalendarPostChip from '@/components/publish/CalendarPostChip.vue';
import CalendarSlotChip from '@/components/publish/CalendarSlotChip.vue';
import { Button } from '@/components/ui/button';
import date from '@/date';
import dayjs from '@/dayjs';
import type { CalendarItem } from '@/lib/calendarItems';
import type { PublishSocialAccount } from '@/types/publish';

const props = defineProps<{
    day: dayjs.Dayjs;
    items: CalendarItem[];
    channels: Record<string, PublishSocialAccount>;
    timezone: string;
    canCreatePost: boolean;
    isPast: boolean;
}>();

const emit = defineEmits<{
    compose: [];
}>();

const dayKey = computed(() => props.day.format('YYYY-MM-DD'));

const hourGroups = computed(() => {
    const groups: { hour: number; label: string; items: CalendarItem[] }[] =
        [];

    for (const item of props.items) {
        const local = dayjs.utc(item.at).tz(props.timezone);
        const last = groups[groups.length - 1];

        if (last && last.hour === local.hour()) {
            last.items.push(item);
            continue;
        }

        groups.push({
            hour: local.hour(),
            label: date.formatTimeInTimezone(
                local.startOf('hour').utc().format(),
                props.timezone,
            ),
            items: [item],
        });
    }

    return groups;
});

const compose = (): void => {
    emit('compose');
};
</script>

<template>
    <section
        class="flex flex-col"
        :aria-labelledby="`calendar-agenda-title-${dayKey}`"
        :data-testid="`calendar-agenda-${dayKey}`"
    >
        <header
            class="flex h-12 shrink-0 items-center justify-between gap-3 px-4"
        >
            <h3
                :id="`calendar-agenda-title-${dayKey}`"
                class="min-w-0 truncate font-heading text-sm font-medium text-foreground capitalize"
                data-testid="calendar-agenda-title"
            >
                {{ day.format('dddd, D MMMM') }}
            </h3>
            <Button
                v-if="canCreatePost && !isPast"
                variant="outline"
                size="icon"
                class="shrink-0"
                :aria-label="$t('calendar.new_post')"
                :data-testid="`calendar-agenda-add-${dayKey}`"
                @click="compose"
            >
                <IconPlus class="size-4" />
            </Button>
        </header>

        <ol
            v-if="hourGroups.length"
            class="flex flex-col pb-4"
            data-testid="calendar-agenda-list"
        >
            <li
                v-for="group in hourGroups"
                :key="group.hour"
                class="flex gap-3 border-t border-border px-4 py-2.5"
            >
                <span
                    class="w-16 shrink-0 pt-1.5 text-xs font-medium whitespace-nowrap text-muted-foreground tabular-nums"
                    >{{ group.label }}</span
                >
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <template v-for="item in group.items" :key="item.key">
                        <CalendarPostChip
                            v-if="item.post"
                            :post="item.post"
                            :timezone="timezone"
                            layout="week"
                            class="w-full"
                        />
                        <CalendarSlotChip
                            v-else-if="item.slot"
                            :posting-slot="item.slot"
                            :channel="channels[item.slot.channel_id] ?? null"
                            :timezone="timezone"
                            :can-create-post="canCreatePost"
                            layout="agenda"
                        />
                    </template>
                </div>
            </li>
        </ol>

        <div
            v-else
            class="flex flex-col items-center gap-2 border-t border-border px-4 py-10 text-center"
            data-testid="calendar-agenda-empty"
        >
            <IconCalendarPlus
                class="size-6 text-subtle-foreground"
                aria-hidden="true"
            />
            <p class="text-sm text-muted-foreground">
                {{ $t('calendar.empty_day') }}
            </p>
        </div>
    </section>
</template>
