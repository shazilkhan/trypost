<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { IconMoon, IconSun } from '@tabler/icons-vue';
import { ref } from 'vue';

import PreferencesController from '@/actions/App/Http/Controllers/App/Settings/PreferencesController';
import { applyTheme, type Theme } from '@/preferences';
import type { SharedData } from '@/types';

type ResolvedTheme = Exclude<Theme, 'system'>;

const page = usePage<SharedData>();

const resolve = (theme: Theme | undefined): ResolvedTheme => {
    if (theme === 'light' || theme === 'dark') {
        return theme;
    }

    return document.documentElement.classList.contains('dark')
        ? 'dark'
        : 'light';
};

const current = ref<ResolvedTheme>(resolve(page.props.auth.user?.theme));

const themes = [
    { value: 'light', icon: IconSun },
    { value: 'dark', icon: IconMoon },
] as const satisfies readonly { value: ResolvedTheme; icon: unknown }[];

const select = (theme: ResolvedTheme): void => {
    if (theme === current.value) {
        return;
    }

    current.value = theme;
    applyTheme(theme);
    router.patch(
        PreferencesController.update.url(),
        { theme },
        { preserveScroll: true, preserveState: true },
    );
};
</script>

<template>
    <div
        class="flex items-center gap-0.5 rounded-lg border border-border bg-card p-[3px]"
        role="radiogroup"
        :aria-label="$t('settings.preferences.theme.heading')"
        data-testid="theme-toggle"
    >
        <button
            v-for="theme in themes"
            :key="theme.value"
            type="button"
            role="radio"
            :aria-checked="current === theme.value"
            :aria-label="$t(`settings.preferences.theme.${theme.value}`)"
            :title="$t(`settings.preferences.theme.${theme.value}`)"
            :data-testid="`theme-toggle-${theme.value}`"
            :class="[
                'flex size-7 items-center justify-center rounded-md transition-control focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring',
                current === theme.value
                    ? 'bg-primary-subtle text-primary-text'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
            @click="select(theme.value)"
        >
            <component :is="theme.icon" class="size-4" />
        </button>
    </div>
</template>
