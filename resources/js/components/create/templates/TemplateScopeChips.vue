<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { index } from '@/routes/app/create/templates';
import type { TemplateCounts, TemplateScope } from '@/types/template';

defineProps<{
    view: TemplateScope;
    counts: TemplateCounts;
    search: string | null;
}>();

const scopes: TemplateScope[] = ['discover', 'team', 'personal'];
</script>

<template>
    <nav
        class="flex shrink-0 flex-wrap items-center gap-2 px-4 pt-4 md:px-8"
        :aria-label="$t('create.templates.scopes_label')"
        data-testid="templates-scopes"
    >
        <Link
            v-for="scope in scopes"
            :key="scope"
            :href="
                index.url({
                    query: {
                        view: scope === 'discover' ? undefined : scope,
                        search: search ?? undefined,
                    },
                })
            "
            preserve-scroll
            :only="['view', 'counts', 'filters', 'library', 'templates']"
            :reset="['templates']"
            :aria-current="view === scope ? 'page' : undefined"
            :data-testid="`templates-scope-${scope}`"
            class="inline-flex h-8 items-center gap-2 rounded-full border px-3 text-sm font-medium transition-control"
            :class="
                view === scope
                    ? 'border-transparent bg-primary-selected text-primary-text'
                    : 'border-border-strong bg-card text-foreground hover:bg-accent'
            "
        >
            {{ $t(`create.templates.scopes.${scope}`) }}
            <span
                class="text-xs"
                :class="view === scope ? '' : 'text-muted-foreground'"
                :data-testid="`templates-scope-count-${scope}`"
                >{{ counts[scope] }}</span
            >
        </Link>
    </nav>
</template>
