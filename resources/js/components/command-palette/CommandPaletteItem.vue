<script setup lang="ts">
import PlatformLogo from '@/components/PlatformLogo.vue';
import { Avatar } from '@/components/ui/avatar';
import { CommandItem } from '@/components/ui/command';
import { channelName } from '@/types/channel';
import type {
    CommandPaletteEntry,
    CommandPaletteLabel,
    CommandPaletteTitle,
} from '@/types/command-palette';

type Translate = (key: string, replacements?: Record<string, string>) => string;

defineProps<{
    entry: CommandPaletteEntry;
    value: string;
    testId: string;
}>();

const emit = defineEmits<{
    select: [entry: CommandPaletteEntry];
}>();

const label = (value: CommandPaletteLabel, translate: Translate): string =>
    'text' in value ? value.text : translate(value.key);

const title = (value: CommandPaletteTitle, translate: Translate): string =>
    'path' in value
        ? translate('command_palette.path', {
              parent: label(value.path[0], translate),
              child: label(value.path[1], translate),
          })
        : label(value, translate);
</script>

<template>
    <CommandItem
        :value="value"
        class="min-h-11 gap-3 rounded-lg px-4 py-3 data-[highlighted]:bg-secondary data-[highlighted]:text-foreground data-[selected]:bg-secondary data-[selected]:text-foreground"
        :data-testid="testId"
        @select="emit('select', entry)"
    >
        <span
            v-if="entry.channel"
            class="relative shrink-0 self-center"
        >
            <Avatar
                :src="entry.channel.avatar_url"
                :name="channelName(entry.channel)"
                class="size-7 rounded-md"
                fallback-class="bg-secondary text-[10px] font-bold"
            />
            <PlatformLogo
                :platform="entry.channel.platform"
                :size="16"
                ring="popover"
                :title="null"
                class="absolute -end-1 -bottom-1"
            />
        </span>
        <component
            :is="entry.icon"
            v-else-if="entry.icon"
            class="size-4 shrink-0 self-center text-foreground"
        />
        <span class="grid min-w-0 flex-1">
            <span class="truncate text-sm leading-5 text-foreground">{{
                title(entry.title, $t)
            }}</span>
            <span
                v-if="entry.subtitle"
                class="truncate text-xs leading-[18px] text-muted-foreground"
                >{{ label(entry.subtitle, $t) }}</span
            >
        </span>
        <span
            v-if="entry.count"
            class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-success-subtle px-1.5 text-xs font-medium text-success-text tabular-nums"
            :data-testid="`${testId}-count`"
            >{{ entry.count }}</span
        >
    </CommandItem>
</template>
