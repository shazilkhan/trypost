<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    IconCopy,
    IconCopyPlus,
    IconDots,
    IconFileText,
    IconPencil,
    IconSearch,
    IconTrash,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import {
    destroy as destroyPost,
    duplicate as duplicatePost,
    edit as editPostRoute,
    index as postsIndex,
    show as showPost,
    store as storePost,
    update as updatePost,
} from '@/actions/App/Http/Controllers/App/PostController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import HeaderTitle from '@/components/HeaderTitle.vue';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import LabelFilter from '@/components/labels/LabelFilter.vue';
import PostComposerDialog from '@/components/posts/composer/PostComposerDialog.vue';
import PostChannelFilter from '@/components/posts/PostChannelFilter.vue';
import PostNotesPopover from '@/components/posts/PostNotesPopover.vue';
import ScheduleViewSwitch from '@/components/posts/ScheduleViewSwitch.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useWorkspaceEcho } from '@/composables/echo/useWorkspaceEcho';
import { openPostComposer } from '@/composables/useGlobalPostComposer';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import type {
    ComposerAccount,
    ComposerInitialDraft,
    ComposerInitialPost,
    PostComposition,
} from '@/composables/usePostComposition';
import { getPostStatusConfig } from '@/composables/usePostStatus';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import date from '@/date';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { isImage } from '@/lib/mediaType';
import { copyToClipboard } from '@/lib/utils';
import type { MediaItem } from '@/types/media';
import { PostStatus } from '@/types/post';
interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    status: string;
    social_account: SocialAccount | null;
    content_type?: string;
    meta?: Record<string, any>;
}

interface Label {
    id: string;
    name: string;
    color: string;
}

interface Post {
    id: string;
    card_key?: string;
    content: string | null;
    status: string;
    created_at: string;
    scheduled_at: string | null;
    published_at: string | null;
    user: { name: string } | null;
    post_platforms: PostPlatform[];
    labels: Label[];
    notes_count: number;
    media?: MediaItem[];
}

interface ScrollPosts {
    data: Post[];
    meta: {
        hasNextPage: boolean;
    };
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    posts: ScrollPosts;
    currentStatus: string | null;
    tabCounts: Record<'all' | 'scheduled' | 'published' | 'draft', number>;
    labels: Label[];
    filterAccounts: SocialAccount[];
    filters: {
        search: string;
        labels: string[];
        channels: string[];
    };
    openComposer?: boolean;
    openComposerAssistant?: boolean;
    initialComposerDate?: string | null;
    openPostNotesId?: string | null;
    highlightNoteId?: string | null;
    authUserId: string;
    editPost?: Post | null;
    socialAccounts?: ComposerAccount[];
    platformConfigs?: Record<string, any>;
    pinterestBoards?: Record<string, any>;
    tiktokCreatorInfos?: Record<string, any>;
    signatures?: { id: string; name: string; content: string }[];
}

const props = defineProps<Props>();
const composerOpen = ref(Boolean(props.openComposer));
const composerSubmitting = ref(false);
const initialPost = computed<ComposerInitialPost | null>(() => {
    const post = props.editPost;
    const target = post?.post_platforms.find((platform) => platform.enabled);
    if (!post || !target?.social_account_id) return null;
    return {
        content: post.content ?? '',
        media: post.media ?? [],
        scheduled_at: date.formatUtcForDateTimeLocalInput(post.scheduled_at),
        status: post.status,
        social_account_id: target.social_account_id,
        content_type: target.content_type ?? '',
        meta: target.meta ?? {},
        label_ids: post.labels?.map((label) => label.id) ?? [],
    };
});
const recoveryDraft = computed<ComposerInitialDraft | null>(() => {
    const post = props.editPost;
    if (!post || post.post_platforms.some((target) => target.enabled))
        return null;

    return {
        content: post.content ?? '',
        media: post.media ?? [],
        scheduled_at: date.formatUtcForDateTimeLocalInput(post.scheduled_at),
        label_ids: post.labels?.map((label) => label.id) ?? [],
    };
});

watch(
    () => props.openComposer,
    (open) => {
        composerOpen.value = Boolean(open);
    },
);

