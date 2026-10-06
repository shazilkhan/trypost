import { useHttp, usePage } from '@inertiajs/vue3';
import {
    computed,
    inject,
    type InjectionKey,
    provide,
    type Ref,
    ref,
    shallowRef,
} from 'vue';

import type { ComposerAccount } from '@/composables/usePostComposition';
import { live as composerLive } from '@/routes/app/posts/composer';
import type { PinterestBoardsPayload, SharedData } from '@/types';
import type {
    ChannelTikTokCreatorInfo,
    SidebarChannel,
} from '@/types/channel';
import type { PostingSchedule } from '@/types/posting-schedule';

export interface ComposerSharedData {
    accounts: Record<
        string,
        {
            timezone: string | null;
            posting_schedule: PostingSchedule | null;
            has_posting_schedule: boolean;
            platform_config: Record<string, any>;
        }
    >;
    signatures: { id: string; name: string; content: string }[];
    labels: { id: string; name: string; color: string }[];
}

interface ComposerLiveData {
    takenSlots: Record<string, string[]>;
    pinterestBoards: Record<string, PinterestBoardsPayload>;
    tiktokCreatorInfos: Record<string, ChannelTikTokCreatorInfo>;
    composer?: ComposerSharedData;
    composerKey?: string;
}

export interface ComposerLiveState {
    loaded: Readonly<Ref<boolean>>;
    failed: Readonly<Ref<boolean>>;
    retry: () => void;
}

export const composerLiveKey: InjectionKey<ComposerLiveState> =
    Symbol('composerLive');

/**
 * Whether the per-open data (taken slots, Pinterest boards, TikTok creator
 * info) has arrived. Outside a composer it is always loaded.
 */
export const useComposerLiveState = (): ComposerLiveState =>
    inject(composerLiveKey, {
        loaded: ref(true),
        failed: ref(false),
        retry: () => {},
    });

/**
 * A bundle the live endpoint sent because the page's once prop went stale
 * (the user changed a channel or a signature without a page visit). It holds
 * only while the page still carries the key it replaced.
 */
const refreshedBundle = shallowRef<{
    replacedKey: string;
    key: string;
    bundle: ComposerSharedData;
} | null>(null);

/**
 * The composer's data: the workspace bundle shared once (`composer`), the
 * channels shared on every page, and what is read fresh on each open (taken
 * slots, Pinterest boards, TikTok creator info). The live request also sends
 * the bundle's key, so the user's own out-of-band changes come back with it.
 */
export const useComposerData = () => {
    const page = usePage<SharedData>();
    const http = useHttp<Record<string, never>, ComposerLiveData>({});
    const live = shallowRef<ComposerLiveData | null>(null);
    const loaded = ref(false);
    const failed = ref(false);
    let latestLoad = 0;

    const pageKey = computed(
        () =>
            Object.entries(page.onceProps ?? {}).find(
                ([, once]) => once.prop === 'composer',
            )?.[0] ?? '',
    );

    const refreshed = computed(() =>
        refreshedBundle.value?.replacedKey === pageKey.value
            ? refreshedBundle.value
            : null,
    );

    const composer = computed(
        () =>
            refreshed.value?.bundle ??
            (page.props.composer as ComposerSharedData | undefined) ??
            null,
    );

    const socialAccounts = computed<ComposerAccount[]>(() =>
        ((page.props.channels as SidebarChannel[] | undefined) ?? []).map(
            (channel) => {
                const details = composer.value?.accounts[channel.id];

                return {
                    ...channel,
                    display_name: channel.display_name ?? '',
                    timezone: details?.timezone ?? channel.timezone,
                    has_posting_schedule:
                        details?.has_posting_schedule ?? false,
                    posting_schedule: details?.posting_schedule ?? null,
                    taken_slots: live.value?.takenSlots[channel.id] ?? [],
                };
            },
        ),
    );

    const platformConfigs = computed<Record<string, any>>(() =>
        Object.fromEntries(
            Object.entries(composer.value?.accounts ?? {}).map(
                ([id, details]) => [id, details.platform_config],
            ),
        ),
    );

    const signatures = computed(() => composer.value?.signatures ?? []);
    const labels = computed(() => composer.value?.labels ?? []);
    const pinterestBoards = computed(
        () => live.value?.pinterestBoards ?? {},
    );
    const tiktokCreatorInfos = computed(
        () => live.value?.tiktokCreatorInfos ?? {},
    );

    const load = async (): Promise<void> => {
        const loadId = ++latestLoad;
        const replacedKey = pageKey.value;
        live.value = null;
        loaded.value = false;
        failed.value = false;
        try {
            const result = (await http.get(
                composerLive.url({
                    query: { key: refreshed.value?.key ?? replacedKey },
                }),
            )) as ComposerLiveData;
            if (loadId !== latestLoad) return;
            if (result.composer && result.composerKey) {
                refreshedBundle.value = {
                    replacedKey,
                    key: result.composerKey,
                    bundle: result.composer,
                };
            }
            live.value = result;
            loaded.value = true;
        } catch {
            if (loadId === latestLoad) failed.value = true;
        }
    };

    const reset = (): void => {
        ++latestLoad;
        live.value = null;
        loaded.value = false;
        failed.value = false;
    };

    const retry = (): void => {
        void load();
    };

    provide(composerLiveKey, { loaded, failed, retry });

    return {
        composer,
        socialAccounts,
        platformConfigs,
        signatures,
        labels,
        pinterestBoards,
        tiktokCreatorInfos,
        load,
        reset,
    };
};
