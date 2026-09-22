<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderTitle from '@/components/HeaderTitle.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    fullWidth?: boolean;
    title?: string;
    total?: number | null;
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    fullWidth: false,
    title: undefined,
    total: undefined,
    breadcrumbs: undefined,
});
</script>

<template>
    <AppLayout :full-width="fullWidth">
        <template
            v-if="$slots['header'] || title || breadcrumbs?.length"
            #header
        >
            <slot name="header">
                <Breadcrumbs
                    v-if="breadcrumbs?.length"
                    :breadcrumbs="breadcrumbs"
                />
                <HeaderTitle v-else-if="title" :title="title" :total="total" />
            </slot>
        </template>
        <template v-if="$slots['header-actions']" #header-actions>
            <slot name="header-actions" />
        </template>
        <slot />
    </AppLayout>
</template>
