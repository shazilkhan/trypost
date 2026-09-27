<script setup lang="ts">
import { IconChevronDown, IconSearch } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface FilterOption {
    id: string;
    label: string;
    searchText?: string;
    ariaLabel?: string;
}

const props = withDefaults(
    defineProps<{
        options: FilterOption[];
        label: string;
        searchPlaceholder: string;
        emptyMessage: string;
        selectAllLabel: string;
        deselectAllLabel: string;
        testId: string;
        contentClass?: string;
        checkboxPosition?: 'start' | 'end';
    }>(),
    {
        contentClass: 'w-72',
        checkboxPosition: 'end',
    },
);

const selectedIds = defineModel<string[]>({ required: true });
const open = ref(false);
const search = ref('');

watch(open, (isOpen) => {
    if (!isOpen) search.value = '';
});

const visibleOptions = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.options.filter((option) =>
        (option.searchText ?? option.label).toLocaleLowerCase().includes(query),
    );
});

const toggle = (id: string): void => {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((selected) => selected !== id)
        : [...selectedIds.value, id];
};

const toggleAll = (): void => {
    selectedIds.value = selectedIds.value.length
        ? []
        : props.options.map((option) => option.id);
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                class="w-full justify-between gap-2 font-normal sm:w-auto"
                :data-testid="`${testId}-filter`"
            >
                <span
                    class="inline-flex size-4 shrink-0 items-center opacity-60"
                >
                    <slot name="icon" />
                </span>
                <span>{{ label }}</span>
                <span
                    v-if="selectedIds.length"
                    class="rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium"
                    >{{ selectedIds.length }}</span
                >
                <IconChevronDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>

        <PopoverContent
            :class="['max-w-[calc(100vw-2rem)] p-3', contentClass]"
            align="start"
        >
            <div class="relative">
                <IconSearch
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="searchPlaceholder"
                    :aria-label="searchPlaceholder"
                    class="pl-9"
                    :data-testid="`${testId}-search`"
                />
            </div>

            <div
                class="flex items-center justify-between px-1 pt-4 pb-2 text-sm"
            >
                <span class="text-muted-foreground">{{ label }}</span>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground"
                    :data-testid="`${testId}-toggle-all`"
                    @click="toggleAll"
                >
                    {{ selectedIds.length ? deselectAllLabel : selectAllLabel }}
                </button>
            </div>

            <div
                class="max-h-72 overflow-y-auto"
                role="group"
                :aria-label="label"
            >
                <p
                    v-if="!visibleOptions.length"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ emptyMessage }}
                </p>
                <div
                    v-for="option in visibleOptions"
                    :key="option.id"
                    class="flex min-h-12 cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                    :class="
                        selectedIds.includes(option.id) ? 'bg-muted/60' : ''
                    "
                    :data-testid="`${testId}-option-${option.id}`"
                    @click="toggle(option.id)"
                >
                    <Checkbox
                        v-if="checkboxPosition === 'start'"
                        :model-value="selectedIds.includes(option.id)"
                        :aria-label="option.ariaLabel ?? option.label"
                        :data-testid="`${testId}-checkbox-${option.id}`"
                        @click.stop
                        @update:model-value="toggle(option.id)"
                    />
                    <span class="min-w-0 flex-1">
                        <slot name="option" :option="option">{{
                            option.label
                        }}</slot>
                    </span>
                    <Checkbox
                        v-if="checkboxPosition === 'end'"
                        :model-value="selectedIds.includes(option.id)"
                        :aria-label="option.ariaLabel ?? option.label"
                        :data-testid="`${testId}-checkbox-${option.id}`"
                        @click.stop
                        @update:model-value="toggle(option.id)"
                    />
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
