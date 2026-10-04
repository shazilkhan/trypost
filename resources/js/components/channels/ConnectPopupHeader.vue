<script setup lang="ts">
import { IconArrowsLeftRight } from '@tabler/icons-vue';

import AppLogo from '@/components/AppLogo.vue';
import PlatformLogo from '@/components/PlatformLogo.vue';

defineProps<{
    platform: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col items-center gap-5 text-center"
        data-testid="connect-popup-header"
    >
        <div class="flex items-center gap-3" aria-hidden="true">
            <AppLogo variant="mark" class="text-[28px]" />
            <IconArrowsLeftRight class="size-4 text-muted-foreground" />
            <PlatformLogo :platform="platform" :size="32" />
        </div>
        <div class="flex flex-col gap-2">
            <h1 class="text-xl leading-tight font-medium text-foreground">
                {{ title }}
            </h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
    </header>
</template>
