<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { IconExternalLink, IconPlugConnected } from '@tabler/icons-vue';
import { ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import McpAdvancedClients from '@/components/mcp/McpAdvancedClients.vue';
import McpPrimarySetup from '@/components/mcp/McpPrimarySetup.vue';
import SettingsListRow from '@/components/settings/SettingsListRow.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import date from '@/date';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { disconnect as mcpDisconnect } from '@/routes/app/mcp';

interface ConnectedClient {
    client_id: string;
    name: string;
    can_disconnect: boolean;
    last_used_at: string | null;
}

defineProps<{
    mcpUrl: string;
    connectedClients: ConnectedClient[];
}>();

const docsUrl = 'https://docs.trypost.it/ai/introduction';
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

usePoll(1000, {
    only: ['connectedClients'],
});

const confirmDisconnect = (client: ConnectedClient): void => {
    deleteModal.value?.open({
        url: mcpDisconnect.url({ client: client.client_id }),
        confirmText: client.name,
    });
};
</script>

<template>
    <Head :title="$t('mcp.title')" />

    <SettingsLayout
        :title="$t('mcp.title')"
        :description="$t('mcp.subtitle')"
    >
        <div class="flex flex-col gap-10">
                <section class="space-y-6">
                    <McpPrimarySetup
                        :mcp-url="mcpUrl"
                        :copied-message="$t('mcp.copied')"
                    />
                    <McpAdvancedClients :mcp-url="mcpUrl" />
                </section>

                <SettingsSection
                    :title="$t('mcp.connected_title')"
                    :description="$t('mcp.connected_description')"
                >
                    <div
                        v-if="connectedClients.length === 0"
                        class="rounded-xl border border-dashed border-border-strong px-4 py-6 text-center text-sm text-muted-foreground"
                        data-testid="mcp-connected-empty"
                    >
                        {{ $t('mcp.connected_empty') }}
                    </div>

                    <div v-else class="flex flex-col gap-2">
                        <SettingsListRow
                            v-for="client in connectedClients"
                            :key="client.client_id"
                            as="div"
                            :icon="IconPlugConnected"
                            :data-testid="`mcp-connected-client-${client.client_id}`"
                        >
                            <div
                                class="truncate text-sm leading-tight font-emphasis text-foreground"
                            >
                                {{ client.name }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <span class="relative flex size-2">
                                    <span
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success/60 motion-reduce:hidden"
                                    />
                                    <span
                                        class="relative inline-flex size-2 rounded-full bg-success"
                                    />
                                </span>
                                <span>
                                    {{ $t('mcp.last_used') }}:
                                    {{
                                        client.last_used_at
                                            ? date.diffForHumans(
                                                  client.last_used_at,
                                              )
                                            : $t('mcp.never')
                                    }}
                                </span>
                            </div>
                            <template #actions>
                                <Button
                                    v-if="client.can_disconnect"
                                    variant="outline"
                                    size="sm"
                                    class="shrink-0"
                                    @click="confirmDisconnect(client)"
                                >
                                    {{ $t('mcp.disconnect') }}
                                </Button>
                            </template>
                        </SettingsListRow>
                    </div>
                </SettingsSection>

                <SettingsSection
                    :title="$t('mcp.documentation_title')"
                    :description="$t('mcp.documentation_description')"
                >
                    <Button
                        as="a"
                        variant="outline"
                        class="self-start"
                        target="_blank"
                        :href="docsUrl"
                    >
                        <IconExternalLink class="size-4" />
                        {{ $t('mcp.view_docs') }}
                    </Button>
                </SettingsSection>
        </div>

        <ConfirmDeleteModal
            ref="deleteModal"
            method="delete"
            :title="$t('mcp.disconnect_title')"
            :description="$t('mcp.disconnect_confirm')"
            :action="$t('mcp.disconnect')"
        />
    </SettingsLayout>
</template>
