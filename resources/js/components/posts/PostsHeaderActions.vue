<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconCalendar, IconList, IconPlus } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { cn } from '@/lib/utils';
import { calendar } from '@/routes/app';
import { create as createPost, index as postsIndex } from '@/routes/app/posts';

const props = defineProps<{
    activeMode: 'list' | 'calendar';
}>();

const { canCreatePost } = useWorkspaceRole();

const modeClass = (mode: 'list' | 'calendar'): string =>
    cn(
        'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
        props.activeMode === mode
            ? 'bg-amber-100 text-amber-950 shadow-xs'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    );
</script>

<template>
    <div class="flex items-center gap-2">
        <div
            class="inline-flex items-center rounded-lg border border-border bg-muted/30 p-0.5"
            data-testid="posts-view-switcher"
        >
            <Link
                :href="postsIndex.url()"
                :class="modeClass('list')"
                data-testid="posts-view-list"
            >
                <IconList class="size-4" />
                <span class="hidden sm:inline">{{
                    $t('posts.view.list')
                }}</span>
            </Link>
            <Link
                :href="calendar.url({ query: { view: 'week' } })"
                :class="modeClass('calendar')"
                data-testid="posts-view-calendar"
            >
                <IconCalendar class="size-4" />
                <span class="hidden sm:inline">{{ $t('calendar.title') }}</span>
            </Link>
        </div>

        <Link
            v-if="canCreatePost"
            :href="createPost.url()"
            data-testid="new-post-link"
        >
            <Button>
                <IconPlus class="size-4" />
                <span class="hidden md:inline">{{ $t('posts.new_post') }}</span>
            </Button>
        </Link>
    </div>
</template>
