<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { IconHash, IconPencil, IconPlus, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import SettingsListRow from '@/components/settings/SettingsListRow.vue';
import SettingsSearch from '@/components/settings/SettingsSearch.vue';
import CreateDialog from '@/components/signatures/CreateDialog.vue';
import EditDialog from '@/components/signatures/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { TableLoadMore } from '@/components/ui/table';
import debounce from '@/debounce';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import {
    destroy as signaturesDestroy,
    index as signaturesIndex,
} from '@/routes/app/signatures';

interface Workspace {
    id: string;
    name: string;
}

interface Signature {
    id: string;
    name: string;
    content: string;
}

interface ScrollSignatures {
    data: Signature[];
    meta: { hasNextPage: boolean };
}

interface Props {
    workspace: Workspace;
    signatures: ScrollSignatures;
    filters: { search: string };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        signaturesIndex.url(),
        { search: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, reset: ['signatures'] },
    );
}, 300);

watch(searchQuery, () => search());

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingSignature = ref<Signature | null>(null);

const openEditDialog = (signature: Signature) => {
    editingSignature.value = signature;
    isEditDialogOpen.value = true;
};

const handleDelete = (signature: Signature) => {
    deleteModal.value?.open({
        url: signaturesDestroy.url(signature.id),
        confirmText: trans('common.confirm_modal.delete_keyword'),
    });
};


const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));
</script>

<template>
    <Head :title="$t('signatures.title')" />

    <SettingsLayout
        :title="$t('signatures.title')"
        :description="$t('signatures.description')"
    >
        <template #actions>
            <Button
                data-testid="create-signature-button"
                :aria-label="$t('signatures.new')"
                @click="isCreateDialogOpen = true"
            >
                <IconPlus class="size-4" />
                <span class="hidden sm:inline">{{ $t('signatures.new') }}</span>
            </Button>
        </template>

        <div class="flex flex-col gap-3">
            <SettingsSearch
                v-model="searchQuery"
                :placeholder="$t('signatures.search')"
            />

            <div
                v-if="signatures.data.length === 0"
                class="rounded-xl border border-dashed border-border-strong"
            >
                <EmptyState
                    :icon="IconHash"
                    :title="
                        hasActiveSearch
                            ? $t('signatures.no_search_results')
                            : $t('signatures.empty_title')
                    "
                    :description="
                        hasActiveSearch
                            ? $t('signatures.try_different_search')
                            : $t('signatures.empty_description')
                    "
                />
            </div>

            <div v-else>
                <InfiniteScroll
                    data="signatures"
                    items-element="#signatures-body"
                    preserve-url
                >
                    <ul id="signatures-body" class="flex flex-col gap-2">
                        <SettingsListRow
                            v-for="signature in signatures.data"
                            :key="signature.id"
                            class="cursor-pointer"
                            :data-testid="`signature-row-${signature.id}`"
                            @click="openEditDialog(signature)"
                        >
                            <p
                                class="truncate text-sm font-strong text-foreground"
                            >
                                {{ signature.name }}
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ signature.content }}
                            </p>
                            <template #actions>
                                <div class="flex shrink-0 gap-1" @click.stop>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground"
                                        :aria-label="$t('signatures.actions.edit')"
                                        @click="openEditDialog(signature)"
                                    >
                                        <IconPencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive-text"
                                        :aria-label="$t('signatures.actions.delete')"
                                        :data-testid="`delete-signature-${signature.id}`"
                                        @click="handleDelete(signature)"
                                    >
                                        <IconTrash class="size-4" />
                                    </Button>
                                </div>
                            </template>
                        </SettingsListRow>
                    </ul>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </SettingsLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :signature="editingSignature" />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('signatures.delete.title')"
        :description="$t('signatures.delete.description')"
        :action="$t('signatures.delete.confirm')"
        :cancel="$t('signatures.delete.cancel')"
    />
</template>
