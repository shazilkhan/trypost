<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    IconCalendarEvent,
    IconCopy,
    IconCopyPlus,
    IconDots,
    IconFileText,
    IconLoader2,
    IconSearch,
    IconTrash,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import {
    destroy as destroyPost,
    duplicate as duplicatePost,
    edit as editPost,
    index as postsIndex,
    show as showPost,
} from '@/actions/App/Http/Controllers/App/PostController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import HeaderTitle from '@/components/HeaderTitle.vue';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import LabelFilter from '@/components/labels/LabelFilter.vue';
import PostsHeaderActions from '@/components/posts/PostsHeaderActions.vue';
import PostMediaPreview from '@/components/posts/previews/PostMediaPreview.vue';
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
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import { getPostStatusConfig } from '@/composables/usePostStatus';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import date from '@/date';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn, copyToClipboard } from '@/lib/utils';
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
}

interface Label {
    id: string;
    name: string;
    color: string;
}

interface Post {
    id: string;
    content: string | null;
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    created_at: string;
    media: MediaItem[];
    post_platforms: PostPlatform[];
    labels: Label[];
}

interface ScrollPosts {
    data: Post[];
    total: number;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    posts: ScrollPosts;
    currentTab: 'draft' | 'scheduled' | 'published' | null;
    labels: Label[];
    filters: {
        tab: 'draft' | 'scheduled' | 'published' | null;
        search: string;
        labels: string[];
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);
const selectedLabelIds = ref<string[]>(props.filters.labels ?? []);

const buildFilterUrl = () => {
    router.get(
        postsIndex.url(),
        {
            tab: props.currentTab || undefined,
            search: searchQuery.value || undefined,
            labels: selectedLabelIds.value.length
                ? selectedLabelIds.value
                : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['posts', 'filters', 'currentTab'],
            reset: ['posts'],
        },
    );
};

const search = debounce(buildFilterUrl, 300);

watch(searchQuery, () => search());
watch(selectedLabelIds, () => buildFilterUrl(), { deep: true });

type PostTab = 'scheduled' | 'draft' | 'published';

const postTabs = computed<
    Array<{ value: PostTab | null; label: string; testId: string }>
>(() => [
    {
        value: null,
        label: trans('sidebar.posts.all'),
        testId: 'posts-tab-all',
    },
    {
        value: 'scheduled',
        label: trans('sidebar.posts.scheduled'),
        testId: 'posts-tab-scheduled',
    },
    {
        value: 'draft',
        label: trans('sidebar.posts.drafts'),
        testId: 'posts-tab-draft',
    },
    {
        value: 'published',
        label: trans('sidebar.posts.posted'),
        testId: 'posts-tab-published',
    },
]);

const tabUrl = (tab: PostTab | null): string =>
    postsIndex.url({
        query: {
            tab: tab ?? undefined,
            search: searchQuery.value || undefined,
            labels: selectedLabelIds.value.length
                ? selectedLabelIds.value
                : undefined,
        },
    });

const tabClass = (tab: PostTab | null): string =>
    cn(
        'inline-flex h-10 shrink-0 items-center border-b-2 px-1 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
        props.currentTab === tab
            ? 'border-amber-500 text-amber-950'
            : 'border-transparent text-muted-foreground hover:border-amber-200 hover:text-foreground',
    );

const getEnabledPlatforms = (post: Post) =>
    post.post_platforms.filter((pp) => pp.enabled);

const getPostPreview = (post: Post): string =>
    post.content?.trim() || trans('calendar.no_content');

const getPostTimestamp = (post: Post): string =>
    post.scheduled_at ?? post.published_at ?? post.created_at;

const formatGroupLabel = (value: string): string => {
    const localDate = date.formatDateTimeForDatePicker(value);
    const today = date.formatDateTimeForDatePicker(new Date().toISOString());

    if (localDate.isSame(today, 'day')) {
        return `${trans('common.date_range_picker.today')}, ${date.formatDate(value)}`;
    }

    if (localDate.isSame(today.subtract(1, 'day'), 'day')) {
        return `${trans('common.date_range_picker.yesterday')}, ${date.formatDate(value)}`;
    }

    return date.formatDate(value);
};

const postGroups = computed(() => {
    const groups = new Map<string, { label: string; posts: Post[] }>();

    props.posts.data.forEach((post) => {
        const timestamp = getPostTimestamp(post);
        const key = date
            .formatDateTimeForDatePicker(timestamp)
            .format('YYYY-MM-DD');
        const group = groups.get(key);

        if (group) {
            group.posts.push(post);
            return;
        }

        groups.set(key, {
            label: formatGroupLabel(timestamp),
            posts: [post],
        });
    });

    return Array.from(groups, ([key, group]) => ({ key, ...group }));
});

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
    canEdit(post) ? editPost.url(post.id) : showPost.url(post.id);

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const handleDelete = (post: Post) => {
    deleteModal.value?.open({
        url: destroyPost.url(post.id),
        confirmText: trans('common.confirm_modal.delete_keyword'),
    });
};

const handleDuplicate = (post: Post) => {
    router.post(duplicatePost.url(post.id));
};

const handleCopyId = (post: Post) =>
    copyToClipboard(post.id, trans('posts.actions.copied'));

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));

