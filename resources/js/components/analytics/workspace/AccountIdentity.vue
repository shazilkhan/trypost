<script setup lang="ts">
import { computed } from 'vue';

import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import type { AccountIdentityData } from '@/types/analytics';

const props = defineProps<{
    account: AccountIdentityData;
    withAvatar?: boolean;
    color?: string;
}>();
const label = computed(() =>
    props.account.username
        ? `@${props.account.username}`
        : props.account.name || getPlatformLabel(props.account.platform),
);
</script>

<template>
    <span
        class="inline-flex min-w-0 items-center"
        :class="withAvatar ? 'gap-3' : 'gap-2'"
    >
        <span
            v-if="color"
            data-testid="analytics-legend-swatch"
            class="-mr-0.5 size-2.5 shrink-0 rounded-[3px]"
            :style="{ backgroundColor: color }"
        />
        <span
            v-if="withAvatar"
            class="relative inline-flex size-8 shrink-0"
        >
            <img
                :src="account.avatar_url ?? getPlatformLogo(account.platform)"
                :alt="getPlatformLabel(account.platform)"
                class="size-full rounded-lg object-cover"
            />
            <img
                v-if="account.avatar_url"
                :src="getPlatformLogo(account.platform)"
                alt=""
                class="absolute -right-1.5 -bottom-1 size-4.5 rounded-md border border-card bg-card"
            />
        </span>
        <img
            v-else
            :src="getPlatformLogo(account.platform)"
            :alt="getPlatformLabel(account.platform)"
            class="size-4 shrink-0 rounded-sm object-contain"
        />
        <span
            class="truncate text-sm text-foreground"
            :title="`${label} · ${getPlatformLabel(account.platform)}`"
            >{{ label }}</span
        >
        <span class="sr-only">{{ getPlatformLabel(account.platform) }}</span>
    </span>
</template>
