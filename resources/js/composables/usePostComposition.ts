import { computed, ref } from 'vue';

import { getContentTypeOptions } from '@/composables/usePlatformLogo';
import type { MediaItem } from '@/types/media';
import { Platform } from '@/types/platform';
import type { QueuePositionValue, ScheduleModeValue } from '@/types/post';

export interface ComposerAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
    has_posting_schedule?: boolean;
}

export interface DestinationDraft {
    social_account_id: string;
    content_type: string;
    meta: Record<string, any>;
    content?: string;
    media?: MediaItem[];
}

export interface PostComposition {
    content: string;
    media: MediaItem[];
    scheduled_at: string | null;
    queue?: QueuePositionValue | null;
    status: 'draft' | 'scheduled' | 'publishing';
    label_ids: string[];
    destinations: DestinationDraft[];
}

export interface ComposerInitialPost {
    content: string;
    media: MediaItem[];
    scheduled_at: string | null;
    status: string;
    schedule_mode?: ScheduleModeValue | null;
    queue_position?: QueuePositionValue | null;
    social_account_id: string;
    content_type: string;
    meta: Record<string, any>;
    label_ids: string[];
}

export interface ComposerInitialDraft {
    content: string;
    media: MediaItem[];
    scheduled_at: string | null;
    label_ids: string[];
}

export type DestinationOverride = Partial<
    Pick<DestinationDraft, 'content' | 'media' | 'content_type' | 'meta'>
>;

type Override = DestinationOverride;

const owns = (value: object, key: string): boolean =>
    Object.prototype.hasOwnProperty.call(value, key);

export const usePostComposition = (
    accounts: () => ComposerAccount[],
    initial?: ComposerInitialPost | null,
    initialDraft?: ComposerInitialDraft | null,
) => {
    const content = ref(initial?.content ?? initialDraft?.content ?? '');
    const media = ref<MediaItem[]>(initial?.media ?? initialDraft?.media ?? []);
    const scheduledAt = ref(
        initial?.scheduled_at ?? initialDraft?.scheduled_at ?? '',
    );
    const labelIds = ref(initial?.label_ids ?? initialDraft?.label_ids ?? []);
    const selectedAccountIds = ref<string[]>(
        initial ? [initial.social_account_id] : [],
    );
    const overrides = ref<Record<string, Override>>(
        initial
            ? {
                  [initial.social_account_id]: {
                      content_type: initial.content_type,
                      meta: initial.meta,
                  },
              }
            : {},
    );

    const selectedAccounts = computed(() =>
        selectedAccountIds.value
            .map((id) => accounts().find((account) => account.id === id))
            .filter((account): account is ComposerAccount => Boolean(account)),
    );

    const toggleAccount = (id: string): void => {
        if (initial) {
            return;
        }

        if (selectedAccountIds.value.includes(id)) {
            selectedAccountIds.value = selectedAccountIds.value.filter(
                (selected) => selected !== id,
            );
            const next = { ...overrides.value };
            delete next[id];
            overrides.value = next;
            adoptSingleDestination();
        } else if (accounts().some((account) => account.id === id)) {
            selectedAccountIds.value = [...selectedAccountIds.value, id];
        }
    };

    const setOverride = (
        id: string,
        field: keyof Override,
        value: Override[keyof Override],
    ): void => {
        if (!selectedAccountIds.value.includes(id)) {
            return;
        }

        if (selectedAccountIds.value.length === 1 && field === 'content') {
            content.value = (value as string | undefined) ?? '';

            return;
        }

        if (selectedAccountIds.value.length === 1 && field === 'media') {
            media.value = (value as MediaItem[] | undefined) ?? [];

            return;
        }

        overrides.value = {
            ...overrides.value,
            [id]: { ...(overrides.value[id] ?? {}), [field]: value },
        };
    };

    const clearOverride = (id: string, field: keyof Override): void => {
        const next = { ...(overrides.value[id] ?? {}) };
        delete next[field];
        overrides.value = { ...overrides.value, [id]: next };
    };

    const withSharedAltText = (items: MediaItem[]): MediaItem[] =>
        items.map((item) => {
            const altText = item.meta?.alt_text?.trim()
                ? null
                : media.value.find((shared) => shared.id === item.id)?.meta
                      ?.alt_text;

            return altText
                ? { ...item, meta: { ...item.meta, alt_text: altText } }
                : item;
        });

    const adoptSingleDestination = (): void => {
        if (selectedAccountIds.value.length !== 1) {
            return;
        }

        const [id] = selectedAccountIds.value;
        const override = overrides.value[id] ?? {};
        const { content: ownContent, media: ownMedia, ...rest } = override;

        if (owns(override, 'content')) {
            content.value = ownContent ?? '';
        }

        if (owns(override, 'media')) {
            media.value = withSharedAltText(ownMedia ?? []);
        }

        overrides.value = { ...overrides.value, [id]: rest };
    };

    const resolvedDestination = (
        account: ComposerAccount,
    ): DestinationDraft & { content: string; media: MediaItem[] } => {
        const override = overrides.value[account.id] ?? {};
        const meta = override.meta ?? {};
        const isInstagram =
            account.platform === Platform.Instagram ||
            account.platform === Platform.InstagramFacebook;

        return {
            social_account_id: account.id,
            content_type:
                override.content_type ??
                getContentTypeOptions(account.platform)[0]?.value ??
                '',
            meta:
                isInstagram && owns(meta, 'aspect_ratio')
                    ? { ...meta, aspect_ratio: null }
                    : meta,
            content: owns(override, 'content')
                ? (override.content ?? '')
                : content.value,
            media: owns(override, 'media')
                ? withSharedAltText(override.media ?? [])
                : media.value,
        };
    };

    const materialize = (
        status: PostComposition['status'],
        queue: QueuePositionValue | null = null,
    ): PostComposition => ({
        content: content.value,
        media: [...media.value],
        scheduled_at:
            status === 'scheduled' && !queue ? scheduledAt.value || null : null,
        queue: status === 'scheduled' ? queue : null,
        status,
        label_ids: [...labelIds.value],
        destinations: selectedAccounts.value.map((account) =>
            resolvedDestination(account),
        ),
    });

    return {
        content,
        media,
        scheduledAt,
        labelIds,
        selectedAccountIds,
        selectedAccounts,
        overrides,
        toggleAccount,
        setOverride,
        clearOverride,
        adoptSingleDestination,
        resolvedDestination,
        materialize,
    };
};
