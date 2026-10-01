<script setup lang="ts">
import {
    IconHeart,
    IconMessageCircle,
    IconPlus,
    IconRepeat,
    IconSend,
} from '@tabler/icons-vue';

import PreviewTextPost from '@/components/posts/previews/PreviewTextPost.vue';

import type { PreviewProps } from './types';

defineProps<PreviewProps>();
</script>

<template>
    <PreviewTextPost
        data-testid="threads-preview"
        :account="socialAccount"
        :content="content"
        :media="media"
        :name="socialAccount.username || socialAccount.handle_label"
        link-tone="info"
        host-only
        tags="bare"
        media-layout="peek"
        mute-badge
        link-card
        actions-style="start"
        :actions="[
            { icon: IconHeart },
            { icon: IconMessageCircle },
            { icon: IconRepeat },
            { icon: IconSend },
        ]"
    >
        <template #avatar-badge>
            <span
                class="absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-full bg-foreground text-background ring-2 ring-card"
            >
                <IconPlus class="size-2.5" stroke-width="3" />
            </span>
        </template>
    </PreviewTextPost>
</template>
