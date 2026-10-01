<script setup lang="ts">
import { IconPlus } from '@tabler/icons-vue';
import { nextTick, ref } from 'vue';

import IdeaColumn from '@/components/create/ideas/IdeaColumn.vue';
import { useIdeaBoard } from '@/composables/useIdeaBoard';
import {
    columnKey,
    UNASSIGNED,
    type IdeaCard,
    type IdeaStage,
    type IdeaLabel,
} from '@/types/idea';

defineProps<{
    stages: IdeaStage[];
    columns: Record<string, IdeaCard[]>;
    counts: Record<string, number>;
    labels: Map<string, IdeaLabel>;
    selectedIds: Set<string>;
    movable: boolean;
    newIdeaHref: (stageId: string | null) => string;
}>();

const emit = defineEmits<{
    moveIdea: [ideaId: string, toStageId: string | null, orderedIdeaIds: string[]];
    reorderStages: [orderedStageIds: string[]];
    createStage: [name: string];
    renameStage: [stage: IdeaStage, name: string];
    deleteStage: [stage: IdeaStage];
}>();

const {
    registerCard,
    registerColumn,
    registerColumnHandle,
    cardIndicator,
    columnIndicator,
} = useIdeaBoard({
    onMoveIdea: (ideaId, toStageId, orderedIdeaIds) =>
        emit('moveIdea', ideaId, toStageId, orderedIdeaIds),
    onReorderStages: (orderedStageIds) =>
        emit('reorderStages', orderedStageIds),
});

const addingStage = ref(false);
const newStageName = ref('');
const newStageInput = ref<HTMLInputElement | null>(null);

const startAddingStage = async (): Promise<void> => {
    newStageName.value = '';
    addingStage.value = true;
    await nextTick();
    newStageInput.value?.focus();
};

const cancelAddingStage = (): void => {
    addingStage.value = false;
    newStageName.value = '';
};

const submitStage = (): void => {
    const name = newStageName.value.trim();

    if (!name) {
        cancelAddingStage();

        return;
    }

    emit('createStage', name);
    cancelAddingStage();
};
</script>

<template>
    <div
        class="flex min-h-0 flex-1 gap-4 overflow-x-auto overscroll-x-contain px-4 pt-4 pb-4 md:px-8"
        data-testid="ideas-board"
    >
        <IdeaColumn
            :stage="null"
            :count="counts[UNASSIGNED] ?? 0"
            :cards="columns[UNASSIGNED] ?? []"
            :stages="stages"
            :labels="labels"
            :selected-ids="selectedIds"
            :movable="movable"
            :new-idea-href="newIdeaHref(null)"
            :card-indicator="cardIndicator"
            :column-indicator="columnIndicator"
            :register-card="registerCard"
            :register-column="registerColumn"
            :register-column-handle="registerColumnHandle"
        />
        <IdeaColumn
            v-for="stage in stages"
            :key="stage.id"
            :stage="stage"
            :count="counts[columnKey(stage.id)] ?? 0"
            :cards="columns[columnKey(stage.id)] ?? []"
            :stages="stages"
            :labels="labels"
            :selected-ids="selectedIds"
            :movable="movable"
            :new-idea-href="newIdeaHref(stage.id)"
            :card-indicator="cardIndicator"
            :column-indicator="columnIndicator"
            :register-card="registerCard"
            :register-column="registerColumn"
            :register-column-handle="registerColumnHandle"
            @rename="(name) => emit('renameStage', stage, name)"
            @delete="emit('deleteStage', stage)"
        />

        <div
            v-if="addingStage"
            class="flex h-full w-60 shrink-0 flex-col rounded-lg bg-muted"
        >
            <div class="flex h-12 items-center ps-3 pe-2 pt-2">
                <input
                    ref="newStageInput"
                    v-model="newStageName"
                    type="text"
                    class="h-7 min-w-0 flex-1 rounded-md border border-input bg-card px-2 text-sm font-emphasis text-foreground outline-none placeholder:text-subtle-foreground focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                    :placeholder="$t('create.ideas.new_stage_placeholder')"
                    :aria-label="$t('create.ideas.new_stage')"
                    data-testid="idea-stage-new-input"
                    @keydown.enter.prevent="submitStage"
                    @keydown.esc.prevent="cancelAddingStage"
                    @blur="submitStage"
                />
            </div>
        </div>
        <div v-else class="shrink-0 pt-2">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-lg px-3 text-sm font-medium whitespace-nowrap text-foreground transition-control hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                data-testid="idea-stage-new"
                @click="startAddingStage"
            >
                <IconPlus class="size-4" />
                {{ $t('create.ideas.new_stage') }}
            </button>
        </div>
    </div>
</template>
