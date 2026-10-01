<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

interface Option {
    value: string;
    labelKey: string;
}

withDefaults(
    defineProps<{
        options: Option[];
        modelValue: string;
        testIdPrefix: string;
        disabled?: boolean;
        error?: string;
    }>(),
    { disabled: false, error: undefined },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div class="space-y-1.5" :data-testid="testIdPrefix">
        <RadioGroup
            :model-value="modelValue"
            :disabled="disabled"
            orientation="horizontal"
            :aria-label="$t('posts.create.steps.format_title')"
            class="flex min-h-6 flex-wrap items-center gap-x-5 gap-y-2"
            @update:model-value="emit('update:modelValue', String($event))"
        >
            <label
                v-for="option in options"
                :key="option.value"
                class="flex cursor-pointer items-center gap-2 text-sm text-foreground has-disabled:cursor-not-allowed has-disabled:opacity-50"
            >
                <RadioGroupItem
                    :value="option.value"
                    :data-testid="`${testIdPrefix}-${option.value}`"
                />
                {{ $t(option.labelKey) }}
            </label>
        </RadioGroup>
        <InputError :message="error" />
    </div>
</template>
