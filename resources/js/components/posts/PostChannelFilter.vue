<script setup lang="ts">
import { IconLayoutGrid } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import MultiSelectFilter from '@/components/MultiSelectFilter.vue';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';

interface Channel {
    id: string;
    platform: string;
    display_label: string;
    username: string;
    avatar_url: string | null;
}

const props = defineProps<{ channels: Channel[] }>();
const selectedIds = defineModel<string[]>({ required: true });

const options = computed(() =>
    props.channels.map((channel) => ({
        id: channel.id,
        label: channel.display_label,
        searchText: `${channel.display_label} ${channel.username} ${getPlatformLabel(channel.platform)}`,
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
        :label="trans('posts.filter_by_channel')"
        :search-placeholder="trans('posts.channel_search_placeholder')"
        :empty-message="trans('posts.no_channels')"
        :select-all-label="trans('posts.composer.select_all')"
        :deselect-all-label="trans('posts.composer.deselect_all')"
        test-id="posts-channel"
        content-class="w-96"
    >
        <template #icon>
            <IconLayoutGrid class="size-4" />
        </template>
        <template #option="{ option }">
            <span class="flex min-w-0 items-center gap-3">
                <span class="relative size-8 shrink-0">
                    <img
                        :src="
                            channelFor(option.id).avatar_url ??
                            getPlatformLogo(channelFor(option.id).platform)
                        "
                        alt=""
                        class="size-8 rounded-md object-cover"
                    />
                    <img
                        v-if="channelFor(option.id).avatar_url"
                        :src="getPlatformLogo(channelFor(option.id).platform)"
                        alt=""
                        class="absolute -right-1 -bottom-1 size-4 rounded-full border border-background bg-background"
                    />
                </span>
                <span class="min-w-0 truncate">{{ option.label }}</span>
            </span>
        </template>
    </MultiSelectFilter>
</template>
