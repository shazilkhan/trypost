<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        panel?: boolean;
        width?: 'sm' | 'md';
    }>(),
    {
        title: undefined,
        description: undefined,
        panel: false,
        width: 'sm',
    },
);
</script>

<template>
    <AuthLayout
        :title="title"
        :description="description"
        :panel="panel"
        :width="width"
    >
        <slot />
    </AuthLayout>
</template>
