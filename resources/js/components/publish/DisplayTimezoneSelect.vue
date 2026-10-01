<script setup lang="ts">
import { computed } from 'vue';

import TimezoneSelect from '@/components/TimezoneSelect.vue';
import type { TimezoneOption } from '@/types/posting-schedule';

const props = defineProps<{
    options: TimezoneOption[];
    userTimezone: string;
    channelTimezones: string[];
}>();

const model = defineModel<string>({ required: true });

const groups = computed(() => {
    const pick = (values: string[]): TimezoneOption[] =>
        props.options.filter((option) => values.includes(option.value));
    const listed = [props.userTimezone, ...props.channelTimezones];

    return [
        { labelKey: 'posts.publish.timezone.yours', options: pick([props.userTimezone]) },
        { labelKey: 'posts.publish.timezone.channels', options: pick(props.channelTimezones) },
        {
            labelKey: 'posts.publish.timezone.all',
            options: props.options.filter((option) => !listed.includes(option.value)),
        },
    ];
});
</script>

<template>
    <div
        class="shrink-0"
        data-testid="publish-timezone-select"
        role="group"
        :aria-label="$t('posts.publish.timezone.label')"
    >
        <TimezoneSelect
            v-model="model"
            :options="options"
            :groups="groups"
            testid="publish-timezone"
            variant="ghost"
            compact
        />
    </div>
</template>
