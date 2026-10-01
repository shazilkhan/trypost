<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    IconExternalLink,
    IconFileTypePdf,
    IconLoader2,
    IconPlayerPlayFilled,
    IconX,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, ref, watch } from 'vue';

import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import PostPlatformMetrics from '@/components/posts/PostPlatformMetrics.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePostEcho } from '@/composables/echo/usePostEcho';
import {
    getContentTypeBadgeKey,
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import {
    getPlatformStatusConfig,
    getPostStatusConfig,
    isActivelyPublishing,
} from '@/composables/usePostStatus';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    classify,
    isDocument as isDocumentItem,
    isVideo as isVideoItem,
    MediaType,
} from '@/lib/mediaType';
import { index as postsIndex } from '@/routes/app/posts';
import type {
    PublicationAnalyticsDetail,
    UnsupportedPublicationAnalytics,
} from '@/types/analytics';
import type { MediaItem } from '@/types/media';
import { PostPlatformStatus, PostStatus } from '@/types/post';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    platform: string;
    display_name: string;
    display_username: string | null;
    display_avatar: string | null;
    content_type: string | null;
    status:
        | 'pending'
        | 'publishing'
        | 'published'
        | 'failed'
        | 'retrying'
        | 'rejected'
        | 'pending_review';
    platform_url: string | null;
    error_message: string | null;
    published_at: string | null;
    enabled: boolean;
    social_account: SocialAccount | null;
}

interface Post {
    id: string;
    content: string;
    media: MediaItem[];
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    platforms: PostPlatform[];
    labels?: { id: string; name: string; color: string }[];
}

interface Workspace {
    id: string;
    name: string;
}

const props = defineProps<{
    workspace: Workspace;
    post: Post;
    postMetrics: Record<
        string,
        PublicationAnalyticsDetail | UnsupportedPublicationAnalytics
    >;
}>();

const awaitingMetrics = computed(() =>
    Object.values(props.postMetrics).some(
        (result) =>
            ('available' in result && result.snapshot === null) ||
            ('unsupported' in result && result.reason === 'not_collected'),
    ),
);
const { start: startMetricsPolling, stop: stopMetricsPolling } = usePoll(
    10000,
    { only: ['postMetrics'] },
    { autoStart: false },
);

onMounted(() => {
    if (awaitingMetrics.value) startMetricsPolling();
});

watch(awaitingMetrics, (waiting) => {
    if (waiting) startMetricsPolling();
    else stopMetricsPolling();
});

const enabledPlatforms = computed(() =>
    props.post.platforms
        .filter((pp) => pp.enabled)
        .map((pp) => ({
            ...pp,
            contentTypeBadgeKey: getContentTypeBadgeKey(
                pp.platform,
                pp.content_type,
            ),
        })),
);

const isPublishing = computed(() =>
    isActivelyPublishing(props.post.status, props.post.platforms),
);

const postStatus = computed(() => getPostStatusConfig(props.post.status));

const pageTitle = computed(() => {
    const snippet =
        props.post.content?.trim().split('\n')[0]?.slice(0, 60) ?? '';
    return snippet
        ? `${trans('posts.show.title')} · ${snippet}${props.post.content.length > 60 ? '…' : ''}`
        : trans('posts.show.title');
});

const getDisplayName = (pp: PostPlatform): string =>
    pp.display_name ?? pp.platform;

const getDisplayAvatar = (pp: PostPlatform): string | null => pp.display_avatar;

const formatDateTime = (value: string | null): string =>
    value ? date.formatDateTime(value) : '';

const lightbox = ref<InstanceType<typeof ImagePreviewDialog> | null>(null);

const openLightbox = (i: number) => {
    const collection = props.post.media.map((m) => ({
        url: m.url,
        type: classify(m) ?? MediaType.Image,
    }));
    lightbox.value?.openCollection(collection, i);
};

