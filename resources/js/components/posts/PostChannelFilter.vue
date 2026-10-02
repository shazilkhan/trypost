<script setup lang="ts">
import { IconLayoutGrid } from '@tabler/icons-vue';
import { computed } from 'vue';

import ChannelAvatar from '@/components/ChannelAvatar.vue';
import MultiSelectFilter from '@/components/MultiSelectFilter.vue';
import {
    getPlatformLabel,
} from '@/composables/usePlatformLogo';

interface Channel {
    id: string;
    platform: string;
    display_label: string;
    username: string | null;
    avatar_url: string | null;
}

const props = withDefaults(
    defineProps<{ channels: Channel[]; testId?: string }>(),
    { testId: 'posts-channel' },
);
const selectedIds = defineModel<string[]>({ required: true });

const options = computed(() =>
    props.channels.map((channel) => ({
        id: channel.id,
        label: channel.display_label,
        searchText: `${channel.display_label} ${channel.username ?? ''} ${getPlatformLabel(channel.platform)}`,
        ariaLabel: `${channel.display_label} (${getPlatformLabel(channel.platform)})`,
    })),
);

const channelsById = computed(
    () => new Map(props.channels.map((channel) => [channel.id, channel])),
);

const channelFor = (id: string): Channel => channelsById.value.get(id)!;
</script>

<template>
    <MultiSelectFilter
        v-model="selectedIds"
        :options="options"
        :label="$t('posts.filter_by_channel')"
        :search-placeholder="$t('posts.channel_search_placeholder')"
        :empty-message="$t('posts.no_channels')"
        :select-all-label="$t('posts.composer.select_all')"
        :deselect-all-label="$t('posts.composer.deselect_all')"
        :test-id="testId"
        content-class="w-96"
    >
        <template #icon>
            <IconLayoutGrid class="size-4" />
        </template>
        <template #option="{ option }">
            <span class="flex min-w-0 items-center gap-3">
                <ChannelAvatar
                    :platform="channelFor(option.id).platform"
                    :src="channelFor(option.id).avatar_url"
                    :name="option.label"
                    ring="popover"
                />
                <span class="min-w-0 truncate">{{ option.label }}</span>
            </span>
        </template>
    </MultiSelectFilter>
</template>
