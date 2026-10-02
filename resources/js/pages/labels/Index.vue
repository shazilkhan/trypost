<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import {
    IconChartBar,
    IconDotsVertical,
    IconLayoutGrid,
    IconPencil,
    IconPlus,
    IconTag,
    IconTrash,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import CreateDialog from '@/components/labels/CreateDialog.vue';
import EditDialog from '@/components/labels/EditDialog.vue';
import SettingsSearch from '@/components/settings/SettingsSearch.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TableLoadMore } from '@/components/ui/table';
import debounce from '@/debounce';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { insights } from '@/routes/app';
import {
    destroy as labelsDestroy,
    index as labelsIndex,
} from '@/routes/app/labels';
import { index as postsIndex } from '@/routes/app/posts';

interface Label {
    id: string;
    name: string;
    color: string;
}

interface ScrollLabels {
    data: Label[];
    meta: { hasNextPage: boolean };
}

interface Props {
    labels: ScrollLabels;
    filters: { search: string };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        labelsIndex.url(),
        { search: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, reset: ['labels'] },
    );
}, 300);

watch(searchQuery, () => search());

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingLabel = ref<Label | null>(null);

const openEditDialog = (label: Label) => {
    editingLabel.value = label;
    isEditDialogOpen.value = true;
};

const handleDelete = (label: Label) => {
    deleteModal.value?.open({
        url: labelsDestroy.url(label.id),
    });
};

const labelQuery = (label: Label) => ({ query: { labels: [label.id] } });

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));
</script>

<template>
    <Head :title="$t('labels.title')" />

    <SettingsLayout
        :title="$t('labels.title')"
        :description="$t('labels.description')"
    >
        <template #actions>
            <Button
                data-testid="create-label-button"
                :aria-label="$t('labels.new_label')"
                @click="isCreateDialogOpen = true"
            >
                <IconPlus class="size-4" />
                <span class="hidden sm:inline">{{ $t('labels.new_label') }}</span>
            </Button>
        </template>

        <div class="flex flex-col gap-3">
            <SettingsSearch
                v-model="searchQuery"
                :placeholder="$t('labels.search')"
            />

            <div
                v-if="labels.data.length === 0"
                class="rounded-xl border border-dashed border-border-strong"
            >
                <EmptyState
                    :icon="IconTag"
                    :title="
                        hasActiveSearch
                            ? $t('labels.no_search_results')
                            : $t('labels.no_labels_yet')
                    "
                    :description="
                        hasActiveSearch
                            ? $t('labels.try_different_search')
                            : $t('labels.description')
                    "
                />
            </div>

            <div v-else>
                <InfiniteScroll
                    data="labels"
                    items-element="#labels-body"
                    preserve-url
                >
                    <ul id="labels-body" class="flex flex-col gap-2">
                        <li
                            v-for="label in labels.data"
                            :key="label.id"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-card p-4"
                            :data-testid="`label-row-${label.id}`"
                            @click="openEditDialog(label)"
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center"
                            >
                                <span
                                    class="size-4 rounded-full"
                                    :style="{ backgroundColor: label.color }"
                                />
                            </span>
                            <p
                                class="min-w-0 flex-1 truncate text-sm font-strong text-foreground"
                            >
                                {{ label.name }}
                            </p>
                            <div class="flex shrink-0 gap-1" @click.stop>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground"
                                    :aria-label="$t('labels.actions.edit')"
                                    @click="openEditDialog(label)"
                                >
                                    <IconPencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive-text"
                                    :aria-label="$t('labels.actions.delete')"
                                    :data-testid="`delete-label-${label.id}`"
                                    @click="handleDelete(label)"
                                >
                                    <IconTrash class="size-4" />
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-muted-foreground data-[state=open]:bg-accent"
                                            :aria-label="$t('labels.actions.more')"
                                            :data-testid="`label-menu-${label.id}`"
                                        >
                                            <IconDotsVertical class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <a
                                                :href="
                                                    postsIndex.url(
                                                        labelQuery(label),
                                                    )
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                :data-testid="`label-view-posts-${label.id}`"
                                            >
                                                <IconLayoutGrid class="size-4" />
                                                {{
                                                    $t(
                                                        'labels.actions.view_posts',
                                                    )
                                                }}
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <a
                                                :href="
                                                    insights.url(
                                                        labelQuery(label),
                                                    )
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                :data-testid="`label-open-reporting-${label.id}`"
                                            >
                                                <IconChartBar class="size-4" />
                                                {{
                                                    $t(
                                                        'labels.actions.open_reporting',
                                                    )
                                                }}
                                            </a>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </li>
                    </ul>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </SettingsLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :label="editingLabel" />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('labels.delete.title')"
        :description="$t('labels.delete.description')"
        :action="$t('labels.delete.confirm')"
        :cancel="$t('labels.delete.cancel')"
    />
</template>
