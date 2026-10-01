<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconDots,
    IconGripHorizontal,
    IconPencil,
    IconPlus,
    IconTrash,
} from '@tabler/icons-vue';
import { nextTick, onBeforeUnmount, onMounted, ref, type Directive } from 'vue';

import IdeaCard from '@/components/create/ideas/IdeaCard.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type {
    IdeaBoardCardItem,
    IdeaBoardColumnItem,
    IdeaCardIndicator,
    IdeaColumnIndicator,
} from '@/composables/useIdeaBoard';
import {
    columnKey,
    type IdeaCard as IdeaCardData,
    type IdeaStage,
    type IdeaLabel,
} from '@/types/idea';

const props = defineProps<{
    stage: IdeaStage | null;
    count: number;
    cards: IdeaCardData[];
    stages: IdeaStage[];
    labels: Map<string, IdeaLabel>;
    selectedIds: Set<string>;
    movable: boolean;
    newIdeaHref: string;
    cardIndicator: IdeaCardIndicator | null;
    columnIndicator: IdeaColumnIndicator | null;
    registerCard: (el: HTMLElement, item: IdeaBoardCardItem) => () => void;
    registerColumn: (el: HTMLElement, column: IdeaBoardColumnItem) => () => void;
    registerColumnHandle: (
        handle: HTMLElement,
        column: HTMLElement,
        item: { stageId: string },
    ) => () => void;
}>();

const emit = defineEmits<{
    rename: [name: string];
    delete: [];
}>();

const key = columnKey(props.stage?.id ?? null);
const columnEl = ref<HTMLElement | null>(null);
const handleEl = ref<HTMLElement | null>(null);
const renameInput = ref<HTMLInputElement | null>(null);
const renaming = ref(false);
const draftName = ref('');
const cleanups: (() => void)[] = [];

onMounted(() => {
    if (!columnEl.value) {
        return;
    }

    cleanups.push(
        props.registerColumn(columnEl.value, {
            stageId: props.stage?.id ?? null,
        }),
    );

    if (props.stage && handleEl.value) {
        cleanups.push(
            props.registerColumnHandle(handleEl.value, columnEl.value, {
                stageId: props.stage.id,
            }),
        );
    }
});

onBeforeUnmount(() => cleanups.forEach((cleanup) => cleanup()));

const cardCleanups = new WeakMap<HTMLElement, () => void>();

const unbindCard = (el: HTMLElement): void => {
    cardCleanups.get(el)?.();
    cardCleanups.delete(el);
};

const bindCard = (el: HTMLElement, item: IdeaBoardCardItem | null): void => {
    if (item) {
        cardCleanups.set(el, props.registerCard(el, item));
    }
};

const vIdeaCard: Directive<HTMLElement, IdeaBoardCardItem | null> = {
    mounted: (el, { value }) => bindCard(el, value),
    updated: (el, { value, oldValue }) => {
        if (
            value?.ideaId === oldValue?.ideaId &&
            value?.stageId === oldValue?.stageId
        ) {
            return;
        }

        unbindCard(el);
        bindCard(el, value);
    },
    unmounted: (el) => unbindCard(el),
};

const sortableItem = (card: IdeaCardData): IdeaBoardCardItem | null =>
    props.movable ? { ideaId: card.id, stageId: card.idea_stage_id } : null;

const startRename = async (): Promise<void> => {
    draftName.value = props.stage?.name ?? '';
    renaming.value = true;
    await nextTick();
    renameInput.value?.focus();
    renameInput.value?.select();
};

const finishRename = (save: boolean): void => {
    if (!renaming.value) {
        return;
    }

    renaming.value = false;
    const name = draftName.value.trim();

    if (save && name && name !== props.stage?.name) {
        emit('rename', name);
    }
};
</script>

