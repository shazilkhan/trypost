<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconEye, IconPlus, IconTrash, IconWebhook } from '@tabler/icons-vue';
import { ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import SettingsListRow from '@/components/settings/SettingsListRow.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CreateWebhookDialog from '@/components/webhook/CreateWebhookDialog.vue';
import date from '@/date';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { destroy, show } from '@/routes/app/webhooks';
import type { Webhook } from '@/types/webhook';
import { webhookStatusVariant } from '@/types/webhook-status';

defineProps<{
    webhooks: Webhook[];
}>();

const createDialogOpen = ref(false);
const confirmDeleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(
    null,
);

const openWebhook = (webhook: Webhook) => {
    router.visit(show.url(webhook));
};

const handleDelete = (webhook: Webhook) => {
    confirmDeleteModal.value?.open({
        url: destroy.url(webhook),
    });
};
</script>

<template>
    <Head :title="$t('webhooks.title')" />

    <SettingsLayout
        :title="$t('webhooks.title')"
        :description="$t('webhooks.description')"
    >
        <template #actions>
            <Button
                data-testid="create-webhook-button"
                @click="createDialogOpen = true"
            >
                <IconPlus class="size-4" />
                {{ $t('webhooks.new') }}
            </Button>
        </template>

        <div
            v-if="webhooks.length === 0"
            class="rounded-xl border border-dashed border-border-strong"
        >
            <EmptyState
                :icon="IconWebhook"
                :title="$t('webhooks.empty_title')"
                :description="$t('webhooks.empty_description')"
            />
        </div>

        <ul
            v-else
            id="webhooks-body"
            class="flex flex-col gap-2"
            data-testid="webhooks-scroll"
        >
            <SettingsListRow
                v-for="webhook in webhooks"
                :key="webhook.id"
                :icon="IconWebhook"
                interactive
                :data-testid="`webhook-row-${webhook.id}`"
                @click="openWebhook(webhook)"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <p
                        class="truncate text-sm leading-tight font-emphasis text-foreground"
                    >
                        {{ webhook.endpoint }}
                    </p>
                    <Badge
                        :variant="webhookStatusVariant(webhook.status)"
                        class="shrink-0"
                    >
                        {{ $t(`webhooks.status.${webhook.status}`) }}
                    </Badge>
                </div>
                <p
                    class="flex flex-wrap gap-x-1.5 text-sm text-muted-foreground"
                >
                    <span>{{
                        $tChoice(
                            'webhooks.events_count',
                            webhook.events.length,
                            { count: String(webhook.events.length) },
                        )
                    }}</span>
                    <span aria-hidden="true">·</span>
                    <span>
                        {{ $t('webhooks.table.last_sent') }}:
                        {{
                            webhook.last_sent_at
                                ? date.diffForHumans(webhook.last_sent_at)
                                : $t('webhooks.never')
                        }}
                    </span>
                </p>
                <template #actions>
                    <div class="flex shrink-0 gap-1" @click.stop>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground"
                            :aria-label="$t('webhooks.actions.view')"
                            data-testid="row-actions-trigger"
                            @click="openWebhook(webhook)"
                        >
                            <IconEye class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-destructive-text"
                            :aria-label="$t('webhooks.actions.delete')"
                            data-testid="delete-webhook-button"
                            @click="handleDelete(webhook)"
                        >
                            <IconTrash class="size-4" />
                        </Button>
                    </div>
                </template>
            </SettingsListRow>
        </ul>
    </SettingsLayout>

    <CreateWebhookDialog v-model:open="createDialogOpen" />
    <ConfirmDeleteModal
        ref="confirmDeleteModal"
        :title="$t('webhooks.delete.title')"
        :description="$t('webhooks.delete.description')"
        :action="$t('webhooks.delete.confirm')"
        :cancel="$t('webhooks.delete.cancel')"
    />
</template>
