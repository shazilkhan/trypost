<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    IconDotsVertical,
    IconKey,
    IconPlus,
    IconRefresh,
    IconTrash,
} from '@tabler/icons-vue';
import { ref, watch } from 'vue';

import ApiKeyController from '@/actions/App/Http/Controllers/App/ApiKeyController';
import ApiKeyGeneratedDialog from '@/components/api-keys/ApiKeyGeneratedDialog.vue';
import CreateApiKeyDialog from '@/components/api-keys/CreateApiKeyDialog.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import SettingsListRow from '@/components/settings/SettingsListRow.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import date from '@/date';
import SettingsLayout from '@/layouts/SettingsLayout.vue';

interface ApiToken {
    id: string;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface Props {
    apiTokens: ApiToken[];
}

defineProps<Props>();

const page = usePage();
const generatedKey = ref('');
const generatedDialogOpen = ref(false);

watch(
    () => (page.props.flash as Record<string, unknown>)?.plainToken as
        | string
        | undefined,
    (token) => {
        if (token) {
            generatedKey.value = token;
            generatedDialogOpen.value = true;
        }
    },
    { immediate: true },
);

const onGeneratedDialogChange = (isOpen: boolean) => {
    generatedDialogOpen.value = isOpen;

    if (!isOpen) {
        generatedKey.value = '';
    }
};

const createDialogOpen = ref(false);
const confirmDeleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(
    null,
);
const confirmRegenerateModal = ref<InstanceType<
    typeof ConfirmDeleteModal
> | null>(null);
</script>

<template>
    <Head :title="$t('settings.api_keys.page_title')" />

    <SettingsLayout
        :title="$t('settings.api_keys.page_title')"
        :description="$t('settings.api_keys.description')"
    >
        <template #actions>
            <Button
                data-testid="create-api-key-button"
                @click="createDialogOpen = true"
            >
                <IconPlus class="size-4" />
                {{ $t('settings.api_keys.create') }}
            </Button>
        </template>

        <div class="flex flex-col gap-3">
            <ul v-if="apiTokens.length > 0" class="flex flex-col gap-2">
                <SettingsListRow
                    v-for="token in apiTokens"
                    :key="token.id"
                    :icon="IconKey"
                    :data-testid="`api-key-row-${token.id}`"
                >
                    <p
                        class="truncate text-sm leading-tight font-emphasis text-foreground"
                    >
                        {{ token.name }}
                    </p>
                    <p
                        class="flex flex-wrap gap-x-1.5 text-sm text-muted-foreground"
                    >
                        <span>
                            {{ $t('settings.api_keys.table.expires') }}:
                            {{
                                token.expires_at
                                    ? date.formatDate(token.expires_at)
                                    : $t('settings.api_keys.table.never')
                            }}
                        </span>
                        <span aria-hidden="true">·</span>
                        <span>
                            {{ $t('settings.api_keys.table.last_used') }}:
                            {{
                                token.last_used_at
                                    ? date.diffForHumans(token.last_used_at)
                                    : $t('settings.api_keys.table.never')
                            }}
                        </span>
                    </p>
                    <template #actions>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="shrink-0 text-muted-foreground data-[state=open]:bg-accent"
                                    :aria-label="token.name"
                                    :data-testid="`api-key-menu-${token.id}`"
                                >
                                    <IconDotsVertical class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    :data-testid="`regenerate-api-key-${token.id}`"
                                    @click="
                                        confirmRegenerateModal?.open({
                                            url: ApiKeyController.regenerate.url(
                                                token.id,
                                            ),
                                            confirmText: $t(
                                                'settings.api_keys.regenerate_modal.keyword',
                                            ),
                                        })
                                    "
                                >
                                    <IconRefresh class="size-4" />
                                    {{
                                        $t('settings.api_keys.actions.regenerate')
                                    }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    :data-testid="`delete-api-key-${token.id}`"
                                    @click="
                                        confirmDeleteModal?.open({
                                            url: ApiKeyController.destroy.url(
                                                token.id,
                                            ),
                                            confirmText: $t(
                                                'common.confirm_modal.delete_keyword',
                                            ),
                                        })
                                    "
                                >
                                    <IconTrash class="size-4" />
                                    {{ $t('settings.api_keys.actions.delete') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </SettingsListRow>
            </ul>

            <div
                v-else
                class="rounded-xl border border-dashed border-border-strong"
            >
                <EmptyState
                    :icon="IconKey"
                    :title="$t('settings.api_keys.empty.title')"
                    :description="$t('settings.api_keys.empty.description')"
                />
            </div>
        </div>
    </SettingsLayout>

    <CreateApiKeyDialog v-model:open="createDialogOpen" />

    <ApiKeyGeneratedDialog
        v-if="generatedKey"
        :open="generatedDialogOpen"
        :api-key="generatedKey"
        @update:open="onGeneratedDialogChange"
    />

    <ConfirmDeleteModal
        ref="confirmDeleteModal"
        :title="$t('settings.api_keys.delete_modal.title')"
        :description="$t('settings.api_keys.delete_modal.description')"
        :action="$t('settings.api_keys.delete_modal.action')"
    />

    <ConfirmDeleteModal
        ref="confirmRegenerateModal"
        method="post"
        :title="$t('settings.api_keys.regenerate_modal.title')"
        :description="$t('settings.api_keys.regenerate_modal.description')"
        :action="$t('settings.api_keys.regenerate_modal.action')"
    />
</template>
