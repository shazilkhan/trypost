<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { preferences as preferencesRoute } from '@/routes/app/notifications';

interface Preferences {
    post_published: boolean;
    post_failed: boolean;
    account_disconnected: boolean;
    post_note_added: boolean;
    collaboration: boolean;
}

interface Props {
    preferences: Preferences;
}

const props = defineProps<Props>();

const postPublished = ref(props.preferences.post_published);
const postFailed = ref(props.preferences.post_failed);
const accountDisconnected = ref(props.preferences.account_disconnected);
const postNoteAdded = ref(props.preferences.post_note_added);
const collaboration = ref(props.preferences.collaboration);
const processing = ref(false);

const options = [
    { id: 'post_published', model: postPublished },
    { id: 'post_failed', model: postFailed },
    { id: 'account_disconnected', model: accountDisconnected },
    { id: 'post_note_added', model: postNoteAdded },
    { id: 'collaboration', model: collaboration },
] as const;

const submit = () => {
    processing.value = true;

    router.put(
        preferencesRoute().url,
        {
            post_published: postPublished.value,
            post_failed: postFailed.value,
            account_disconnected: accountDisconnected.value,
            post_note_added: postNoteAdded.value,
            collaboration: collaboration.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="$t('settings.notifications.title')" />

    <SettingsLayout :title="$t('settings.notifications.title')">
        <SettingsSection
            :title="$t('settings.notifications.heading')"
            :description="$t('settings.notifications.description')"
        >
            <div class="flex flex-col gap-6">
                <div
                    v-for="option in options"
                    :key="option.id"
                    class="flex items-start justify-between gap-4"
                >
                    <div class="flex max-w-[440px] min-w-0 flex-col gap-2">
                        <Label
                            :for="option.id"
                            class="text-sm leading-tight font-emphasis"
                        >
                            {{ $t(`settings.notifications.${option.id}`) }}
                        </Label>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t(
                                    `settings.notifications.${option.id}_description`,
                                )
                            }}
                        </p>
                    </div>
                    <Switch
                        :id="option.id"
                        v-model="option.model.value"
                        class="shrink-0"
                    />
                </div>
            </div>

            <Button
                :disabled="processing"
                class="self-start"
                @click="submit"
            >
                {{ $t('settings.notifications.save') }}
            </Button>
        </SettingsSection>
    </SettingsLayout>
</template>
