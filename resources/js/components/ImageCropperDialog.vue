<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    clampSelection,
    containScale,
    type Corner,
    defaultSelection,
    resizeSelection,
    resolveOutputFileName,
    resolveOutputMime,
    type SourceRect,
} from '@/lib/imageCrop';

type Props = {
    open: boolean;
    src: string | null;
    fileName?: string;
    mimeType?: string;
    outputSize?: number;
    outputWidth?: number;
    outputHeight?: number;
    aspectPresets?: Array<{
        value: string;
        label: string;
        width: number;
        height: number;
    }>;
    enableAltText?: boolean;
    altText?: string | null;
    enableAppearance?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    fileName: 'image.png',
    mimeType: 'image/png',
    outputSize: 512,
    enableAltText: false,
    altText: null,
    enableAppearance: false,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (
        e: 'cropped',
        file: File,
        dimensions: { width: number; height: number },
    ): void;
    (e: 'alt-text-saved', value: string | null): void;
}>();

const viewportEl = ref<HTMLElement | null>(null);
const imageEl = ref<HTMLImageElement | null>(null);
const viewportSize = ref(0);
const natural = ref({ width: 0, height: 0 });
const selection = ref<SourceRect>({ sx: 0, sy: 0, sw: 0, sh: 0 });
const processing = ref(false);
const initialized = ref(false);
const imageError = ref(false);
const selectedPreset = ref<string | null>(null);
const activeTab = ref<'edit' | 'alt'>('edit');
const editSection = ref<'crop' | 'appearance'>('crop');
const draftAltText = ref('');
const cropTouched = ref(false);
const selectedFilter = ref<'original' | 'vivid' | 'mono' | 'sepia'>('original');
const brightness = ref(0);
const contrast = ref(0);
const saturation = ref(0);
const warmth = ref(0);

const filterPresets = ['original', 'vivid', 'mono', 'sepia'] as const;
const FILTER_CSS = {
    original: '',
    vivid: 'saturate(1.3) contrast(1.08)',
    mono: 'grayscale(1)',
    sepia: 'sepia(1)',
} as const;
const presetFilter = computed(() => FILTER_CSS[selectedFilter.value]);
const adjustments = computed(() => ({
    brightness: brightness.value,
    contrast: contrast.value,
    saturation: saturation.value,
    warmth: warmth.value,
}));
const setAdjustment = (key: string, value: number): void => {
    const controls: Record<string, typeof brightness> = {
        brightness,
        contrast,
        saturation,
        warmth,
    };
    if (controls[key]) controls[key].value = value;
};
const appearanceFilter = computed(() =>
    [
        presetFilter.value,
        `brightness(${1 + brightness.value / 100})`,
        `contrast(${1 + contrast.value / 100})`,
        `saturate(${1 + saturation.value / 100})`,
    ]
        .filter(Boolean)
        .join(' '),
);
const warmthColor = computed(() =>
    warmth.value >= 0 ? '245, 158, 11' : '59, 130, 246',
);
const warmthOpacity = computed(() => Math.abs(warmth.value) / 500);
const hasAppearanceChanges = computed(
    () =>
        selectedFilter.value !== 'original' ||
        [brightness.value, contrast.value, saturation.value, warmth.value].some(
            (value) => value !== 0,
        ),
);

const MIN_SELECTION_RATIO = 0.1;

let dragMode: 'move' | Corner | null = null;
let activePointerId: number | null = null;
let dragStart = {
    pointerX: 0,
    pointerY: 0,
    selection: { sx: 0, sy: 0, sw: 0, sh: 0 } as SourceRect,
};
let resizeObserver: ResizeObserver | null = null;

const ready = computed(() => viewportSize.value > 0 && natural.value.width > 0);

const scale = computed(() =>
    containScale(natural.value.width, natural.value.height, viewportSize.value),
);

const minSourceSize = computed(
    () =>
        Math.min(natural.value.width, natural.value.height) *
        MIN_SELECTION_RATIO,
);

const imageDisplay = computed(() => {
    const width = natural.value.width * scale.value;
    const height = natural.value.height * scale.value;

    return {
        width,
        height,
        left: (viewportSize.value - width) / 2,
        top: (viewportSize.value - height) / 2,
    };
});

const imageStyle = computed(() => ({
    left: `${imageDisplay.value.left}px`,
    top: `${imageDisplay.value.top}px`,
    width: `${imageDisplay.value.width}px`,
    height: `${imageDisplay.value.height}px`,
    filter: appearanceFilter.value,
}));

const warmthStyle = computed(() => ({
    ...imageStyle.value,
    filter: undefined,
    backgroundColor: `rgba(${warmthColor.value}, ${warmthOpacity.value})`,
}));

