<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

import PlatformLogo from '@/components/PlatformLogo.vue';
import { Avatar } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

type ChannelAvatarSize = 24 | 28 | 32 | 40 | 44 | 64;

type ChannelAvatarRing = 'background' | 'card' | 'popover' | 'sidebar';

interface SizeSpec {
    avatar: string;
    fallback: string;
    badge: number;
    offset: string;
    reserve: string;
}

/**
 * The badge sits outside the avatar's bottom-end corner: it overhangs by about
 * a third of its size on the end side and a fifth at the bottom, and `reserve`
 * keeps that overhang clear of whatever follows the avatar.
 */
const SIZES: Record<ChannelAvatarSize, SizeSpec> = {
    24: {
        avatar: 'size-6 rounded-full',
        fallback: 'text-[9px]',
        badge: 12,
        offset: '-end-1 -bottom-0.5',
        reserve: 'me-0.5',
    },
    28: {
        avatar: 'size-7 rounded-md',
        fallback: 'text-[10px]',
        badge: 18,
        offset: '-end-1.5 -bottom-1',
        reserve: 'me-1',
    },
    32: {
        avatar: 'size-8 rounded-lg',
        fallback: 'text-[10px]',
        badge: 18,
        offset: '-end-1.5 -bottom-1',
        reserve: 'me-1',
    },
    40: {
        avatar: 'size-10 rounded-xl',
        fallback: 'text-xs',
        badge: 22,
        offset: '-end-2 -bottom-1',
        reserve: 'me-1.5',
    },
    44: {
        avatar: 'size-11 rounded-xl',
        fallback: 'text-xs',
        badge: 24,
        offset: '-end-2 -bottom-1',
        reserve: 'me-1.5',
    },
    64: {
        avatar: 'size-16 rounded-2xl',
        fallback: 'text-sm',
        badge: 28,
        offset: '-end-2.5 -bottom-1.5',
        reserve: 'me-2',
    },
};

const props = withDefaults(
    defineProps<{
        platform: string;
        name: string;
        src?: string | null;
        size?: ChannelAvatarSize;
        ring?: ChannelAvatarRing;
        reserveSpace?: boolean;
        avatarClass?: HTMLAttributes['class'];
    }>(),
    {
        src: null,
        size: 32,
        ring: 'background',
        reserveSpace: true,
        avatarClass: undefined,
    },
);

const spec = computed(() => SIZES[props.size]);
</script>

<template>
    <span
        :class="[
            'relative inline-flex shrink-0',
            reserveSpace ? spec.reserve : '',
        ]"
    >
        <Avatar
            :src="src"
            :name="name"
            :class="cn(spec.avatar, avatarClass)"
            :fallback-class="['bg-secondary font-bold', spec.fallback]"
        />
        <PlatformLogo
            :platform="platform"
            :size="spec.badge"
            :ring="ring"
            :title="null"
            :class="['absolute', spec.offset]"
        />
        <slot />
    </span>
</template>