const closeComposer = (): void => {
    composerOpen.value = false;
    router.visit(tabUrl(props.currentStatus), {
        replace: true,
        preserveScroll: true,
    });
};

const onComposerOpenChange = (open: boolean): void => {
    if (!open) closeComposer();
};

const submitComposition = (
    composition: PostComposition,
    createAnother: boolean,
): void => {
    composerSubmitting.value = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            composerOpen.value = false;
            if (createAnother) {
                openPostComposer();
            }
        },
        onFinish: () => {
            composerSubmitting.value = false;
        },
    };
    if (props.editPost) {
        if (!initialPost.value) {
            const data: Record<string, any> = {
                ...composition,
                recover_post_id: props.editPost.id,
            };
            router.post(storePost.url(), data, options);
            return;
        }
        const destination = composition.destinations[0];
        const data: Record<string, any> = {
            status: composition.status,
            content: destination.content ?? composition.content,
            media: destination.media ?? composition.media,
            content_type: destination.content_type,
            meta: destination.meta,
            label_ids: composition.label_ids,
            scheduled_at: composition.scheduled_at,
        };
        router.put(updatePost.url(props.editPost.id), data, options);
        return;
    }
    const data: Record<string, any> = { ...composition };
    router.post(storePost.url(), data, options);
};

const searchQuery = ref(props.filters.search);
const selectedLabelIds = ref<string[]>(props.filters.labels ?? []);
const selectedChannelIds = ref<string[]>(props.filters.channels ?? []);

const buildFilterUrl = () => {
    router.get(
        postsIndex.url(),
        {
            tab: props.currentStatus ?? undefined,
            search: searchQuery.value || undefined,
            labels: selectedLabelIds.value.length
                ? selectedLabelIds.value
                : undefined,
            channels: selectedChannelIds.value.length
                ? selectedChannelIds.value
                : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['posts', 'tabCounts', 'filters'],
            reset: ['posts'],
        },
    );
};

const search = debounce(buildFilterUrl, 300);

watch(searchQuery, () => search());
watch(selectedLabelIds, () => buildFilterUrl(), { deep: true });
watch(selectedChannelIds, () => buildFilterUrl(), { deep: true });

const pageTitle = computed(() => trans('posts.title'));
const statusTabs = [
    { key: 'all', status: null, label: 'sidebar.posts.all' },
    { key: 'scheduled', status: 'scheduled', label: 'sidebar.posts.scheduled' },
    { key: 'published', status: 'published', label: 'sidebar.posts.posted' },
    { key: 'draft', status: 'draft', label: 'sidebar.posts.drafts' },
] as const;
const tabUrl = (status: string | null): string =>
    postsIndex.url({
        query: {
            tab: status ?? undefined,
            search: searchQuery.value || undefined,
            labels: selectedLabelIds.value.length
                ? selectedLabelIds.value
                : undefined,
            channels: selectedChannelIds.value.length
                ? selectedChannelIds.value
                : undefined,
        },
    });

const formatDateTime = (value: string | null): string => {
    if (!value) return '—';
    return date.formatDateTime(value);
};

const getEnabledPlatforms = (post: Post) =>
    post.post_platforms.filter((pp) => pp.enabled);

const displayPosts = computed<Post[]>(() =>
    props.posts.data.flatMap((post) => {
        const targets = getEnabledPlatforms(post);
        if (
            !['published', 'partially_published', 'failed'].includes(
                post.status,
            ) ||
            targets.length <= 1
        ) {
            return [post];
        }

        return targets.map((target) => ({
            ...post,
            card_key: `${post.id}:${target.id}`,
            post_platforms: [target],
        }));
    }),
);

const getPostPreview = (post: Post): string =>
    post.content?.trim() || trans('calendar.no_content');

const getPostImage = (post: Post): MediaItem | undefined =>
    post.media?.find(isImage);

const EDITABLE_STATUSES: readonly string[] = [
    PostStatus.Draft,
    PostStatus.Scheduled,
];
const DELETABLE_STATUSES: readonly string[] = [
    PostStatus.Draft,
    PostStatus.Scheduled,
    PostStatus.Failed,
];
const canEdit = (post: Post): boolean =>
    EDITABLE_STATUSES.includes(post.status);