const selectionStyle = computed(() => ({
    left: `${imageDisplay.value.left + selection.value.sx * scale.value}px`,
    top: `${imageDisplay.value.top + selection.value.sy * scale.value}px`,
    width: `${selection.value.sw * scale.value}px`,
    height: `${selection.value.sh * scale.value}px`,
    boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.5)',
}));

const outputMime = computed(() => resolveOutputMime(props.mimeType));
const activePreset = computed(() =>
    props.aspectPresets?.find(
        (preset) => preset.value === selectedPreset.value,
    ),
);
const outputWidth = computed(
    () => activePreset.value?.width ?? props.outputWidth ?? props.outputSize,
);
const outputHeight = computed(
    () => activePreset.value?.height ?? props.outputHeight ?? props.outputSize,
);
const aspectRatio = computed(() => outputWidth.value / outputHeight.value);

const outputFileName = computed(() =>
    resolveOutputFileName(props.fileName, outputMime.value),
);

const maybeInitialize = () => {
    if (!ready.value || initialized.value) {
        return;
    }

    selection.value = defaultSelection(
        natural.value.width,
        natural.value.height,
        aspectRatio.value,
    );
    initialized.value = true;
};

const measure = () => {
    const el = viewportEl.value;

    if (!el) {
        return;
    }

    viewportSize.value = el.clientWidth;
    maybeInitialize();
};

const onImageLoad = () => {
    const img = imageEl.value;

    if (!img) {
        return;
    }

    if (img.naturalWidth === 0 || img.naturalHeight === 0) {
        imageError.value = true;

        return;
    }

    natural.value = { width: img.naturalWidth, height: img.naturalHeight };
    maybeInitialize();
};

const onImageError = () => {
    imageError.value = true;
};

const sourcePoint = (
    clientX: number,
    clientY: number,
): { px: number; py: number } => {
    const box = viewportEl.value!.getBoundingClientRect();

    return {
        px: (clientX - box.left - imageDisplay.value.left) / scale.value,
        py: (clientY - box.top - imageDisplay.value.top) / scale.value,
    };
};

const beginDrag = (mode: 'move' | Corner, event: PointerEvent) => {
    if (!ready.value || dragMode) {
        return;
    }

    dragMode = mode;
    activePointerId = event.pointerId;
    dragStart = {
        pointerX: event.clientX,
        pointerY: event.clientY,
        selection: { ...selection.value },
    };
    viewportEl.value?.setPointerCapture(event.pointerId);
};

const endDrag = (event: PointerEvent) => {
    dragMode = null;
    activePointerId = null;

    if (viewportEl.value?.hasPointerCapture(event.pointerId)) {
        viewportEl.value.releasePointerCapture(event.pointerId);
    }
};

const onSelectionPointerDown = (event: PointerEvent) => {
    beginDrag('move', event);
};

const onHandlePointerDown = (corner: Corner, event: PointerEvent) => {
    beginDrag(corner, event);
};

const onPointerMove = (event: PointerEvent) => {
    if (!dragMode || event.pointerId !== activePointerId) {
        return;
    }

    if (dragMode === 'move') {
        cropTouched.value = true;
        selection.value = clampSelection(
            {
                sx:
                    dragStart.selection.sx +
                    (event.clientX - dragStart.pointerX) / scale.value,
                sy:
                    dragStart.selection.sy +
                    (event.clientY - dragStart.pointerY) / scale.value,
                sw: dragStart.selection.sw,
                sh: dragStart.selection.sh,
            },
            natural.value.width,
            natural.value.height,
            minSourceSize.value,
            aspectRatio.value,
        );

        return;
    }

    const { px, py } = sourcePoint(event.clientX, event.clientY);
    cropTouched.value = true;
    selection.value = resizeSelection(
        dragStart.selection,
        dragMode,
        px,
        py,
        natural.value.width,
        natural.value.height,
        minSourceSize.value,
        aspectRatio.value,
    );
};

const onPointerUp = (event: PointerEvent) => {
    if (!dragMode || event.pointerId !== activePointerId) {
        return;
    }

    endDrag(event);
};

const close = () => {
    emit('update:open', false);
};

