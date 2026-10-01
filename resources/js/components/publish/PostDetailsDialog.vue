<script setup lang="ts">
import { computed } from 'vue';

import PlatformPreview from '@/components/posts/previews/PlatformPreview.vue';
import type { PreviewAccount } from '@/components/posts/previews/types';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import {
    getPlatformStatusConfig,
    getPostStatusConfig,
} from '@/composables/usePostStatus';
import date from '@/date';
import dayjs from '@/dayjs';
import type { PostCard, PostCardPlatform } from '@/types/publish';

const props = defineProps<{
    post: PostCard;
    testKey: string;
    timezone: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const targets = computed(() =>
    props.post.post_platforms.filter((target) => target.enabled),
);

const moment = computed(() => {
    if (props.post.published_at) {
        return {
            key: 'posts.show.published_on',
            at: props.post.published_at,
        };
    }

    if (props.post.scheduled_at) {
        return {
            key: 'posts.show.scheduled_for',
            at: props.post.scheduled_at,
        };
    }

    return null;
});

const postedAt = computed(() =>
    moment.value
        ? dayjs.utc(moment.value.at).tz(props.timezone).format('YYYY-MM-DDTHH:mm')
        : null,
);

const previewAccount = (target: PostCardPlatform): PreviewAccount | null => {
    const account = target.social_account;

    if (!account) {
        return null;
    }

    return {
        id: account.id,
        platform: account.platform,
        display_name: account.display_name ?? '',
        username: account.username,
        display_label: account.display_label,
        handle_label: account.handle_label ?? account.username,
        avatar_url: account.avatar_url,
    };
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="sm:max-w-xl"
            :data-testid="`post-details-${testKey}`"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('posts.show.title') }}</DialogTitle>
                <DialogDescription
                    class="flex flex-wrap items-center gap-2"
                    as="div"
                >
                    <Badge
                        :variant="getPostStatusConfig(post.status).variant"
                        class="h-6 gap-1 px-2 [&>svg]:size-4"
                        :data-testid="`post-details-status-${testKey}`"
                    >
                        <component :is="getPostStatusConfig(post.status).icon" />
                        {{ $t(`posts.status.${post.status}`) }}
                    </Badge>
                    <span
                        class="text-sm text-muted-foreground"
                        :data-testid="`post-details-time-${testKey}`"
                    >
                        {{
                            moment
                                ? $t(moment.key, {
                                      date: date.formatDateTimeInTimezone(
                                          moment.at,
                                          timezone,
                                      ),
                                  })
                                : $t('posts.publish.unscheduled')
                        }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <section
                v-for="target in targets"
                :key="target.id"
                class="flex flex-col gap-3"
                :data-testid="`post-details-target-${target.id}`"
            >
                <div class="flex items-center gap-2">
                    <img
                        :src="getPlatformLogo(target.platform)"
                        :alt="getPlatformLabel(target.platform)"
                        class="size-5 rounded-sm"
                    />
                    <p
                        class="min-w-0 flex-1 truncate text-sm font-emphasis text-foreground"
                    >
                        {{
                            target.social_account?.display_label ??
                            getPlatformLabel(target.platform)
                        }}
                    </p>
                    <Badge
                        :variant="getPlatformStatusConfig(target.status).variant"
                        class="h-6 px-2"
                    >
                        {{ $t(`posts.edit.status.${target.status}`) }}
                    </Badge>
                </div>
                <PlatformPreview
                    :platform="target.platform"
                    :social-account="previewAccount(target)"
                    :content="post.content ?? ''"
                    :media="post.media ?? []"
                    :content-type="target.content_type"
                    :meta="target.meta"
                    :posted-at="postedAt"
                />
            </section>
        </DialogContent>
    </Dialog>
</template>
