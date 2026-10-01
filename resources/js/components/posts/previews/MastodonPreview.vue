<script setup lang="ts">
import {
    IconArrowBackUp,
    IconBookmark,
    IconDots,
    IconRepeat,
    IconStar,
    IconWorld,
} from '@tabler/icons-vue';

import PreviewTextPost from '@/components/posts/previews/PreviewTextPost.vue';
import date from '@/date';

import type { PreviewProps } from './types';

defineProps<PreviewProps>();
</script>

<template>
    <PreviewTextPost
        data-testid="mastodon-preview"
        variant="stacked"
        :account="socialAccount"
        :content="content"
        :media="media"
        :name="socialAccount.display_label"
        :handle="`@${socialAccount.username || socialAccount.handle_label}`"
        avatar-class="size-[46px]"
        avatar-square
        link-tone="info"
        host-only
        tags="muted"
        link-card
        :link-card-options="{ band: true, titleFirst: true }"
        :actions="[
            { icon: IconArrowBackUp },
            { icon: IconRepeat },
            { icon: IconStar },
            { icon: IconBookmark },
            { icon: IconDots },
        ]"
        action-class="text-foreground"
    >
        <template #aside>
            <span
                class="flex shrink-0 items-center gap-1 self-start pt-1 text-muted-foreground"
            >
                <IconWorld class="size-3.5" />
                {{ date.formatPreviewPostedAt(postedAt, $t('common.just_now')) }}
            </span>
        </template>
    </PreviewTextPost>
</template>
