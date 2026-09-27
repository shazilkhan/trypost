<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconCalendar, IconList } from '@tabler/icons-vue';

import { calendar } from '@/routes/app';
import { index as postsIndex } from '@/routes/app/posts';

defineProps<{ activeView: 'list' | 'calendar' }>();

const views = [
    {
        key: 'list',
        label: 'posts.list_view',
        icon: IconList,
        href: postsIndex.url(),
    },
    {
        key: 'calendar',
        label: 'calendar.title',
        icon: IconCalendar,
        href: calendar.url({ view: 'month' }),
    },
] as const;
</script>

<template>
    <nav
        class="inline-flex items-center rounded-md border border-border bg-card p-1"
        :aria-label="$t('posts.view_switcher')"
    >
        <Link
            v-for="view in views"
            :key="view.key"
            :href="view.href"
            :aria-current="activeView === view.key ? 'page' : undefined"
            :data-testid="`schedule-view-${view.key}`"
            class="inline-flex h-8 items-center justify-center gap-1.5 rounded-sm px-3 text-sm font-medium transition-colors"
            :class="
                activeView === view.key
                    ? 'bg-muted text-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="view.icon" class="size-4" />
            <span class="hidden sm:inline">{{ $t(view.label) }}</span>
        </Link>
    </nav>
</template>