const hasActiveFilters = computed(
    () => hasActiveSearch.value || selectedLabelIds.value.length > 0,
);

const refreshPosts = () => router.reload({ only: ['posts'], reset: ['posts'] });

useWorkspaceEcho(
    ['.post.created', '.post.deleted', '.post.platform.status.updated'],
    refreshPosts,
);
</script>

<template>
    <Head :title="$t('posts.title')" />

    <AppLayout full-width>
        <template #header>
            <HeaderTitle
                :title="$t('posts.title')"
                :total="posts.total"
                :icon="IconCalendarEvent"
            />
        </template>

        <template #header-actions>
            <PostsHeaderActions active-mode="list" />
        </template>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <div
                class="flex shrink-0 flex-col gap-3 border-b border-border px-4 pt-2 pb-3 lg:flex-row lg:items-end lg:justify-between lg:gap-6 lg:pb-0"
            >
                <nav
                    class="-mb-px flex min-w-0 gap-5 overflow-x-auto"
                    data-testid="posts-tabs"
                    :aria-label="$t('sidebar.groups.posts')"
                >
                    <Link
                        v-for="tab in postTabs"
                        :key="tab.testId"
                        :href="tabUrl(tab.value)"
                        :class="tabClass(tab.value)"
                        :data-testid="tab.testId"
                        :aria-current="
                            currentTab === tab.value ? 'page' : undefined
                        "
                    >
                        {{ tab.label }}
                    </Link>
                </nav>

                <div class="flex min-w-0 items-center gap-2 pb-0 lg:pb-2">
                    <div class="relative min-w-0 flex-1 sm:w-56 sm:flex-none">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            :placeholder="trans('posts.search')"
                            class="w-full pl-9"
                            autocomplete="off"
                            data-testid="posts-search"
                        />
                    </div>

                    <div v-if="labels.length" class="shrink-0">
                        <LabelFilter
                            v-model="selectedLabelIds"
                            :labels="labels"
                        />
                    </div>
                </div>
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
                class="min-h-0 min-w-0 flex-1 overflow-auto overscroll-contain bg-stone-50/55 px-4 py-6 sm:px-6 lg:px-8 dark:bg-stone-950/20"
                data-testid="posts-scroll"
            >
                <InfiniteScroll
                    data="posts"
                    items-element="#posts-feed"
                    preserve-url
                    :buffer="300"
                >
                    <div
                        id="posts-feed"
                        class="mx-auto flex w-full max-w-4xl flex-col gap-8"
                        data-testid="posts-feed"
                    >
                        <section
                            v-for="group in postGroups"
                            :key="group.key"
                            class="grid gap-3 lg:grid-cols-[6.5rem_minmax(0,1fr)] lg:gap-x-5"
                        >
                            <h2
                                class="text-base font-semibold tracking-tight text-foreground lg:col-start-2"
                            >
                                {{ group.label }}
                            </h2>

                            <div class="contents">
                                <template
                                    v-for="post in group.posts"
                                    :key="post.id"
                                >
                                    <div
                                        class="flex items-center gap-2 text-xs font-medium text-muted-foreground lg:flex-col lg:items-end lg:gap-0.5 lg:pt-4"
                                    >
                                        <span
                                            class="text-sm text-foreground/80"
                                        >
                                            {{
                                                date.formatTime(
                                                    getPostTimestamp(post),
                                                )
                                            }}
                                        </span>
                                        <span>{{
                                            getPostStatusConfig(post.status)
                                                .label
                                        }}</span>
                                    </div>

                                    <article
                                        class="relative min-w-0 overflow-hidden rounded-xl border border-stone-200 bg-card shadow-[0_1px_2px_rgba(28,25,23,0.04)] transition-colors hover:border-amber-300/80 dark:border-stone-800"
                                        :data-testid="`post-card-${post.id}`"
                                    >
                                        <div
                                            class="flex min-w-0 flex-col md:min-h-44 md:flex-row"
                                        >
                                            <Link
                                                :href="postUrl(post)"
                                                class="flex min-w-0 flex-1 flex-col gap-4 p-4 pr-12 focus-visible:ring-2 focus-visible:ring-amber-500/60 focus-visible:outline-none sm:p-5 sm:pr-14"
                                            >
                                                <div
                                                    class="flex min-w-0 items-center gap-3"
                                                >
                                                    <div
                                                        v-if="
                                                            getEnabledPlatforms(
                                                                post,
                                                            ).length
                                                        "
                                                        class="flex shrink-0 -space-x-2"
                                                    >
                                                        <TooltipProvider
                                                            v-for="pp in getEnabledPlatforms(
                                                                post,
                                                            ).slice(0, 4)"
                                                            :key="pp.id"
                                                            :delay-duration="
                                                                200
                                                            "
                                                        >
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    as-child
                                                                >
                                                                    <span
                                                                        class="relative inline-flex size-9 items-center justify-center rounded-full border-2 border-card bg-muted"
                                                                    >
                                                                        <img
                                                                            v-if="
                                                                                pp
                                                                                    .social_account
                                                                                    ?.avatar_url
                                                                            "
                                                                            :src="
                                                                                pp
                                                                                    .social_account
                                                                                    .avatar_url
                                                                            "
                                                                            :alt="
                                                                                pp
                                                                                    .social_account
                                                                                    .display_label
                                                                            "
                                                                            class="size-full rounded-full object-cover"
                                                                        />
                                                                        <span
                                                                            v-else
                                                                            class="text-xs font-semibold text-muted-foreground"
                                                                        >
                                                                            {{
                                                                                (
                                                                                    pp
                                                                                        .social_account
                                                                                        ?.display_label ??
                                                                                    pp.platform
                                                                                )
                                                                                    .slice(
                                                                                        0,
                                                                                        1,
                                                                                    )
                                                                                    .toUpperCase()
                                                                            }}
                                                                        </span>
                                                                        <span
                                                                            class="absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-full border border-card bg-card"
                                                                        >
                                                                            <img
                                                                                :src="
                                                                                    getPlatformLogo(
                                                                                        pp.platform,
                                                                                    )
                                                                                "
                                                                                :alt="
                                                                                    pp.platform
                                                                                "
                                                                                class="size-3 rounded-sm object-contain"
                                                                            />
                                                                        </span>
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <div
                                                                        class="space-y-0.5 text-xs"
                                                                    >
                                                                        <p
                                                                            class="font-semibold"
                                                                        >
                                                                            {{
                                                                                pp
                                                                                    .social_account
                                                                                    ?.display_label ??
                                                                                pp.platform
                                                                            }}
                                                                        </p>
                                                                        <p
                                                                            class="opacity-70"
                                                                        >
                                                                            {{
                                                                                getPlatformLabel(
                                                                                    pp.platform,
                                                                                )
                                                                            }}
                                                                        </p>
                                                                    </div>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </div>

                                                    <div class="min-w-0 flex-1">
                                                        <p
                                                            class="truncate text-sm font-semibold text-foreground"
                                                        >
                                                            {{
                                                                getEnabledPlatforms(
                                                                    post,
                                                                )[0]
                                                                    ?.social_account
                                                                    ?.display_label ??
                                                                workspace.name
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                getEnabledPlatforms(
                                                                    post,
                                                                )
                                                                    .map(
                                                                        (
                                                                            platform,
                                                                        ) =>
                                                                            getPlatformLabel(
                                                                                platform.platform,
                                                                            ),
                                                                    )
                                                                    .join(' · ')
                                                            }}
                                                        </p>
                                                    </div>

                                                    <Badge
                                                        :variant="
                                                            getPostStatusConfig(
                                                                post.status,
                                                            ).variant
                                                        "
                                                        class="hidden sm:inline-flex"
                                                    >
                                                        <component
                                                            :is="
                                                                getPostStatusConfig(
                                                                    post.status,
                                                                ).icon
                                                            "
                                                            class="size-3"
                                                        />
                                                        {{
                                                            getPostStatusConfig(
                                                                post.status,
                                                            ).label
                                                        }}
                                                    </Badge>
                                                </div>

                                                <p
                                                    class="line-clamp-4 max-w-2xl text-sm leading-6 whitespace-pre-line text-foreground/85"
                                                >
                                                    {{ getPostPreview(post) }}
                                                </p>

                                                <div
                                                    v-if="post.labels?.length"
                                                    class="mt-auto flex flex-wrap items-center gap-1.5"
                                                >
                                                    <LabelBadge
                                                        v-for="label in post.labels.slice(
                                                            0,
                                                            4,
                                                        )"
                                                        :key="label.id"
                                                        :label="label"
                                                    />
                                                    <span
                                                        v-if="
                                                            post.labels.length >
                                                            4
                                                        "
                                                        class="text-xs font-medium text-muted-foreground"
                                                    >
                                                        +{{
                                                            post.labels.length -
                                                            4
                                                        }}
                                                    </span>
                                                </div>
                                            </Link>

                                            <div
                                                v-if="post.media?.length"
                                                class="relative h-48 shrink-0 overflow-hidden border-t border-border bg-muted md:h-auto md:w-52 md:border-t-0 md:border-l"
                                                @click.stop
                                            >
                                                <PostMediaPreview
                                                    :media="post.media"
                                                    :show-arrows="false"
                                                    :show-dots="
                                                        post.media.length > 1
                                                    "
                                                    media-class="h-full w-full object-cover"
                                                />
                                                <span
                                                    v-if="post.media.length > 1"
                                                    class="absolute top-3 right-3 rounded-full bg-black/65 px-2 py-0.5 text-xs font-medium text-white backdrop-blur-sm"
                                                >
                                                    +{{ post.media.length - 1 }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="absolute top-3 right-3"
                                            @click.stop
                                        >
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-8 bg-card/90 backdrop-blur-sm"
                                                        @click.stop
                                                    >
                                                        <IconDots
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    align="end"
                                                >
                                                    <DropdownMenuItem
                                                        v-if="canCreatePost"
                                                        @click="
                                                            handleDuplicate(
                                                                post,
                                                            )
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
                                                        @click="
                                                            handleCopyId(post)
                                                        "
                                                    >
                                                        <IconCopy
                                                            class="size-4"
                                                        />
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
                                                                handleDelete(
                                                                    post,
                                                                )
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
                                    </article>
                                </template>
                            </div>
                        </section>
                    </div>

                    <template #next="{ loading }">
                        <div
                            v-if="loading"
                            class="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground"
                        >
                            <IconLoader2 class="size-4 animate-spin" />
                            {{ $t('common.loading') }}
                        </div>
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
</template>
