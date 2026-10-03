<script setup lang="ts">
import {
    IconBrandBluesky,
    IconBrandDiscord,
    IconBrandFacebook,
    IconBrandGoogleFilled,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandMastodon,
    IconBrandPinterest,
    IconBrandTelegram,
    IconBrandThreads,
    IconBrandTiktok,
    IconBrandX,
    IconBrandYoutubeFilled,
} from '@tabler/icons-vue';
import { computed } from 'vue';

const props = defineProps<{
    platform: string;
}>();

const icons = {
    linkedin: IconBrandLinkedin,
    'linkedin-page': IconBrandLinkedin,
    x: IconBrandX,
    tiktok: IconBrandTiktok,
    youtube: IconBrandYoutubeFilled,
    facebook: IconBrandFacebook,
    instagram: IconBrandInstagram,
    'instagram-facebook': IconBrandInstagram,
    threads: IconBrandThreads,
    pinterest: IconBrandPinterest,
    bluesky: IconBrandBluesky,
    mastodon: IconBrandMastodon,
    telegram: IconBrandTelegram,
    discord: IconBrandDiscord,
    google_business: IconBrandGoogleFilled,
} as const;

const icon = computed(() => icons[props.platform as keyof typeof icons]);
</script>

<template>
    <component :is="icon" v-if="icon" class="size-4.5 text-muted-foreground" />
</template>