const canDelete = (post: Post): boolean =>
    DELETABLE_STATUSES.includes(post.status);

const { canCreatePost } = useWorkspaceRole();

const postUrl = (post: Post): string =>
    canEdit(post) ? editPostRoute.url(post.id) : showPost.url(post.id);

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const handleDelete = (post: Post) => {
    deleteModal.value?.open({
        url: destroyPost.url(post.id),
        confirmText: trans('common.confirm_modal.delete_keyword'),
    });
};

const handleDuplicate = (post: Post) => {
    router.post(duplicatePost.url(post.id), {
        post_platform_id: post.post_platforms[0]?.id,
    });
};

const handleCopyId = (post: Post) =>
    copyToClipboard(post.id, trans('posts.actions.copied'));

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));

const hasActiveFilters = computed(
    () =>
        hasActiveSearch.value ||
        selectedLabelIds.value.length > 0 ||
        selectedChannelIds.value.length > 0,
);

const refreshPosts = () => router.reload({ only: ['posts'], reset: ['posts'] });

useWorkspaceEcho(
    ['.post.created', '.post.deleted', '.post.platform.status.updated'],
    refreshPosts,
);

useWorkspaceEcho<{ post_id: string; change: string }>(
    '.post.note.changed',
    ({ change }) => {
        if (change === 'created' || change === 'deleted') refreshPosts();
    },
);
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout full-width>
        <template #header>
            <HeaderTitle :title="pageTitle" />
        </template>

        <template #header-actions>
            <ScheduleViewSwitch active-view="list" />
        </template>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <nav
                class="flex shrink-0 gap-5 overflow-x-auto border-b border-border px-6 pt-4"
                :aria-label="$t('posts.title')"
                data-testid="posts-tabs"
            >
                <Link
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    :href="tabUrl(tab.status)"
                    :aria-current="
                        currentStatus === tab.status ? 'page' : undefined
                    "
                    :data-testid="`posts-tab-${tab.key}`"
                    class="inline-flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-sm"
                    :class="
                        currentStatus === tab.status
                            ? 'border-primary font-semibold'
                            : 'border-transparent text-muted-foreground'
                    "
                >
                    {{ $t(tab.label) }}
                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs">{{
                        tabCounts[tab.key]
                    }}</span>
                </Link>
            </nav>

            <!-- Toolbar -->
            <div
                class="flex shrink-0 flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-64">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            :placeholder="trans('posts.search')"
                            class="w-full pl-9"
                        />
                    </div>

                    <PostChannelFilter
                        v-if="filterAccounts.length"
                        v-model="selectedChannelIds"
                        :channels="filterAccounts"
                    />

                    <LabelFilter
                        v-if="labels.length"
                        v-model="selectedLabelIds"
                        :labels="labels"
                    />
                </div>

                <Button
                    v-if="canCreatePost"
                    class="w-full sm:w-auto"
                    data-testid="posts-new-post"
                    @click="openPostComposer()"
                    >{{ $t('posts.new_post') }}</Button
                >
            </div>

            <EmptyState
                v-if="posts.data.length === 0"
                :icon="IconFileText"
                :title="
                    hasActiveFilters
                        ? $t('posts.no_search_results')
                        : $t('posts.no_posts')
                "
                :description="
                    hasActiveFilters
                        ? $t('posts.try_different_search')
                        : $t('posts.start_creating')
                "
            />

            <div
                v-else
                class="min-h-0 min-w-0 flex-1 overflow-auto overscroll-contain pb-px"
                data-testid="posts-scroll"
            >
                <InfiniteScroll
                    data="posts"
                    items-element="#posts-body"
                    preserve-url
                >
                    <div
                        id="posts-body"
                        class="mx-auto flex w-full max-w-5xl flex-col gap-5 px-4 pt-3 pb-12 sm:px-6"
                    >
                        <div
                            v-for="post in displayPosts"
                            :key="post.card_key ?? post.id"
                            :data-testid="`post-card-${post.card_key ?? post.id}`"
                            class="grid grid-cols-[minmax(0,1fr)_2.5rem] gap-x-3 gap-y-2 md:grid-cols-[8rem_minmax(0,1fr)_2.5rem]"
                        >
                            <div
                                class="col-span-2 flex items-center gap-2 pl-1 md:col-span-1 md:flex-col md:items-start md:gap-1 md:pt-4 md:pl-0"
                            >
                                <time
                                    v-if="
                                        post.scheduled_at ?? post.published_at
                                    "
                                    class="text-xs font-medium text-foreground"
                                    :datetime="
                                        post.scheduled_at ??
                                        post.published_at ??
                                        undefined
                                    "
                                >
                                    {{
                                        formatDateTime(
                                            post.scheduled_at ??
                                                post.published_at,
                                        )
                                    }}
                                </time>
                                <Badge
                                    :variant="
                                        getPostStatusConfig(post.status).variant
                                    "
                                    class="gap-1"
                                >
                                    <component
                                        :is="
                                            getPostStatusConfig(post.status)
                                                .icon
                                        "
                                        class="size-3"
                                    />
                                    {{ getPostStatusConfig(post.status).label }}
                                </Badge>
                            </div>

                            <article
                                class="min-w-0 overflow-hidden rounded-xl border border-border bg-card shadow-xs transition-colors hover:border-primary/40"
                            >
                                <Link
                                    :href="postUrl(post)"
                                    class="block px-4 pt-4 pb-5 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring sm:px-5"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            v-if="
                                                getEnabledPlatforms(post).length
                                            "
                                            class="flex shrink-0 -space-x-2"
                                        >
                                            <TooltipProvider
                                                v-for="pp in getEnabledPlatforms(
                                                    post,
                                                ).slice(0, 3)"
                                                :key="pp.id"
                                                :delay-duration="200"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span
                                                            class="relative inline-flex size-9 items-center justify-center rounded-lg border-2 border-card bg-muted"
                                                        >
                                                            <img
                                                                :src="
                                                                    pp
                                                                        .social_account
                                                                        ?.avatar_url ??
                                                                    getPlatformLogo(
                                                                        pp.platform,
                                                                    )
                                                                "
                                                                :alt="
                                                                    pp
                                                                        .social_account
                                                                        ?.display_label ??
                                                                    getPlatformLabel(
                                                                        pp.platform,
                                                                    )
                                                                "
                                                                class="size-full rounded-md object-cover"
                                                            />
                                                            <img
                                                                v-if="
                                                                    pp
                                                                        .social_account
                                                                        ?.avatar_url
                                                                "
                                                                :src="
                                                                    getPlatformLogo(
                                                                        pp.platform,
                                                                    )
                                                                "
                                                                :alt="
                                                                    getPlatformLabel(
                                                                        pp.platform,
                                                                    )
                                                                "
                                                                class="absolute -right-1 -bottom-1 size-4 rounded-full border border-card bg-card"
                                                            />
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {{
                                                            pp.social_account
                                                                ?.display_label ??
                                                            getPlatformLabel(
                                                                pp.platform,
                                                            )
                                                        }}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{
                                                    getEnabledPlatforms(post)[0]
                                                        ?.social_account
                                                        ?.display_label ??
                                                    getPlatformLabel(
                                                        getEnabledPlatforms(
                                                            post,
                                                        )[0]?.platform ?? '',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    getEnabledPlatforms(post)[0]
                                                        ?.social_account
                                                        ?.username
                                                "
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                @{{
                                                    getEnabledPlatforms(post)[0]
                                                        ?.social_account
                                                        ?.username
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            v-if="
                                                getEnabledPlatforms(post)
                                                    .length > 3
                                            "
                                            class="text-xs font-medium text-muted-foreground"
                                            >+{{
                                                getEnabledPlatforms(post)
                                                    .length - 3
                                            }}</span
                                        >
                                    </div>
                                    <div class="mt-4 flex items-start gap-4">
                                        <p
                                            class="line-clamp-3 min-w-0 flex-1 text-sm leading-6 whitespace-pre-line text-foreground/85"
                                        >
                                            {{ getPostPreview(post) }}
                                        </p>
                                        <img
                                            v-if="getPostImage(post)"
                                            :src="getPostImage(post)?.url"
                                            alt=""
                                            class="size-20 shrink-0 rounded-lg border border-border object-cover sm:size-24"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div
                                        v-if="post.labels?.length"
                                        class="mt-4 flex flex-wrap items-center gap-1.5"
                                    >
                                        <LabelBadge
                                            v-for="label in post.labels.slice(
                                                0,
                                                3,
                                            )"
                                            :key="label.id"
                                            :label="label"
                                        />
                                        <span
                                            v-if="post.labels.length > 3"
                                            class="text-xs text-muted-foreground"
                                            >+{{ post.labels.length - 3 }}</span
                                        >
                                    </div>
                                </Link>

                                <div
                                    class="flex min-h-12 items-center justify-between gap-2 border-t border-border px-4 py-2 sm:px-5"
                                >
                                    <p
                                        class="min-w-0 truncate text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="post.user?.name"
                                            class="font-medium text-foreground/80"
                                            >{{ post.user.name }}</span
                                        >
                                        <span v-if="post.user?.name"> · </span>
                                        <span
                                            :title="
                                                formatDateTime(post.created_at)
                                            "
                                            >{{
                                                date.diffForHumans(
                                                    post.created_at,
                                                )
                                            }}</span
                                        >
                                    </p>
                                    <div
                                        class="flex shrink-0 items-center gap-1.5"
                                    >
                                        <Button
                                            v-if="
                                                canCreatePost && canEdit(post)
                                            "
                                            variant="outline"
                                            size="icon"
                                            class="size-8"
                                            :aria-label="$t('posts.edit.title')"
                                            @click="
                                                router.visit(
                                                    editPostRoute.url(post.id),
                                                )
                                            "
                                        >
                                            <IconPencil class="size-4" />
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8"
                                                    :aria-label="
                                                        $t(
                                                            'posts.table.actions',
                                                        )
                                                    "
                                                >
                                                    <IconDots class="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    v-if="canCreatePost"
                                                    @click="
                                                        handleDuplicate(post)
                                                    "
                                                >
                                                    <IconCopyPlus
                                                        class="size-4"
                                                    />
                                                    {{
                                                        $t(
                                                            'posts.actions.duplicate',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="handleCopyId(post)"
                                                >
                                                    <IconCopy class="size-4" />
                                                    {{
                                                        $t(
                                                            'posts.actions.copy_id',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                                <template
                                                    v-if="
                                                        canCreatePost &&
                                                        canDelete(post)
                                                    "
                                                >
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        @click="
                                                            handleDelete(post)
                                                        "
                                                    >
                                                        <IconTrash
                                                            class="size-4"
                                                        />
                                                        {{
                                                            $t(
                                                                'posts.actions.delete',
                                                            )
                                                        }}
                                                    </DropdownMenuItem>
                                                </template>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </article>

                            <div class="pt-0.5">
                                <PostNotesPopover
                                    :post-id="post.id"
                                    :count="post.notes_count"
                                    :current-user-id="authUserId"
                                    :initial-open="openPostNotesId === post.id"
                                    :highlight-note-id="highlightNoteId"
                                />
                            </div>
                        </div>
                    </div>

                    <template #next="{ loading }">
                        <p
                            v-if="loading"
                            class="py-5 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            {{ $t('common.loading_more') }}
                        </p>
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('posts.edit.delete_modal.title')"
        :description="$t('posts.edit.delete_modal.description')"
        :action="$t('posts.edit.delete_modal.action')"
        :cancel="$t('posts.edit.delete_modal.cancel')"
    />

    <PostComposerDialog
        v-if="openComposer"
        :key="editPost?.id ?? 'new'"
        v-model:open="composerOpen"
        :social-accounts="socialAccounts ?? []"
        :initial-post="initialPost"
        :initial-draft="recoveryDraft"
        :post-id="editPost?.id"
        :open-assistant="openComposerAssistant"
        :labels="labels"
        :signatures="signatures ?? []"
        :initial-date="initialComposerDate"
        :submitting="composerSubmitting"
        :platform-configs="platformConfigs ?? {}"
        :pinterest-boards="pinterestBoards ?? {}"
        :tiktok-creator-infos="tiktokCreatorInfos ?? {}"
        @update:open="onComposerOpenChange"
        @submit="submitComposition"
    />
</template>
