<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { store } from '@/routes/app/referral-source';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();

const sources = computed(() => page.props.referralSources ?? []);

const form = useForm<{ referral_source: string }>({ referral_source: '' });

const choose = (value: unknown): void => {
    form.referral_source = String(value);
};

const submit = (): void => {
    if (form.referral_source === '' || form.processing) {
        return;
    }

    form.submit(store(), { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <section
        v-if="sources.length > 0"
        class="fixed end-4 bottom-4 z-40 flex max-h-[calc(100svh-2rem)] w-[calc(100vw-2rem)] max-w-sm flex-col rounded-2xl border border-border bg-card p-6 shadow-lg"
        role="dialog"
        aria-labelledby="referral-source-title"
        data-testid="referral-source-survey"
    >
        <h2
            id="referral-source-title"
            class="text-base font-medium text-foreground"
        >
            {{ $t('referral_source.title') }}
        </h2>

        <RadioGroup
            :model-value="form.referral_source"
            aria-labelledby="referral-source-title"
            class="-mx-1 mt-4 flex min-h-0 flex-col gap-0 overflow-y-auto px-1"
            @update:model-value="choose"
        >
            <label
                v-for="source in sources"
                :key="source"
                class="flex cursor-pointer items-center gap-3 py-1.5 text-sm text-foreground"
            >
                <RadioGroupItem
                    :value="source"
                    :data-testid="`referral-source-option-${source}`"
                />
                {{ $t(`referral_source.options.${source}`) }}
            </label>
        </RadioGroup>

        <InputError class="mt-2" :message="form.errors.referral_source" />

        <Button
            type="button"
            class="mt-4 self-start"
            :disabled="form.referral_source === '' || form.processing"
            data-testid="referral-source-submit"
            @click="submit"
        >
            {{ $t('referral_source.submit') }}
        </Button>
    </section>
</template>
