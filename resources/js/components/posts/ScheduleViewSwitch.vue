<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconCalendar, IconList } from '@tabler/icons-vue';
import { computed } from 'vue';

import { calendar } from '@/routes/app';
import { index as postsIndex } from '@/routes/app/posts';

const props = defineProps<{
    activeView: 'list' | 'calendar';
    listHref?: string;
    calendarHref?: string;
}>();

const views = computed(() => [
    {
        key: 'list',
        label: 'posts.list_view',
        icon: IconList,
        href: props.listHref ?? postsIndex.url(),
    },
    {
        key: 'calendar',
        label: 'calendar.title',
        icon: IconCalendar,
        href: props.calendarHref ?? calendar.url({ view: 'month' }),
    },
]);
</script>

<template>
    <nav
        class="inline-flex h-8 items-center gap-1 rounded-lg border border-border-strong bg-card p-[3px]"
        :aria-label="$t('posts.view_switcher')"
    >
        <Link
            v-for="view in views"
            :key="view.key"
            :href="view.href"
            :aria-current="activeView === view.key ? 'page' : undefined"
            :data-testid="`schedule-view-${view.key}`"
            class="inline-flex h-6 items-center justify-center gap-1 rounded-md border border-transparent px-2 text-sm font-medium transition-control"
            :class="
                activeView === view.key
                    ? 'bg-primary-selected text-primary-text'
                    : 'text-foreground hover:bg-accent'
            "
        >
            <component :is="view.icon" class="size-4" />
            <span class="hidden sm:inline">{{ $t(view.label) }}</span>
        </Link>
    </nav>
</template>
