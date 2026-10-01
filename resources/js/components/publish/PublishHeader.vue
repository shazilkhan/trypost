<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconLayoutList, IconSettings } from '@tabler/icons-vue';

import HeaderTitle from '@/components/HeaderTitle.vue';
import PlatformLogo from '@/components/PlatformLogo.vue';
import GoalProgress from '@/components/publish/GoalProgress.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { settings } from '@/routes/app/channels';
import { channelName } from '@/types/channel';
import type { PublishChannel } from '@/types/publish';

defineProps<{
    channel: PublishChannel | null;
}>();

const { canManageAccounts } = useWorkspaceRole();
</script>

<template>
    <div v-if="channel" class="flex min-w-0 items-center gap-4">
        <span class="relative shrink-0">
            <Avatar
                :src="channel.avatar_url"
                :name="channelName(channel)"
                class="size-11 rounded-xl"
                fallback-class="bg-secondary text-xs font-bold"
            />
            <PlatformLogo
                :platform="channel.platform"
                size="xs"
               
                class="absolute -right-2 -bottom-1"
            />
        </span>
        <div class="min-w-0">
            <div class="flex min-w-0 items-center gap-2">
                <h1
                    class="truncate font-heading text-xl leading-6 font-medium text-foreground"
                    data-testid="header-title"
                >
                    {{ channelName(channel) }}
                </h1>
                <Button
                    v-if="canManageAccounts"
                    as-child
                    variant="ghost"
                    size="icon-xs"
                    class="shrink-0 text-muted-foreground"
                >
                    <Link
                        :href="settings.url(channel.id)"
                        :aria-label="$t('channels.settings')"
                        data-testid="publish-channel-settings"
                    >
                        <IconSettings class="size-4" />
                    </Link>
                </Button>
            </div>
            <GoalProgress
                v-if="channel.posting_goal !== null"
                :channel-id="channel.id"
                :sent="channel.sent_this_week"
                :goal="channel.posting_goal"
            />
        </div>
    </div>
    <HeaderTitle
        v-else
        :title="$t('posts.publish.all_channels')"
        :icon="IconLayoutList"
    />
</template>