const save = () => {
    if (activeTab.value === 'alt') {
        emit('alt-text-saved', draftAltText.value.trim() || null);
        close();
        return;
    }

    const img = imageEl.value;

    if (!img || !ready.value) {
        return;
    }

    const preserveOriginal =
        editSection.value === 'appearance' && !cropTouched.value;
    const width = preserveOriginal ? natural.value.width : outputWidth.value;
    const height = preserveOriginal ? natural.value.height : outputHeight.value;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    const rect = preserveOriginal
        ? { sx: 0, sy: 0, sw: natural.value.width, sh: natural.value.height }
        : selection.value;
    processing.value = true;

    try {
        context.filter = appearanceFilter.value;
        context.drawImage(
            img,
            rect.sx,
            rect.sy,
            rect.sw,
            rect.sh,
            0,
            0,
            width,
            height,
        );
        if (warmthOpacity.value > 0) {
            context.filter = 'none';
            context.fillStyle = `rgba(${warmthColor.value}, ${warmthOpacity.value})`;
            context.fillRect(0, 0, width, height);
        }
        canvas.toBlob(
            (blob) => {
                processing.value = false;

                if (!blob) {
                    return;
                }

                emit(
                    'cropped',
                    new File([blob], outputFileName.value, {
                        type: outputMime.value,
                    }),
                    { width, height },
                );
                close();
            },
            outputMime.value,
            0.92,
        );
    } catch {
        processing.value = false;
    }
};

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            selectedPreset.value = props.aspectPresets?.[0]?.value ?? null;
            activeTab.value = 'edit';
            editSection.value = 'crop';
            draftAltText.value = props.altText ?? '';
            cropTouched.value = false;
            selectedFilter.value = 'original';
            brightness.value = 0;
            contrast.value = 0;
            saturation.value = 0;
            warmth.value = 0;
            initialized.value = false;
            processing.value = false;
            imageError.value = false;
            dragMode = null;
            activePointerId = null;
            await nextTick();
            measure();

            if (viewportEl.value && !resizeObserver) {
                resizeObserver = new ResizeObserver(() => measure());
                resizeObserver.observe(viewportEl.value);
            }
        } else {
            dragMode = null;
            activePointerId = null;
            resizeObserver?.disconnect();
            resizeObserver = null;
        }
    },
);

watch(
    () => props.src,
    () => {
        initialized.value = false;
        imageError.value = false;
        natural.value = { width: 0, height: 0 };
    },
);

watch(aspectRatio, () => {
    initialized.value = false;
    maybeInitialize();
});

