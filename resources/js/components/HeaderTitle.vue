<script setup lang="ts">
defineProps<{
    title: string;
    total?: number | null;
}>();
</script>

<template>
    <h1
        class="truncate text-base font-semibold text-foreground"
        data-testid="header-title"
    >
        {{ title }}
        <span v-if="total != null && total > 0" class="text-muted-foreground">
            ({{ total }})
        </span>
    </h1>
</template>
