<script setup lang="ts">
import { IconPlus } from '@tabler/icons-vue';
import { computed } from 'vue';

import { openPostComposer } from '@/composables/useGlobalPostComposer';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import date from '@/date';
import dayjs from '@/dayjs';
import type { CalendarSlot, PublishSocialAccount } from '@/types/publish';

const props = defineProps<{
    postingSlot: CalendarSlot;
    channel: PublishSocialAccount | null;
    timezone: string;
    canCreatePost: boolean;
}>();

const time = computed(() =>
    date.formatTimeInTimezone(props.postingSlot.at, props.timezone),
);

const testKey = computed(
    () => `${props.postingSlot.channel_id}-${dayjs.utc(props.postingSlot.at).unix()}`,
);

const newPost = (): void => {
    openPostComposer({ socialAccountIds: [props.postingSlot.channel_id] });
};
</script>

<template>
    <component
        :is="canCreatePost ? 'button' : 'div'"
        :type="canCreatePost ? 'button' : undefined"
        class="flex h-7 min-w-0 shrink-0 items-center gap-1 rounded-lg border border-dashed border-border-strong px-1 text-xs font-medium text-muted-foreground transition-control enabled:hover:bg-secondary enabled:hover:text-foreground"
        :title="channel?.display_label"
        :aria-label="
            channel
                ? $t('posts.publish.slot_aria', {
                      channel: channel.display_label,
                      network: getPlatformLabel(channel.platform),
                      time,
                  })
                : undefined
        "
        :data-testid="`calendar-posting-slot-${testKey}`"
        @click="canCreatePost ? newPost() : undefined"
    >
        <img
            v-if="channel"
            :src="getPlatformLogo(channel.platform)"
            alt=""
            class="size-4 shrink-0 rounded-sm"
        />
        <span class="shrink-0">{{ time }}</span>
        <template v-if="canCreatePost">
            <IconPlus class="size-3.5 shrink-0" />
            <span class="truncate">{{ $t('posts.publish.new_in_slot') }}</span>
        </template>
    </component>
</template>
