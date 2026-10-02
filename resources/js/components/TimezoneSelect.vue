<script setup lang="ts">
import { IconCheck, IconChevronDown, IconMapPin, IconSearch, IconWorld } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { TimezoneOption } from '@/types/posting-schedule';

const props = defineProps<{
    options: TimezoneOption[];
    groups?: { labelKey: string; options: TimezoneOption[] }[];
    testid?: string;
    compact?: boolean;
    variant?: 'outline' | 'ghost';
    suggested?: string | null;
}>();

const model = defineModel<string>({ required: true });
const search = ref('');
const open = ref(false);
const id = computed(() => props.testid ?? 'timezone');

const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const suggestion = computed(() => (props.suggested === undefined ? detectedTimezone : props.suggested));

const selected = computed(() => props.options.find((option) => option.value === model.value));

const sections = computed(() => {
    const term = search.value.trim().toLowerCase();
    const matches = (option: TimezoneOption): boolean =>
        term === '' || `${option.label} ${option.value} ${option.offset}`.toLowerCase().includes(term);

    const suggestedOption = suggestion.value ? props.options.find((option) => option.value === suggestion.value) : undefined;
    const fallbackGroups = suggestedOption
        ? [
              { labelKey: 'channels.timezone_select.suggestions', options: [suggestedOption] },
              { labelKey: 'channels.timezone_select.all', options: props.options },
          ]
        : [{ labelKey: '', options: props.options }];

    return (props.groups ?? fallbackGroups)
        .map((group) => ({ labelKey: group.labelKey, options: group.options.filter(matches) }))
        .filter((group) => group.options.length > 0);
});

const optionTestId = (value: string): string => `${id.value}-option-${value.replaceAll('/', '-')}`;

const choose = (value: string): void => {
    model.value = value;
    open.value = false;
    search.value = '';
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                :variant="variant ?? 'outline'"
                role="combobox"
                :aria-expanded="open"
                :data-testid="`${id}-trigger`"
                :class="
                    cn(
                        'data-[state=open]:bg-accent',
                        variant === 'ghost' || compact ? 'gap-1' : 'gap-2 font-normal',
                        compact ? 'max-w-full' : 'w-full justify-start',
                    )
                "
            >
                <IconWorld class="size-4 shrink-0 text-muted-foreground" />
                <span class="truncate">
                    {{ selected ? (compact ? selected.label : `${selected.label} (${selected.offset})`) : model }}
                </span>
                <IconChevronDown v-if="compact" class="size-4 shrink-0 text-muted-foreground" />
            </Button>
        </PopoverTrigger>

        <PopoverContent
            :class="cn('p-3', compact ? 'w-[306px] max-w-[calc(100vw-2rem)]' : 'w-(--reka-popover-trigger-width)')"
            :align="compact ? 'end' : 'start'"
        >
            <div class="relative">
                <IconSearch class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="search"
                    class="pl-8"
                    :data-testid="`${id}-search`"
                    :placeholder="$t('channels.timezone_select.placeholder')"
                    autocomplete="off"
                />
            </div>

            <div class="mt-3 max-h-72 overflow-y-auto">
                <p v-if="sections.length === 0" class="px-2 py-6 text-center text-sm text-muted-foreground">
                    {{ $t('channels.timezone_select.empty') }}
                </p>

                <div
                    v-for="(section, index) in sections"
                    :key="section.labelKey"
                    :class="index > 0 ? 'mt-2 border-t border-border-strong pt-2' : ''"
                >
                    <p v-if="section.labelKey" class="px-2 py-1 text-xs text-muted-foreground">
                        {{ $t(section.labelKey) }}
                    </p>
                    <button
                        v-for="option in section.options"
                        :key="option.value"
                        type="button"
                        :data-testid="optionTestId(option.value)"
                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm transition-control hover:bg-accent"
                        @click="choose(option.value)"
                    >
                        <IconCheck :class="cn('size-4 shrink-0', model === option.value ? 'opacity-100' : 'opacity-0')" />
                        <span class="truncate">
                            {{ option.label }}
                            <span class="text-xs text-muted-foreground">({{ option.offset }})</span>
                        </span>
                        <IconMapPin
                            v-if="section.labelKey === 'channels.timezone_select.suggestions' && option.value === suggestion"
                            class="ms-auto size-4 shrink-0 text-muted-foreground"
                            :aria-label="$t('channels.timezone_select.detected')"
                            :data-testid="`${id}-detected`"
                        />
                    </button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
