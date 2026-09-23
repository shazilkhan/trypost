<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    IconCopy,
    IconCopyPlus,
    IconDots,
    IconFileText,
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
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

const formatDateTime = (value: string | null): string => {
    if (!value) return '—';
    return date.formatDateTime(value);
};

const getEnabledPlatforms = (post: Post) =>
    post.post_platforms.filter((pp) => pp.enabled);

const getPostPreview = (post: Post): string =>
    post.content?.trim() || trans('calendar.no_content');

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
            <HeaderTitle :title="$t('posts.title')" :total="posts.total" />
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
                class="min-h-0 min-w-0 flex-1 overflow-auto overscroll-contain pb-px"
                data-testid="posts-scroll"
            >
                <InfiniteScroll
                    data="posts"
                    items-element="#posts-body"
                    preserve-url
                    :buffer="300"
                >
                    <Table>
                        <TableHeader sticky>
                            <TableRow>
                                <TableHead>{{
                                    $t('posts.table.post')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('posts.table.status')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('posts.table.scheduled_at')
                                }}</TableHead>
                                <TableHead class="text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody id="posts-body">
                            <TableRow
                                v-for="post in posts.data"
                                :key="post.id"
                                class="cursor-pointer"
                                :data-testid="`post-row-${post.id}`"
                                @click="router.visit(postUrl(post))"
                            >
                                <TableCell class="max-w-md py-3">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div
                                                v-if="
                                                    getEnabledPlatforms(post)
                                                        .length
                                                "
                                                class="flex -space-x-1.5"
                                            >
                                                <TooltipProvider
                                                    v-for="pp in getEnabledPlatforms(
                                                        post,
                                                    ).slice(0, 4)"
                                                    :key="pp.id"
                                                    :delay-duration="200"
                                                >
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <span
                                                                class="inline-flex size-6 items-center justify-center overflow-hidden rounded-full border border-border bg-card shadow-2xs"
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
                                                                    class="size-full object-cover"
                                                                />
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
                                                                    }}<span
                                                                        v-if="
                                                                            pp
                                                                                .social_account
                                                                                ?.username
                                                                        "
                                                                        class="font-normal opacity-80"
                                                                        >&nbsp;·&nbsp;@{{
                                                                            pp
                                                                                .social_account
                                                                                .username
                                                                        }}</span
                                                                    >
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
                                            <span
                                                v-if="
                                                    getEnabledPlatforms(post)
                                                        .length > 4
                                                "
                                                class="text-xs font-bold text-foreground/60"
                                                >+{{
                                                    getEnabledPlatforms(post)
                                                        .length - 4
                                                }}</span
                                            >
                                            <div
                                                v-if="post.labels?.length"
                                                class="ml-1 flex flex-wrap items-center gap-1"
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
                                                    v-if="
                                                        post.labels.length > 3
                                                    "
                                                    class="text-xs font-bold text-foreground/60"
                                                >
                                                    +{{
                                                        post.labels.length - 3
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <p class="truncate text-foreground/80">
                                            {{ getPostPreview(post) }}
                                        </p>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getPostStatusConfig(post.status)
                                                .variant
                                        "
                                    >
                                        <component
                                            :is="
                                                getPostStatusConfig(post.status)
                                                    .icon
                                            "
                                            class="size-3"
                                        />
                                        {{
                                            getPostStatusConfig(post.status)
                                                .label
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    {{
                                        formatDateTime(
                                            post.scheduled_at ??
                                                post.published_at,
                                        )
                                    }}
                                </TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                class="size-8"
                                                @click.stop
                                            >
                                                <IconDots class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                v-if="canCreatePost"
                                                @click="handleDuplicate(post)"
                                            >
                                                <IconCopyPlus class="size-4" />
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
                                                    $t('posts.actions.copy_id')
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
                                                    @click="handleDelete(post)"
                                                >
                                                    <IconTrash class="size-4" />
                                                    {{
                                                        $t(
                                                            'posts.actions.delete',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                            </template>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
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
