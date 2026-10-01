<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconArrowDown,
    IconArrowUp,
    IconDotsVertical,
    IconExternalLink,
    IconRefresh,
    IconSettings,
    IconTrash,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import PlatformLogo from '@/components/PlatformLogo.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getPlatformLabel } from '@/composables/usePlatformLogo';
import { settings as settingsRoute } from '@/routes/app/channels';
import { Platform } from '@/types/platform';
import {
    isConnectionLost,
    type ConnectedAccount,
} from '@/types/social-account';

const props = withDefaults(
    defineProps<{
        channel: ConnectedAccount;
        showSettings?: boolean;
        canMoveUp?: boolean;
        canMoveDown?: boolean;
    }>(),
    { showSettings: true, canMoveUp: false, canMoveDown: false },
);

const emit = defineEmits<{
    reconnect: [channel: ConnectedAccount];
    disconnect: [channel: ConnectedAccount];
    move: [channel: ConnectedAccount, offset: -1 | 1];
}>();

const lost = computed(() => isConnectionLost(props.channel));

const accountTypeKey = computed((): string | null =>
    props.channel.platform === Platform.LinkedInPage ||
    props.channel.platform === Platform.InstagramFacebook
        ? `channels.variants.${props.channel.platform}`
        : null,
);
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-xl border border-border bg-card p-4"
        :data-testid="`channel-row-${channel.id}`"
    >
        <slot name="handle" />

        <span class="relative me-1 shrink-0">
            <Avatar
                :src="channel.avatar_url"
                :name="channel.display_name || channel.username"
                class="size-10 rounded-xl"
                fallback-class="bg-secondary text-xs font-bold"
            />
            <PlatformLogo
                :platform="channel.platform"
                :size="22"
                ring="card"
                class="absolute -end-2 -bottom-px"
            />
        </span>

        <div class="min-w-0 flex-1">
            <p
                class="truncate text-sm leading-tight font-emphasis text-foreground"
                :data-testid="`channel-name-${channel.id}`"
            >
                {{ channel.display_name || channel.username }}
            </p>
            <p class="truncate text-sm text-muted-foreground">
                {{
                    accountTypeKey
                        ? $t(accountTypeKey)
                        : getPlatformLabel(channel.platform)
                }}
            </p>
        </div>

        <template v-if="lost">
            <Badge variant="warning">{{ $t('channels.connection_lost') }}</Badge>
            <Button
                size="sm"
                variant="outline"
                :data-testid="`channel-reconnect-${channel.id}`"
                @click="emit('reconnect', channel)"
            >
                {{ $t('channels.reconnect') }}
            </Button>
        </template>

        <div class="flex shrink-0 items-center">
            <Button
                v-if="showSettings"
                as-child
                variant="ghost"
                size="icon"
            >
                <Link
                    :href="settingsRoute.url(channel.id)"
                    :aria-label="$t('channels.settings')"
                    :data-testid="`channel-settings-${channel.id}`"
                >
                    <IconSettings class="size-4" />
                </Link>
            </Button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="data-[state=open]:bg-accent"
                        :aria-label="$t('channels.actions')"
                        :data-testid="`channel-menu-${channel.id}`"
                    >
                        <IconDotsVertical class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem v-if="channel.profile_url" as-child>
                        <a
                            :href="channel.profile_url"
                            target="_blank"
                            rel="noopener"
                            :data-testid="`channel-menu-profile-${channel.id}`"
                        >
                            <IconExternalLink class="size-4" />
                            {{ $t('channels.view_profile') }}
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        :data-testid="`channel-menu-reconnect-${channel.id}`"
                        @click="emit('reconnect', channel)"
                    >
                        <IconRefresh class="size-4" />
                        {{ $t('channels.reconnect') }}
                    </DropdownMenuItem>
                    <template v-if="canMoveUp || canMoveDown">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            :disabled="!canMoveUp"
                            :data-testid="`channel-menu-move-up-${channel.id}`"
                            @click="emit('move', channel, -1)"
                        >
                            <IconArrowUp class="size-4" />
                            {{ $t('channels.reorder.move_up') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :disabled="!canMoveDown"
                            :data-testid="`channel-menu-move-down-${channel.id}`"
                            @click="emit('move', channel, 1)"
                        >
                            <IconArrowDown class="size-4" />
                            {{ $t('channels.reorder.move_down') }}
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        :data-testid="`channel-disconnect-${channel.id}`"
                        @click="emit('disconnect', channel)"
                    >
                        <IconTrash class="size-4" />
                        {{ $t('channels.disconnect') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
