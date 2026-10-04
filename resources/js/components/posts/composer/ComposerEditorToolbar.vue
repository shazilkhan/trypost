<script setup lang="ts">
import {
    IconMoodSmile,
    IconPhotoPlus,
    IconSignature,
} from '@tabler/icons-vue';
import { ref } from 'vue';

import MediaSourceMenu from '@/components/posts/composer/MediaSourceMenu.vue';
import EmojiPicker from '@/components/posts/EmojiPicker.vue';
import SignaturePicker from '@/components/signatures/SignaturePicker.vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

withDefaults(
    defineProps<{
        testIdPrefix: string;
        signatures: { id: string; name: string; content: string }[];
        mediaDisabled?: boolean;
        /** Offers a file upload button, for a card without an add tile. */
        uploadable?: boolean;
    }>(),
    { mediaDisabled: false, uploadable: false },
);

const emit = defineEmits<{
    (event: 'import-started', payload: { importId: string; label: string }): void;
    (event: 'open-unsplash'): void;
    (event: 'upload'): void;
    (event: 'select-emoji', emoji: string): void;
    (
        event: 'select-signature',
        signature: { id: string; name: string; content: string },
    ): void;
    (
        event: 'save-signature',
        signature: { id: string; name: string; content: string },
    ): void;
}>();

const emojiOpen = ref(false);
const signaturesOpen = ref(false);

const selectSignature = (signature: {
    id: string;
    name: string;
    content: string;
}): void => {
    emit('select-signature', signature);
    signaturesOpen.value = false;
};

const requestUpload = (): void => {
    emit('upload');
};

const selectEmoji = (emoji: string): void => {
    emit('select-emoji', emoji);
    emojiOpen.value = false;
};
</script>

<template>
    <div
        class="-mx-3 -mb-2 flex items-center pt-4"
        :data-testid="`${testIdPrefix}-toolbar`"
    >
        <button
            v-if="uploadable"
            type="button"
            :data-testid="`${testIdPrefix}-add-media`"
            :aria-label="$t('posts.edit.add_media')"
            :title="$t('posts.edit.add_media')"
            :disabled="mediaDisabled"
            class="flex size-8 items-center justify-center rounded-lg text-foreground transition-control hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring disabled:cursor-not-allowed disabled:text-subtle-foreground disabled:hover:bg-transparent"
            @click="requestUpload"
        >
            <IconPhotoPlus class="size-4" />
        </button>
        <MediaSourceMenu
            :test-id-prefix="testIdPrefix"
            :disabled="mediaDisabled"
            :disabled-reason="$t('posts.composer.media_not_supported')"
            @import-started="emit('import-started', $event)"
            @open-unsplash="emit('open-unsplash')"
        />
        <span class="mx-1.5 h-6 w-px bg-border" aria-hidden="true" />
        <Popover v-model:open="emojiOpen">
            <PopoverTrigger as-child>
                <button
                    type="button"
                    :data-testid="`${testIdPrefix}-emoji`"
                    :aria-label="$t('posts.edit.emoji_picker.search')"
                    :title="$t('posts.edit.emoji_picker.search')"
                    class="flex size-8 items-center justify-center rounded-lg text-foreground transition-control hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                >
                    <IconMoodSmile class="size-4" />
                </button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0" align="start">
                <EmojiPicker @select="selectEmoji" />
            </PopoverContent>
        </Popover>
        <Popover v-model:open="signaturesOpen">
            <PopoverTrigger as-child>
                <button
                    type="button"
                    :data-testid="`${testIdPrefix}-signature`"
                    :aria-label="$t('posts.edit.signatures')"
                    :title="$t('posts.edit.signatures')"
                    class="flex size-8 items-center justify-center rounded-lg text-foreground transition-control hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                >
                    <IconSignature class="size-4" />
                </button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0" align="start">
                <SignaturePicker
                    :signatures="signatures"
                    @select="selectSignature"
                    @saved="emit('save-signature', $event)"
                />
            </PopoverContent>
        </Popover>
        <div class="ms-auto flex items-center gap-3 pe-3">
            <slot />
        </div>
    </div>
</template>