usePostEcho(props.post.id, '.post.platform.status.updated', () => {
    router.reload({ only: ['post', 'postMetrics'] });
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout full-width>
        <!-- Publishing state: clean centered loader, nothing else visible. -->
        <div
            v-if="isPublishing"
            class="flex flex-1 flex-col items-center justify-center gap-4 p-6"
        >
            <div
                class="inline-flex size-14 items-center justify-center rounded-xl border border-border bg-muted shadow-xs"
            >
                <IconLoader2
                    class="size-7 animate-spin text-foreground"
                    stroke-width="2"
                />
            </div>
            <p
                class="text-2xl leading-tight font-semibold text-foreground"
                style="font-family: var(--font-display)"
            >
                {{ $t('posts.edit.publishing_overlay_title') }}
            </p>
            <p class="max-w-md text-center text-sm text-foreground/70">
                {{ $t('posts.edit.publishing_overlay_subtitle') }}
            </p>
        </div>

        <div v-else class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto w-full max-w-[680px] px-4 py-6 md:py-10">
                <article
                    class="overflow-hidden rounded-2xl border border-border bg-card"
                    data-testid="post-details"
                >
                    <header
                        class="flex min-h-12 items-center justify-between gap-2 border-b border-border-strong py-1 ps-6 pe-4"
                    >
                        <p
                            class="flex min-w-0 flex-wrap items-center gap-2 text-sm text-foreground"
                        >
                            <span v-if="post.published_at">
                                {{
                                    $t('posts.show.published_on', {
                                        date: formatDateTime(post.published_at),
                                    })
                                }}
                            </span>
                            <span v-else-if="post.scheduled_at">
                                {{
                                    $t('posts.show.scheduled_for', {
                                        date: formatDateTime(post.scheduled_at),
                                    })
                                }}
                            </span>
                            <span v-else>{{ $t('posts.show.draft') }}</span>
                            <Badge
                                v-if="post.status !== PostStatus.Published"
                                :variant="postStatus.variant"
                                class="h-6 gap-1 px-2 [&>svg]:size-4"
                            >
                                <component :is="postStatus.icon" />
                                {{ postStatus.label }}
                            </Badge>
                        </p>
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            class="shrink-0"
                        >
                            <Link
                                :href="postsIndex.url()"
                                :aria-label="$t('posts.show.back')"
                                :title="$t('posts.show.back')"
                                data-testid="post-details-close"
                            >
                                <IconX class="size-4" />
                            </Link>
                        </Button>
                    </header>

                    <section class="flex flex-col gap-3 px-6 py-4">
                        <div
                            v-for="pp in enabledPlatforms"
                            :key="pp.id"
                            class="flex items-center gap-3"
                        >
                            <span class="relative inline-flex size-8 shrink-0">
                                <Avatar
                                    :src="getDisplayAvatar(pp)"
                                    :name="getDisplayName(pp)"
                                    class="size-8 rounded-lg"
                                />
                                <img
                                    :src="getPlatformLogo(pp.platform)"
                                    :alt="getPlatformLabel(pp.platform)"
                                    class="absolute -right-1.5 -bottom-1 size-4.5 rounded-md border border-card bg-card"
                                />
                            </span>
                            <p
                                class="min-w-0 truncate text-sm leading-tight font-emphasis text-foreground"
                            >
                                {{ getDisplayName(pp) }}
                            </p>
                            <Badge
                                v-if="pp.contentTypeBadgeKey"
                                variant="secondary"
                                class="ms-auto h-6 px-2"
                                :data-testid="`content-type-${pp.content_type}`"
                            >
                                {{ $t(pp.contentTypeBadgeKey) }}
                            </Badge>
                            <Badge
                                v-if="pp.status !== PostPlatformStatus.Published"
                                :variant="getPlatformStatusConfig(pp.status).variant"
                                class="h-6 gap-1 px-2 [&>svg]:size-4"
                                :class="pp.contentTypeBadgeKey ? '' : 'ms-auto'"
                            >
                                <component
                                    :is="getPlatformStatusConfig(pp.status).icon"
                                    :class="
                                        pp.status ===
                                        PostPlatformStatus.Publishing
                                            ? 'animate-spin'
                                            : ''
                                    "
                                />
                                {{ getPlatformStatusConfig(pp.status).label }}
                            </Badge>
                        </div>
                        <p
                            v-if="post.content"
                            class="text-sm break-words whitespace-pre-wrap text-foreground"
                        >
                            {{ post.content }}
                        </p>
                        <p
                            v-if="enabledPlatforms.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ $t('posts.show.no_platforms') }}
                        </p>
                        <div
                            v-if="post.labels && post.labels.length > 0"
                            class="flex flex-wrap gap-1.5"
                        >
                            <LabelBadge
                                v-for="label in post.labels"
                                :key="label.id"
                                :label="label"
                            />
                        </div>
                    </section>

                    <div
                        v-if="post.media.length > 0"
                        class="flex flex-wrap gap-1 px-6 pt-3 pb-4"
                    >
                        <button
                            v-for="(item, i) in post.media"
                            :key="item.id"
                            type="button"
                            class="relative size-[180px] max-w-[calc(50%-2px)] cursor-zoom-in overflow-hidden rounded-md border border-border-strong bg-secondary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring sm:max-w-none"
                            @click="openLightbox(i)"
                        >
                            <template v-if="isVideoItem(item)">
                                <video
                                    :src="item.url"
                                    class="size-full object-cover"
                                    muted
                                    playsinline
                                    preload="metadata"
                                />
                                <IconPlayerPlayFilled
                                    aria-hidden="true"
                                    class="absolute bottom-2 left-2 size-8 rounded-full bg-black/60 p-2 text-white"
                                />
                            </template>
                            <span
                                v-else-if="isDocumentItem(item)"
                                class="flex size-full flex-col items-center justify-center gap-1.5 p-2 text-center"
                            >
                                <IconFileTypePdf
                                    class="size-8 text-muted-foreground"
                                />
                                <span
                                    class="line-clamp-2 text-xs break-all text-muted-foreground"
                                    >{{ item.original_filename || 'PDF' }}</span
                                >
                            </span>
                            <img
                                v-else
                                :src="item.url"
                                :alt="item.original_filename"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                        </button>
                    </div>

                    <template v-for="pp in enabledPlatforms" :key="pp.id">
                        <p
                            v-if="pp.status === PostPlatformStatus.PendingReview"
                            class="border-t border-border-strong bg-amber-50 px-6 py-3 text-sm text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                            data-testid="google-business-pending-review"
                        >
                            {{ $t('posts.show.pending_review') }}
                        </p>
                        <p
                            v-if="
                                (pp.status === PostPlatformStatus.Failed ||
                                    pp.status ===
                                        PostPlatformStatus.Rejected) &&
                                pp.error_message
                            "
                            class="border-t border-border-strong px-6 py-3 text-sm text-destructive-text"
                        >
                            {{ pp.error_message }}
                        </p>
                        <PostPlatformMetrics
                            v-if="pp.status === PostPlatformStatus.Published"
                            :detail="postMetrics[pp.id]"
                        />
                        <footer
                            v-if="pp.status === PostPlatformStatus.Published"
                            class="flex min-h-14 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-border-strong px-6 py-3"
                        >
                            <p
                                class="inline-flex min-w-0 items-center gap-0.5 text-sm text-foreground"
                            >
                                {{ $t('posts.publish.published_via') }}
                                <img
                                    :src="getPlatformLogo(pp.platform)"
                                    alt=""
                                    class="ms-0.5 size-4 rounded-sm"
                                />
                                {{ getPlatformLabel(pp.platform) }}
                            </p>
                            <Button
                                v-if="pp.platform_url"
                                as="a"
                                :href="pp.platform_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                variant="outline"
                                :title="$t('posts.show.view_on_platform')"
                            >
                                <IconExternalLink class="size-4" />
                                {{ $t('posts.publish.actions.view_post') }}
                            </Button>
                        </footer>
                    </template>
                </article>
            </div>
        </div>

        <ImagePreviewDialog ref="lightbox" />
    </AppLayout>
</template>
