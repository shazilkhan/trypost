<script setup lang="ts">
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconPlus,
    IconRepeat,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import EmptyState from '@/components/EmptyState.vue';
import HeaderTitle from '@/components/HeaderTitle.vue';
import CreateRepurposeDialog from '@/components/repurpose/CreateRepurposeDialog.vue';
import RepurposeFlow from '@/components/repurpose/RepurposeFlow.vue';
import RepurposesEmptyIllustration from '@/components/repurpose/RepurposesEmptyIllustration.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConnectChannelDialog } from '@/composables/useConnectChannelDialog';
import { useWorkspaceAbilities } from '@/composables/useWorkspaceAbilities';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import { show } from '@/routes/app/repurposes';
import type { ChannelAccount } from '@/types/channel';
import type { FlowNode, Repurpose } from '@/types/repurpose';
import { repurposeStatusVariant } from '@/types/repurpose-status';

const props = defineProps<{
    repurposes: { data: Repurpose[] };
    sourceAccounts: ChannelAccount[];
    destinationAccounts: ChannelAccount[];
}>();

const createDialogOpen = ref(false);

const { canManageAccounts } = useWorkspaceAbilities();
const { open: openConnectDialog } = useConnectChannelDialog();

const hasSourceAccounts = computed(() => props.sourceAccounts.length > 0);

const connectChannel = (): void => {
    openConnectDialog();
};

const startBlank = () => {
    createDialogOpen.value = true;
};

const destinationNodes = (repurpose: Repurpose): FlowNode[] =>
    repurpose.destinations.flatMap((destination) => {
        const account = props.destinationAccounts.find(
            (item) => item.id === destination.social_account_id,
        );

        return account
            ? [
                  {
                      platform: account.platform,
                      label: account.display_name,
                      username: account.username,
                  },
              ]
            : [];
    });
</script>

<template>
    <Head :title="$t('repurposes.title')" />

    <AppLayout full-width>
        <template #header>
            <HeaderTitle :title="$t('repurposes.title')" :icon="IconRepeat" />
        </template>

        <template #header-actions>
            <Button
                variant="outline"
                data-testid="create-repurpose-button"
                @click="startBlank"
            >
                <IconPlus aria-hidden="true" />
                {{ $t('repurposes.new') }}
            </Button>
        </template>

        <div
            class="flex h-full min-w-0 flex-1 flex-col gap-6 px-4 pt-2 pb-10 md:px-8"
        >

            <EmptyState
                v-if="repurposes.data.length === 0"
                :title="$t('repurposes.empty.title')"
                :description="$t('repurposes.empty.description')"
                data-testid="repurposes-empty"
            >
                <template #illustration>
                    <RepurposesEmptyIllustration />
                </template>
                <template v-if="hasSourceAccounts" #action>
                    <Button
                        data-testid="repurposes-empty-create"
                        @click="startBlank"
                    >
                        <IconPlus aria-hidden="true" />
                        {{ $t('repurposes.new') }}
                    </Button>
                </template>
                <template v-else-if="canManageAccounts" #action>
                    <Button
                        data-testid="repurposes-empty-connect"
                        @click="connectChannel"
                    >
                        <IconPlus aria-hidden="true" />
                        {{ $t('channels.connect') }}
                    </Button>
                </template>
            </EmptyState>

            <InfiniteScroll
                v-else
                data="repurposes"
                items-element="#repurposes-body"
                preserve-url
            >
                <ul
                    id="repurposes-body"
                    class="flex flex-col gap-2"
                    data-testid="repurposes-table"
                >
                    <li
                        v-for="repurpose in repurposes.data"
                        :key="repurpose.id"
                    >
                        <Link
                            :href="show.url(repurpose.id)"
                            class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-border bg-card p-4 transition-control hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                            :data-testid="`repurpose-row-${repurpose.id}`"
                        >
                            <RepurposeFlow
                                :source="{
                                    platform:
                                        repurpose.source_account?.platform ??
                                        '',
                                    label: repurpose.source_account
                                        ?.display_name,
                                    username:
                                        repurpose.source_account?.username,
                                }"
                                :destinations="destinationNodes(repurpose)"
                                size="sm"
                                align="start"
                            />
                            <div
                                class="ms-auto flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1"
                            >
                                <p
                                    class="text-sm text-muted-foreground tabular-nums"
                                >
                                    {{ $t('repurposes.table.published') }}
                                    <span class="text-foreground">{{
                                        repurpose.published_items_count ?? 0
                                    }}</span>
                                    <span aria-hidden="true"> · </span>
                                    {{ $t('repurposes.table.last_polled') }}
                                    <span class="text-foreground">{{
                                        repurpose.last_polled_at
                                            ? date.diffForHumans(
                                                  repurpose.last_polled_at,
                                              )
                                            : '—'
                                    }}</span>
                                </p>
                                <span class="flex items-center gap-1.5">
                                    <Badge
                                        :variant="
                                            repurposeStatusVariant(
                                                repurpose.status,
                                            )
                                        "
                                        class="h-6 px-2"
                                    >
                                        {{
                                            $t(
                                                `repurposes.status.${repurpose.status}`,
                                            )
                                        }}
                                    </Badge>
                                    <IconAlertTriangle
                                        v-if="repurpose.paused_reason"
                                        class="size-4 text-amber-500"
                                        :title="
                                            $t(
                                                'repurposes.health.stopped_itself',
                                            )
                                        "
                                        data-testid="repurpose-stopped-itself"
                                    />
                                </span>
                            </div>
                        </Link>
                    </li>
                </ul>
            </InfiniteScroll>
        </div>

        <CreateRepurposeDialog
            v-model:open="createDialogOpen"
            :source-accounts="sourceAccounts"
        />
    </AppLayout>
</template>