onBeforeUnmount(() => resizeObserver?.disconnect());
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            :class="
                enableAppearance
                    ? 'max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-3xl'
                    : 'sm:max-w-lg'
            "
        >
            <DialogHeader>
                <DialogTitle>{{
                    $t(
                        enableAppearance
                            ? 'posts.composer.media_editor_title'
                            : 'common.photo_upload.crop_title',
                    )
                }}</DialogTitle>
                <DialogDescription>{{
                    $t('common.photo_upload.crop_description')
                }}</DialogDescription>
            </DialogHeader>

            <div
                v-if="enableAltText"
                class="flex gap-2 border-b border-border pb-2"
            >
                <Button
                    type="button"
                    size="sm"
                    :variant="activeTab === 'edit' ? 'default' : 'ghost'"
                    data-testid="media-editor-edit-tab"
                    @click="activeTab = 'edit'"
                    >{{ $t('posts.composer.media_edit_tab') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    :variant="activeTab === 'alt' ? 'default' : 'ghost'"
                    data-testid="media-editor-alt-tab"
                    @click="activeTab = 'alt'"
                    >{{ $t('posts.composer.media_alt_tab') }}</Button
                >
            </div>

            <div
                v-if="activeTab === 'edit' && enableAppearance"
                class="flex gap-1 rounded-lg bg-muted p-1"
            >
                <Button
                    type="button"
                    size="sm"
                    class="flex-1"
                    :variant="editSection === 'crop' ? 'secondary' : 'ghost'"
                    data-testid="media-editor-crop-tab"
                    @click="editSection = 'crop'"
                    >{{ $t('posts.composer.media_crop_tab') }}</Button
                >
                <Button
                    type="button"
                    size="sm"
                    class="flex-1"
                    :variant="
                        editSection === 'appearance' ? 'secondary' : 'ghost'
                    "
                    data-testid="media-editor-appearance-tab"
                    @click="editSection = 'appearance'"
                    >{{ $t('posts.composer.media_appearance_tab') }}</Button
                >
            </div>

            <div v-if="activeTab === 'alt'" class="space-y-2">
                <label for="media-editor-alt-text" class="text-sm font-medium">
                    {{ $t('posts.composer.media_alt_label') }}
                </label>
                <textarea
                    id="media-editor-alt-text"
                    v-model="draftAltText"
                    data-testid="media-editor-alt-text"
                    maxlength="2000"
                    rows="5"
                    class="w-full rounded-lg border border-border bg-background p-3 text-sm"
                    :placeholder="$t('posts.composer.media_alt_placeholder')"
                />
            </div>

            <div
                v-if="
                    activeTab === 'edit' &&
                    editSection === 'crop' &&
                    aspectPresets?.length
                "
                class="flex flex-wrap gap-2"
            >
                <Button
                    v-for="preset in aspectPresets"
                    :key="preset.value"
                    type="button"
                    size="sm"
                    :variant="
                        selectedPreset === preset.value ? 'default' : 'outline'
                    "
                    :data-testid="`crop-aspect-${preset.value.replace(':', '-')}`"
                    @click="
                        selectedPreset = preset.value;
                        cropTouched = true;
                    "
                >
                    {{ preset.label }}
                </Button>
            </div>

            <div
                v-show="activeTab === 'edit'"
                ref="viewportEl"
                class="relative aspect-square w-full touch-none overflow-hidden rounded-xl border border-border bg-muted select-none"
                :class="
                    enableAppearance ? 'mx-auto max-w-[min(55vh,36rem)]' : ''
                "
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @wheel.prevent
            >
                <img
                    v-if="src && !imageError"
                    ref="imageEl"
                    :src="src"
                    alt=""
                    draggable="false"
                    class="pointer-events-none absolute max-w-none"
                    :style="imageStyle"
                    @load="onImageLoad"
                    @error="onImageError"
                />
                <div
                    v-if="ready && warmthOpacity > 0"
                    class="pointer-events-none absolute"
                    :style="warmthStyle"
                />
                <div
                    v-if="imageError"
                    class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-muted-foreground"
                >
                    {{ $t('common.photo_upload.crop_error') }}
                </div>
                <div
                    v-else-if="ready && (editSection === 'crop' || cropTouched)"
                    class="absolute cursor-move"
                    :style="selectionStyle"
                    @pointerdown="onSelectionPointerDown"
                >
                    <div
                        class="pointer-events-none absolute inset-0 border-2 border-white shadow-[0_0_0_1px_rgba(0,0,0,0.4)]"
                    />
                    <span
                        class="absolute top-0 left-0 size-3 cursor-nwse-resize rounded-sm border border-foreground bg-white"
                        @pointerdown.stop="onHandlePointerDown('nw', $event)"
                    />
                    <span
                        class="absolute top-0 right-0 size-3 cursor-nesw-resize rounded-sm border border-foreground bg-white"
                        @pointerdown.stop="onHandlePointerDown('ne', $event)"
                    />
                    <span
                        class="absolute bottom-0 left-0 size-3 cursor-nesw-resize rounded-sm border border-foreground bg-white"
                        @pointerdown.stop="onHandlePointerDown('sw', $event)"
                    />
                    <span
                        class="absolute right-0 bottom-0 size-3 cursor-nwse-resize rounded-sm border border-foreground bg-white"
                        @pointerdown.stop="onHandlePointerDown('se', $event)"
                    />
                </div>
            </div>

            <p
                v-if="activeTab === 'edit' && editSection === 'crop'"
                class="text-center text-xs text-muted-foreground"
            >
                {{ $t('common.photo_upload.crop_hint') }}
            </p>

            <div
                v-if="activeTab === 'edit' && editSection === 'appearance'"
                class="space-y-4"
            >
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="filter in filterPresets"
                        :key="filter"
                        type="button"
                        class="overflow-hidden rounded-lg border text-xs"
                        :class="
                            selectedFilter === filter
                                ? 'border-primary ring-1 ring-primary'
                                : 'border-border'
                        "
                        :data-testid="`media-filter-${filter}`"
                        @click="selectedFilter = filter"
                    >
                        <img
                            v-if="src"
                            :src="src"
                            alt=""
                            class="aspect-video w-full object-cover"
                            :style="{ filter: FILTER_CSS[filter] }"
                        />
                        <span class="block py-1">{{
                            $t(`posts.composer.media_filter_${filter}`)
                        }}</span>
                    </button>
                </div>
                <label
                    v-for="(value, key) in adjustments"
                    :key="key"
                    class="block space-y-1 text-xs"
                >
                    <span class="flex justify-between"
                        ><span>{{ $t(`posts.composer.media_${key}`) }}</span
                        ><span>{{ value }}%</span></span
                    >
                    <input
                        :value="value"
                        :data-testid="`media-adjust-${key}`"
                        type="range"
                        min="-100"
                        max="100"
                        class="w-full accent-primary"
                        @input="
                            setAdjustment(
                                key,
                                Number(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            )
                        "
                    />
                </label>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close">
                    {{ $t('common.photo_upload.crop_cancel') }}
                </Button>
                <Button
                    type="button"
                    data-testid="crop-save"
                    :disabled="
                        activeTab === 'edit' &&
                        (processing ||
                            !ready ||
                            (editSection === 'appearance' &&
                                !hasAppearanceChanges &&
                                !cropTouched))
                    "
                    @click="save"
                >
                    {{
                        $t(
                            activeTab === 'alt'
                                ? 'posts.composer.media_alt_save'
                                : 'common.photo_upload.crop_save',
                        )
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