<template>
    <section
        ref="columnEl"
        class="group/column relative flex h-full w-60 shrink-0 flex-col rounded-lg bg-muted transition-[opacity,background-color] duration-150 data-card-over:bg-secondary data-dragging:opacity-40"
        :aria-label="stage ? stage.name : $t('create.ideas.unassigned')"
        :data-testid="`idea-column-${key}`"
    >
        <div
            v-if="columnIndicator && stage && columnIndicator.stageId === stage.id"
            class="pointer-events-none absolute inset-y-0 w-0.5 rounded-full bg-primary-strong"
            :class="
                columnIndicator.edge === 'left' ? '-left-[9px]' : '-right-[9px]'
            "
            :data-testid="`idea-column-drop-indicator-${key}`"
        />

        <button
            v-if="stage"
            ref="handleEl"
            type="button"
            class="absolute top-0 left-1/2 flex h-4 w-8 -translate-x-1/2 cursor-grab items-center justify-center rounded-b-md text-muted-foreground opacity-0 transition-opacity duration-150 group-hover/column:opacity-100 focus-visible:opacity-100 active:cursor-grabbing"
            :aria-label="$t('create.ideas.reorder_stage', { stage: stage.name })"
            :data-testid="`idea-stage-handle-${stage.id}`"
        >
            <IconGripHorizontal class="size-4" />
        </button>

        <header class="flex h-12 shrink-0 items-center gap-1 ps-3 pe-2 pt-2">
            <input
                v-if="renaming"
                ref="renameInput"
                v-model="draftName"
                type="text"
                class="h-7 min-w-0 flex-1 rounded-md border border-input bg-card px-2 text-sm font-emphasis text-foreground outline-none focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                :aria-label="$t('create.ideas.rename')"
                :data-testid="`idea-stage-rename-input-${key}`"
                @keydown.enter.prevent="finishRename(true)"
                @keydown.esc.prevent="finishRename(false)"
                @blur="finishRename(true)"
            />
            <template v-else>
                <h2
                    class="truncate text-sm leading-5 font-emphasis text-foreground"
                    :title="stage?.name"
                >
                    {{ stage ? stage.name : $t('create.ideas.unassigned') }}
                </h2>
                <span
                    class="inline-flex h-4.5 min-w-4.5 shrink-0 items-center justify-center rounded-full bg-secondary px-1 text-xs leading-[18px] font-medium text-muted-foreground"
                    :data-testid="`idea-column-count-${key}`"
                    >{{ count }}</span
                >
            </template>
            <span class="flex-1" />
            <Button
                variant="ghost"
                size="icon-xs"
                as-child
                class="shrink-0 hover:bg-secondary"
            >
                <Link
                    :href="newIdeaHref"
                    preserve-state
                    preserve-scroll
                    :only="['editor']"
                    :aria-label="$t('create.ideas.new')"
                    :data-testid="`idea-column-add-${key}`"
                >
                    <IconPlus class="size-4" />
                </Link>
            </Button>
            <DropdownMenu v-if="stage">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        class="shrink-0 hover:bg-secondary data-[state=open]:bg-secondary"
                        :aria-label="$t('create.ideas.more')"
                        :data-testid="`idea-column-menu-${stage.id}`"
                    >
                        <IconDots class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="w-64"
                    @close-auto-focus.prevent
                >
                    <DropdownMenuItem
                        :data-testid="`idea-stage-rename-${stage.id}`"
                        @click="startRename"
                    >
                        <IconPencil class="size-4" />
                        {{ $t('create.ideas.rename') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        class="items-start"
                        :data-testid="`idea-stage-delete-${stage.id}`"
                        @click="emit('delete')"
                    >
                        <IconTrash class="mt-px size-4" />
                        <span class="flex flex-col gap-0.5">
                            <span>{{ $t('create.ideas.delete_stage') }}</span>
                            <span
                                class="text-xs leading-4 font-normal text-muted-foreground"
                                >{{ $t('create.ideas.delete_stage_hint') }}</span
                            >
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>

        <div
            class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-[9px] pt-1 pb-2"
        >
            <div
                v-for="card in cards"
                :key="card.id"
                v-idea-card="sortableItem(card)"
                class="relative transition-opacity duration-150 data-dragging:opacity-40"
            >
                <div
                    v-if="cardIndicator?.ideaId === card.id"
                    class="pointer-events-none absolute inset-x-0 h-0.5 rounded-full bg-primary-strong"
                    :class="
                        cardIndicator.edge === 'top'
                            ? '-top-[5px]'
                            : '-bottom-[5px]'
                    "
                    :data-testid="`idea-card-drop-indicator-${card.id}`"
                />
                <IdeaCard
                    :card="card"
                    view="board"
                    :stages="stages"
                    :labels="labels"
                    :selected="selectedIds.has(card.id)"
                    :selecting="selectedIds.size > 0"
                />
            </div>

            <Link
                :href="newIdeaHref"
                preserve-state
                preserve-scroll
                :only="['editor']"
                class="flex h-8 shrink-0 items-center gap-1 rounded-lg px-2 text-sm font-medium text-muted-foreground transition-control hover:bg-secondary hover:text-foreground"
                :data-testid="`idea-column-new-${key}`"
            >
                <IconPlus class="size-4" />
                {{ $t('create.ideas.new') }}
            </Link>
        </div>
    </section>
</template>
