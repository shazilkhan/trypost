<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconExternalLink,
    IconGripVertical,
    IconListNumbers,
    IconPencil,
    IconSend,
} from '@tabler/icons-vue';
import { computed, inject, ref, watch } from 'vue';

import {
    edit as editPostRoute,
    show as showPost,
} from '@/actions/App/Http/Controllers/App/PostController';
import PostNotesPopover from '@/components/posts/PostNotesPopover.vue';
import PostScheduleModeBadge from '@/components/posts/PostScheduleModeBadge.vue';
import PostCardLabels from '@/components/publish/PostCardLabels.vue';
import PostCardMenu from '@/components/publish/PostCardMenu.vue';
import PostDetailsDialog from '@/components/publish/PostDetailsDialog.vue';
import PostMetricsBand from '@/components/publish/PostMetricsBand.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDisplayTimezone } from '@/composables/useDisplayTimezone';
import {
    getContentTypeBadgeKey,
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import {
    deletePostCardKey,
    duplicatePostCard,
    editPostCardUrlKey,
    schedulePostCard,
} from '@/composables/usePostCardActions';
import { getPostStatusConfig } from '@/composables/usePostStatus';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import date from '@/date';
import { isImage } from '@/lib/mediaType';
import { compactPublicationMetrics } from '@/lib/publicationMetrics';
import { PostStatus, ScheduleMode } from '@/types/post';
import type {
    PostCard,
    PostCardMenuAction,
    PostCardMove,
    PublishTab,
} from '@/types/publish';

const props = withDefaults(
    defineProps<{
        post: PostCard;
        tab: PublishTab;
        displayTimezone: string;
        canMoveUp?: boolean;
        canMoveDown?: boolean;
        draggable?: boolean;
        movable?: boolean;
    }>(),
    { canMoveUp: false, canMoveDown: false, draggable: false, movable: true },
);

const emit = defineEmits<{ move: [direction: PostCardMove] }>();

const MAX_THUMBNAILS = 4;

const page = usePage();
const { canCreatePost } = useWorkspaceRole();
const deletePost = inject(deletePostCardKey, () => {});
const editUrl = inject(editPostCardUrlKey, (post: PostCard) =>
    editPostRoute.url(post.id),
);
const { timezone, formatTime } = useDisplayTimezone(props.displayTimezone, []);

watch(
    () => props.displayTimezone,
    (value) => {
        timezone.value = value;
    },
);

const testKey = computed(() => props.post.card_key ?? props.post.id);

const authUserId = computed(() => String(page.props.authUserId ?? ''));
const openPostNotesId = computed(
    () => (page.props.openPostNotesId as string | null | undefined) ?? null,
);
const highlightNoteId = computed(
    () => (page.props.highlightNoteId as string | null | undefined) ?? null,
);

const targets = computed(() =>
    props.post.post_platforms.filter((target) => target.enabled),
);
const primaryTarget = computed(() => targets.value[0] ?? null);
const account = computed(() => primaryTarget.value?.social_account ?? null);

const time = computed(() =>
    props.tab === 'sent'
        ? (props.post.published_at ?? props.post.scheduled_at)
        : props.post.scheduled_at,
);

const isEditable = computed(
    () =>
        props.post.status === PostStatus.Draft ||
        props.post.status === PostStatus.Scheduled,
);

const postUrl = computed(() =>
    isEditable.value ? editUrl(props.post) : showPost.url(props.post.id),
);

const preview = computed(() => props.post.content?.trim() ?? '');

const images = computed(() => (props.post.media ?? []).filter(isImage));

const thumbnails = computed(() => images.value.slice(0, MAX_THUMBNAILS));

const hiddenImages = computed(() =>
    Math.max(0, images.value.length - MAX_THUMBNAILS),
);

const contentTypeKey = computed(() =>
    primaryTarget.value
        ? getContentTypeBadgeKey(
              primaryTarget.value.platform,
              primaryTarget.value.content_type ?? null,
          )
        : null,
);

const canQueue = computed(
    () => account.value?.has_posting_schedule === true,
);

const permalink = computed(() => primaryTarget.value?.platform_url ?? null);

const metricsDetail = computed(() => {
    const detail = primaryTarget.value
        ? props.post.metrics?.[primaryTarget.value.id]
        : null;

    return detail && detail.available ? detail : null;
});

const hasMetrics = computed(() =>
    metricsDetail.value
        ? compactPublicationMetrics(metricsDetail.value.metrics).length > 0
        : false,
);

const showStatus = computed(
    () =>
        props.post.status !== PostStatus.Scheduled &&
        props.post.status !== PostStatus.Published,
);

const detailsOpen = ref(false);

const edit = (): void => {
    router.visit(editUrl(props.post));
};

const onMenuSelect = (action: PostCardMenuAction): void => {
    switch (action) {
        case 'publish_now':
            schedulePostCard(props.post.id, 'publish_now');
            break;
        case 'move_top':
            emit('move', 'top');
            break;
        case 'move_up':
            emit('move', 'up');
            break;
        case 'move_down':
            emit('move', 'down');
            break;
        case 'duplicate':
            duplicatePostCard(props.post);
            break;
        case 'move_drafts':
            schedulePostCard(props.post.id, 'draft');
            break;
        case 'details':
            detailsOpen.value = true;
            break;
        case 'delete':
            deletePost(props.post);
            break;
    }
};
</script>

<template>
    <div
        :data-testid="`post-card-${testKey}`"
        :data-post-id="post.id"
        class="relative grid grid-cols-[minmax(0,1fr)_2.5rem] gap-x-3 gap-y-2 md:grid-cols-[71px_minmax(0,1fr)] md:gap-x-8"
    >
        <div
            class="col-span-2 flex flex-wrap items-center gap-2 md:col-span-1 md:flex-col md:flex-nowrap md:items-start"
        >
            <span class="inline-flex items-center gap-1">
                <IconGripVertical
                    v-if="draggable"
                    class="size-4 cursor-grab text-muted-foreground md:-ms-5"
                    :data-testid="`post-drag-handle-${testKey}`"
                    aria-hidden="true"
                />
                <time
                    v-if="time"
                    class="text-sm font-medium text-foreground"
                    :datetime="time"
                    :data-testid="`post-time-${testKey}`"
                >
                    {{ formatTime(time) }}
                </time>
                <span v-else class="text-sm font-medium text-muted-foreground">
                    {{ $t('posts.publish.no_time') }}
                </span>
            </span>
            <PostScheduleModeBadge
                v-if="
                    post.schedule_mode &&
                    (tab === 'sent' ||
                        (post.status === PostStatus.Scheduled &&
                            post.schedule_mode === ScheduleMode.Custom))
                "
                :post-id="post.id"
                :mode="post.schedule_mode"
                plain
            />
            <Badge
                v-if="showStatus"
                :variant="getPostStatusConfig(post.status).variant"
                class="h-6 gap-1 px-2 [&>svg]:size-4"
            >
                <component :is="getPostStatusConfig(post.status).icon" />
                {{ $t(`posts.status.${post.status}`) }}
            </Badge>
        </div>

        <article
            class="min-w-0 overflow-hidden rounded-xl border border-border-strong bg-card"
        >
            <Link
                :href="postUrl"
                :draggable="draggable ? 'false' : undefined"
                class="flex gap-4 p-4 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring md:gap-6"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <div class="flex items-center gap-3">
                        <span
                            v-if="primaryTarget"
                            class="relative inline-flex size-8 shrink-0"
                        >
                            <img
                                :draggable="draggable ? 'false' : undefined"
                                :src="
                                    account?.avatar_url ??
                                    getPlatformLogo(primaryTarget.platform)
                                "
                                :alt="
                                    account?.display_label ??
                                    getPlatformLabel(primaryTarget.platform)
                                "
                                class="size-full rounded-lg object-cover"
                            />
                            <img
                                v-if="account?.avatar_url"
                                :draggable="draggable ? 'false' : undefined"
                                :src="getPlatformLogo(primaryTarget.platform)"
                                :alt="getPlatformLabel(primaryTarget.platform)"
                                class="absolute -right-1.5 -bottom-1 size-4.5 rounded-md border border-card bg-card"
                            />
                        </span>
                        <p
                            class="min-w-0 truncate text-sm leading-tight font-emphasis text-foreground"
                        >
                            {{
                                account?.display_label ??
                                getPlatformLabel(primaryTarget?.platform ?? '')
                            }}
                        </p>
                        <span
                            v-if="targets.length > 1"
                            class="text-xs font-medium text-muted-foreground"
                            >+{{ targets.length - 1 }}</span
                        >
                        <Badge
                            v-if="contentTypeKey"
                            variant="secondary"
                            class="ms-auto h-6 px-2"
                            >{{ $t(contentTypeKey) }}</Badge
                        >
                    </div>
                    <p
                        class="line-clamp-4 text-sm whitespace-pre-line text-foreground"
                        :class="{ 'text-muted-foreground': !preview }"
                    >
                        {{ preview || $t('calendar.no_content') }}
                    </p>
                </div>
                <div
                    v-if="thumbnails.length"
                    class="grid w-24 shrink-0 content-start gap-2 md:w-[180px]"
                    :class="thumbnails.length > 1 ? 'grid-cols-2' : ''"
                >
                    <span
                        v-for="(thumbnail, index) in thumbnails"
                        :key="thumbnail.url"
                        class="relative block aspect-square overflow-hidden rounded-md border border-border-strong bg-secondary"
                    >
                        <img
                            :draggable="draggable ? 'false' : undefined"
                            :src="thumbnail.url"
                            alt=""
                            class="size-full object-cover"
                            loading="lazy"
                        />
                        <span
                            v-if="
                                hiddenImages > 0 &&
                                index === thumbnails.length - 1
                            "
                            class="absolute right-1.5 bottom-1.5 inline-flex size-6 items-center justify-center rounded-full bg-foreground/60 text-xs font-medium text-background"
                            >+{{ hiddenImages }}</span
                        >
                    </span>
                </div>
            </Link>

            <PostCardLabels
                class="-mt-1 px-4 pb-4"
                :post-id="post.id"
                :labels="post.labels ?? []"
                :test-key="testKey"
            />

            <PostMetricsBand
                v-if="tab === 'sent' && metricsDetail && hasMetrics"
                :detail="metricsDetail"
                :limit="5"
                :metrics-test-id="`post-metrics-${testKey}`"
                :insights-test-id="`post-insights-${testKey}`"
            />

            <div
                class="flex min-h-14 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-border-strong px-4 py-3"
            >
                <p class="min-w-0 truncate text-sm text-foreground">
                    <span
                        v-if="tab === 'sent' && primaryTarget"
                        class="inline-flex items-center gap-0.5"
                    >
                        {{ $t('posts.publish.published_via') }}
                        <img
                            :src="getPlatformLogo(primaryTarget.platform)"
                            alt=""
                            class="ms-0.5 size-4 rounded-sm"
                        />
                        {{ getPlatformLabel(primaryTarget.platform) }}
                    </span>
                    <span v-else-if="post.user?.name">{{
                        $t('posts.publish.created_by', {
                            name: post.user.name,
                            when: date.diffForHumans(post.created_at),
                        })
                    }}</span>
                </p>
                <div class="flex shrink-0 items-center gap-1">
                    <template v-if="canCreatePost && tab === 'drafts'">
                        <Button
                            v-if="canQueue"
                            variant="outline"
                            :data-testid="`post-add-to-queue-${testKey}`"
                            @click="schedulePostCard(post.id, 'queue_next')"
                        >
                            <IconListNumbers class="size-4" />
                            {{ $t('posts.publish.actions.add_to_queue') }}
                        </Button>
                        <TooltipProvider v-else :delay-duration="200">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span tabindex="0">
                                        <Button
                                            variant="outline"
                                            disabled
                                            :data-testid="`post-add-to-queue-${testKey}`"
                                        >
                                            <IconListNumbers class="size-4" />
                                            {{
                                                $t(
                                                    'posts.publish.actions.add_to_queue',
                                                )
                                            }}
                                        </Button>
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {{
                                        $t(
                                            'posts.publish.actions.add_to_queue_disabled',
                                        )
                                    }}
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </template>
                    <Button
                        v-if="permalink"
                        as="a"
                        :href="permalink"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="outline"
                        :data-testid="`post-view-${testKey}`"
                    >
                        <IconExternalLink class="size-4" />
                        {{ $t('posts.publish.actions.view_post') }}
                    </Button>
                    <Button
                        v-if="canCreatePost && post.status === PostStatus.Scheduled"
                        variant="outline"
                        :data-testid="`post-publish-now-${testKey}`"
                        @click="schedulePostCard(post.id, 'publish_now')"
                    >
                        <IconSend class="size-4" />
                        {{ $t('posts.publish.actions.publish_now') }}
                    </Button>
                    <Button
                        v-if="canCreatePost && isEditable"
                        variant="outline"
                        size="icon"
                        :aria-label="$t('posts.publish.actions.edit')"
                        :data-testid="`post-edit-${testKey}`"
                        @click="edit"
                    >
                        <IconPencil class="size-4" />
                    </Button>
                    <PostCardMenu
                        v-if="canCreatePost"
                        :post="post"
                        :test-key="testKey"
                        :can-move-up="canMoveUp"
                        :can-move-down="canMoveDown"
                        :movable="movable"
                        @select="onMenuSelect"
                    />
                    <PostDetailsDialog
                        v-if="canCreatePost"
                        v-model:open="detailsOpen"
                        :post="post"
                        :test-key="testKey"
                        :timezone="timezone"
                    />
                </div>
            </div>
        </article>

        <div class="md:absolute md:top-0 md:left-full md:ms-1">
            <PostNotesPopover
                :post-id="post.id"
                :count="post.notes_count"
                :current-user-id="authUserId"
                :initial-open="openPostNotesId === post.id"
                :highlight-note-id="highlightNoteId"
            />
        </div>
    </div>
</template>
