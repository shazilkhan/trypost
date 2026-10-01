<script setup lang="ts">
import { IconCheck, IconChevronDown } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { Language } from '@/types';

const props = defineProps<{
    languages: Language[];
    testid?: string;
    label?: string;
}>();

const model = defineModel<string>({ required: true });
const search = ref('');
const open = ref(false);
const id = computed(() => props.testid ?? 'language');

const selected = computed(() =>
    props.languages.find((language) => language.code === model.value),
);

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();

    if (term === '') {
        return props.languages;
    }

    return props.languages.filter((language) =>
        `${language.name} ${language.code}`
            .toLocaleLowerCase()
            .includes(term),
    );
});

const choose = (code: string): void => {
    if (code !== model.value) {
        model.value = code;
    }

    open.value = false;
    search.value = '';
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
                :aria-label="label"
                class="max-w-full data-[state=open]:bg-accent"
                :data-testid="`${id}-trigger`"
            >
                <img
                    v-if="selected?.flag"
                    :src="selected.flag"
                    alt=""
                    class="h-3.5 w-5 shrink-0 rounded-xs object-cover ring-1 ring-border"
                />
                <span class="truncate" :data-testid="`${id}-value`">{{
                    selected?.name ?? model
                }}</span>
                <IconChevronDown class="size-4 text-muted-foreground" />
            </Button>
        </PopoverTrigger>

        <PopoverContent
            align="end"
            class="w-[306px] max-w-[calc(100vw-2rem)] p-3"
        >
            <Input
                v-model="search"
                :data-testid="`${id}-search`"
                :placeholder="$t('settings.preferences.language.search')"
                autocomplete="off"
            />

            <div class="mt-3 max-h-72 overflow-y-auto">
                <p
                    v-if="filtered.length === 0"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ $t('settings.preferences.language.empty') }}
                </p>
                <button
                    v-for="language in filtered"
                    :key="language.code"
                    type="button"
                    :data-testid="`${id}-option-${language.code}`"
                    class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm transition-control hover:bg-accent"
                    @click="choose(language.code)"
                >
                    <IconCheck
                        :class="
                            cn(
                                'size-4 shrink-0',
                                model === language.code
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )
                        "
                    />
                    <img
                        v-if="language.flag"
                        :src="language.flag"
                        alt=""
                        class="h-3.5 w-5 shrink-0 rounded-xs object-cover ring-1 ring-border"
                    />
                    <span class="truncate">{{ language.name }}</span>
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
