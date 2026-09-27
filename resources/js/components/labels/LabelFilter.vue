<script setup lang="ts">
import { IconTag } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import LabelBadge from '@/components/labels/LabelBadge.vue';
import MultiSelectFilter from '@/components/MultiSelectFilter.vue';

interface Label {
    id: string;
    name: string;
    color: string;
}

const props = defineProps<{ labels: Label[] }>();
const selectedIds = defineModel<string[]>({ required: true });

const options = computed(() =>
    props.labels.map((label) => ({ id: label.id, label: label.name })),
);

const labelsById = computed(
    () => new Map(props.labels.map((label) => [label.id, label])),
);

const labelFor = (id: string): Label => labelsById.value.get(id)!;
</script>

<template>
    <MultiSelectFilter
        v-model="selectedIds"
        :options="options"
        :label="trans('posts.filter_by_label')"
        :search-placeholder="trans('posts.label_search_placeholder')"
        :empty-message="trans('posts.no_labels')"
        :select-all-label="trans('posts.composer.select_all')"
        :deselect-all-label="trans('posts.composer.deselect_all')"
        test-id="posts-label"
        checkbox-position="start"
    >
        <template #icon>
            <IconTag class="size-4" />
        </template>
        <template #option="{ option }">
            <LabelBadge :label="labelFor(option.id)" />
        </template>
    </MultiSelectFilter>
</template>
