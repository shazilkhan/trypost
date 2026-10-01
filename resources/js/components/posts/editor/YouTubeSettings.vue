<script setup lang="ts">
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import SettingsRow from '@/components/posts/editor/SettingsRow.vue';
import SettingsSection from '@/components/posts/editor/SettingsSection.vue';
import { Textarea } from '@/components/ui/textarea';
import { usePageErrors } from '@/composables/usePageErrors';
import { toNullableText } from '@/lib/utils';
import {
    getYouTubeDescriptionIssue,
    YOUTUBE_DESCRIPTION_MAX_BYTES,
    youtubeDescriptionBytes,
} from '@/lib/youtubeDescription';

interface Props {
    platformIndex: number;
    meta: Record<string, unknown>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});
const emit = defineEmits<{
    'update:meta': [value: Record<string, unknown>];
}>();

const errors = usePageErrors();
const descriptionId = computed(
    () => `youtube-description-${props.platformIndex}`,
);

const description = computed({
    get: () => toNullableText(props.meta.description) ?? '',
    set: (value: string) =>
        emit('update:meta', {
            ...props.meta,
            description: toNullableText(value),
        }),
});
const usedBytes = computed(() => youtubeDescriptionBytes(description.value));
const descriptionIssueKey = computed(() =>
    getYouTubeDescriptionIssue(props.meta.description),
);
const descriptionServerError = computed(
    () =>
        errors.value[`destinations.${props.platformIndex}.meta.description`] ??
        errors.value[`platforms.${props.platformIndex}.meta.description`],
);
const hasDescriptionError = computed(
    () => !!descriptionIssueKey.value || !!descriptionServerError.value,
);
</script>

<template>
    <SettingsSection>
        <SettingsRow
            :label="$t('posts.form.youtube.description')"
            :label-for="descriptionId"
            align-top
        >
            <Textarea
                :id="descriptionId"
                v-model="description"
                :data-testid="descriptionId"
                :disabled="disabled"
                :aria-invalid="hasDescriptionError ? true : undefined"
                :placeholder="$t('posts.form.youtube.description_placeholder')"
                class="field-sizing-fixed min-h-32 w-full resize-y"
            />
            <p
                class="text-xs tabular-nums"
                :class="
                    hasDescriptionError
                        ? 'text-destructive-text'
                        : 'text-muted-foreground'
                "
            >
                {{
                    $t('posts.form.youtube.description_bytes', {
                        used: usedBytes.toString(),
                        limit: YOUTUBE_DESCRIPTION_MAX_BYTES.toString(),
                    })
                }}
            </p>
            <InputError
                :message="
                    descriptionIssueKey
                        ? $t(descriptionIssueKey)
                        : descriptionServerError
                "
            />
        </SettingsRow>
    </SettingsSection>
</template>
