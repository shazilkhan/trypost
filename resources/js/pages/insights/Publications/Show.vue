<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconExternalLink, IconX } from '@tabler/icons-vue';
import { computed } from 'vue';

import PublicationMetrics from '@/components/analytics/workspace/PublicationMetrics.vue';
import ChannelAvatar from '@/components/ChannelAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    getPlatformLabel,
} from '@/composables/usePlatformLogo';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import { insights as insightsRoute } from '@/routes/app';
import type { PublicationAnalyticsDetail } from '@/types/analytics';

const props = defineProps<{ detail: PublicationAnalyticsDetail }>();
const publication = computed(() => props.detail.publication);
const platformName = computed(() =>
    getPlatformLabel(publication.value.platform),
);
const accountName = computed(() =>
    publication.value.account_username
        ? `@${publication.value.account_username}`
        : publication.value.account_display_name || platformName.value,
);
const thumbnail = computed(() => {
    const candidate = publication.value.preview_metadata?.thumbnail_url;
    return typeof candidate === 'string' && /^https:\/\//i.test(candidate)
        ? candidate
        : null;
});
const providerUrl = computed(() =>
    publication.value.permalink &&
    /^https:\/\//i.test(publication.value.permalink)
        ? publication.value.permalink
        : null,
);
</script>

<template>
    <AppLayout full-width>
        <Head
            :title="
                $t('analytics.detail.page_title', { platform: platformName })
            "
        />
        <div class="min-h-0 flex-1 overflow-y-auto">
            <div
                class="mx-auto flex w-full max-w-[680px] flex-col gap-6 px-4 py-6 md:py-10"
            >
                <article
                    class="overflow-hidden rounded-2xl border border-border bg-card"
                    data-testid="analytics-publication"
                >
                    <header
                        class="flex min-h-12 items-center justify-between gap-2 border-b border-border-strong py-1 ps-6 pe-4"
                        data-testid="analytics-publication-header"
                    >
                        <p
                            class="flex min-w-0 flex-wrap items-center gap-2 text-sm text-foreground"
                        >
                            <span>{{
                                publication.origin === 'trypost'
                                    ? $t(
                                          'analytics.detail.published_via_trypost',
                                      )
                                    : $t('analytics.detail.published_on', {
                                          platform: platformName,
                                      })
                            }}</span>
                            <Badge variant="secondary" class="h-6 px-2">
                                {{
                                    $t(
                                        `analytics.detail.content_types.${publication.content_type}`,
                                    )
                                }}
                            </Badge>
                        </p>
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            class="shrink-0"
                        >
                            <Link
                                :href="insightsRoute.url()"
                                :aria-label="
                                    $t('analytics.detail.back_to_insights')
                                "
                                :title="$t('analytics.detail.back_to_insights')"
                                data-testid="analytics-publication-close"
                            >
                                <IconX class="size-4" />
                            </Link>
                        </Button>
                    </header>

                    <section class="flex flex-col gap-3 px-6 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <ChannelAvatar
                                :platform="publication.platform"
                                :src="publication.account_avatar_url"
                                :name="accountName"
                                ring="card"
                            />
                            <p
                                class="min-w-0 truncate text-sm leading-tight font-emphasis text-foreground"
                            >
                                {{ accountName }}
                            </p>
                        </div>
                        <p
                            class="text-sm break-words whitespace-pre-wrap text-foreground"
                            data-testid="analytics-publication-excerpt"
                        >
                            {{
                                publication.excerpt ||
                                $t('analytics.dashboard.no_excerpt')
                            }}
                        </p>
                        <img
                            v-if="thumbnail"
                            :src="thumbnail"
                            alt=""
                            class="aspect-[4/5] w-[180px] rounded-md object-cover"
                        />
                    </section>

                    <footer
                        v-if="publication.provider_published_at || providerUrl"
                        class="flex min-h-14 flex-wrap items-center justify-between gap-2 border-t border-border-strong px-6 py-3"
                    >
                        <p class="text-sm text-foreground">
                            <time
                                v-if="publication.provider_published_at"
                                :datetime="publication.provider_published_at"
                                >{{
                                    date.formatDateTime(
                                        publication.provider_published_at,
                                    )
                                }}</time
                            >
                        </p>
                        <Button
                            v-if="providerUrl"
                            as-child
                            variant="outline"
                        >
                            <a
                                :href="providerUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <IconExternalLink aria-hidden="true" />
                                {{ $t('analytics.dashboard.view_post') }}
                            </a>
                        </Button>
                    </footer>
                </article>

                <PublicationMetrics :detail="detail" />
            </div>
        </div>
    </AppLayout>
</template>
