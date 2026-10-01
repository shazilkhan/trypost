<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconArrowLeft,
    IconArrowRight,
    IconArrowsMaximize,
    IconArrowsMinimize,
    IconCalendarClock,
    IconCheck,
    IconChevronDown,
    IconChevronUp,
    IconEye,
    IconLibraryPhoto,
    IconLoader2,
    IconPin,
    IconPlus,
    IconSearch,
    IconSend,
    IconStar,
    IconStarFilled,
    IconTag,
    IconTemplate,
    IconWand,
    IconX,
} from '@tabler/icons-vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import {
    computed,
    effectScope,
    onMounted,
    ref,
    shallowReactive,
    watch,
} from 'vue';
import { toast } from 'vue-sonner';

import WritingAssistantPanel, {
    type AssistantChannel,
} from '@/components/ai/WritingAssistantPanel.vue';
import MediaTray from '@/components/media/MediaTray.vue';
import UnsplashDialog from '@/components/media/UnsplashDialog.vue';
import PlatformLogo from '@/components/PlatformLogo.vue';
import ComposerAccountChip from '@/components/posts/composer/ComposerAccountChip.vue';
import ComposerAccountOptions from '@/components/posts/composer/ComposerAccountOptions.vue';
import ComposerAccountStack from '@/components/posts/composer/ComposerAccountStack.vue';
import ComposerEditorToolbar from '@/components/posts/composer/ComposerEditorToolbar.vue';
import ComposerSchedulePicker from '@/components/posts/composer/ComposerSchedulePicker.vue';
import ComposerTemplatesPanel from '@/components/posts/composer/ComposerTemplatesPanel.vue';
import MediaEditorDialog, {
    type MediaEditChange,
} from '@/components/posts/composer/MediaEditorDialog.vue';
import ResumeUnfinishedPostDialog from '@/components/posts/composer/ResumeUnfinishedPostDialog.vue';
import ChannelMediaWarnings from '@/components/posts/editor/ChannelMediaWarnings.vue';
import ContentTypeRadioGroup from '@/components/posts/editor/ContentTypeRadioGroup.vue';
import DiscordSettings from '@/components/posts/editor/DiscordSettings.vue';
import FacebookSettings from '@/components/posts/editor/FacebookSettings.vue';
import GoogleBusinessSettings from '@/components/posts/editor/GoogleBusinessSettings.vue';
import LinkedInSettings from '@/components/posts/editor/LinkedInSettings.vue';
import PinterestSettings from '@/components/posts/editor/PinterestSettings.vue';
import TikTokSettings from '@/components/posts/editor/TikTokSettings.vue';
import YouTubeSettings from '@/components/posts/editor/YouTubeSettings.vue';
import PlatformPreview from '@/components/posts/previews/PlatformPreview.vue';
import PreviewPanelTitle from '@/components/posts/previews/PreviewPanelTitle.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    type AutosaveMediaRef,
    type AutosaveSnapshot,
    composerAutosaveKey,
    fromAutosaveMedia,
    isExpiredUpload,
    toAutosaveMedia,
    useComposerAutosave,
} from '@/composables/useComposerAutosave';
import {
    getMediaValidationWarning,
    mediaWarningParams,
    type MediaValidationWarning,
} from '@/composables/useMedia';
import { useMediaEditSwap, withMediaAdded } from '@/composables/useMediaEditSwap';
import {
    type MediaImportStarted,
    useMediaImport,
} from '@/composables/useMediaImport';
import { getMediaRulesForContentType } from '@/composables/useMediaRules';
import {
    type MediaUploader,
    useMediaUpload,
} from '@/composables/useMediaUpload';
import { usePageErrors } from '@/composables/usePageErrors';
import {
    getContentTypeOptions,
    getPlatformLabel,
} from '@/composables/usePlatformLogo';
import { evaluatePlatformMeta } from '@/composables/usePostCompliance';
import {
    usePostComposition,
    type ComposerAccount,
    type ComposerInitialDraft,
    type ComposerInitialPost,
    type PostComposition,
} from '@/composables/usePostComposition';
import { useXLinkDefuser } from '@/composables/useXLinkDefuser';
import date from '@/date';
import dayjs from '@/dayjs';
import { getInstagramImageAspectIssues } from '@/lib/instagramImageAspect';
import {
    editorTabsFor,
    type EditorTab,
    rulesFor,
} from '@/lib/mediaEditor';
import { isGooglePickerOpen } from '@/lib/mediaSources/googleDrive';
import { isImage, isVideo } from '@/lib/mediaType';
import { settings as channelSettings } from '@/routes/app/channels';
import { update as updatePreferences } from '@/routes/app/settings/preferences';
import type {
    MediaUploadLimits,
    PinterestBoardsPayload,
    SharedData,
    User,
} from '@/types';
import type { MediaItem } from '@/types/media';
import { Platform } from '@/types/platform';
import {
    PostStatus,
    ScheduleMode,
    type QueuePositionValue,
} from '@/types/post';
import type { TikTokPrivacyLevelValue } from '@/types/tiktok-privacy';

const props = withDefaults(
    defineProps<{
        open: boolean;
        socialAccounts: ComposerAccount[];
        initialPost?: ComposerInitialPost | null;
        initialDraft?: ComposerInitialDraft | null;
        postId?: string | null;
        openAssistant?: boolean;
        labels?: { id: string; name: string; color: string }[];
        signatures?: { id: string; name: string; content: string }[];
        initialDate?: string | null;
        initialAccountIds?: string[];
        submitting?: boolean;
        platformConfigs?: Record<string, any>;
        pinterestBoards?: Record<string, PinterestBoardsPayload>;
        tiktokCreatorInfos?: Record<
            string,
            {
                creator_nickname: string | null;
                creator_username: string | null;
                creator_avatar_url: string | null;
                privacy_level_options: TikTokPrivacyLevelValue[];
                comment_disabled: boolean;
                duet_disabled: boolean;
                stitch_disabled: boolean;
                max_video_post_duration_sec: number | null;
            }
        >;
    }>(),
    {
        initialPost: null,
        initialDraft: null,
        postId: null,
        openAssistant: false,
        labels: () => [],
        signatures: () => [],
        initialDate: null,
        initialAccountIds: () => [],
        submitting: false,
        platformConfigs: () => ({}),
        pinterestBoards: () => ({}),
        tiktokCreatorInfos: () => ({}),
    },
);

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (
        event: 'submit',
        composition: PostComposition,
        createAnother: boolean,
    ): void;
}>();

const composition = usePostComposition(
    () => props.socialAccounts,
    props.initialPost,
    props.initialDraft,
);
if (!props.initialPost && props.initialDate) {
    if (/^\d{4}-\d{2}-\d{2}$/.test(props.initialDate)) {
        composition.scheduledAt.value = `${props.initialDate}T09:00`;
    } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(props.initialDate)) {
        composition.scheduledAt.value = props.initialDate;
    }
}
if (!props.initialPost) {
    props.initialAccountIds.forEach((id) => composition.toggleAccount(id));
}
const defaultPostAction =
    (usePage().props.auth?.user as User | null)?.default_post_action ?? 'next';
if (
    !props.initialPost &&
    !composition.scheduledAt.value &&
    defaultPostAction === 'custom'
) {
    composition.scheduledAt.value = dayjs()
        .add(1, 'hour')
        .startOf('hour')
        .format('YYYY-MM-DDTHH:mm');
}
const { contentFor } = useXLinkDefuser();
const errors = usePageErrors();
const chosenStep = ref<1 | 2>(1);
type ComposerSidePanel = 'templates' | 'assistant' | 'preview';
const sidePanel = ref<ComposerSidePanel>(
    props.openAssistant ? 'assistant' : 'preview',
);
const expandedDialog = ref(false);
const mobilePanelOpen = ref(false);
const accountPickerOpen = ref(false);
const accountSearch = ref('');
const labelSearch = ref('');
const labelsOpen = ref(false);
const lastUsedAccountIds = ref<string[]>([]);
const scheduleMenuOpen = ref(false);
const schedulePanel = ref<'menu' | 'picker'>('menu');
const createAnother = ref(false);
type ComposerScheduleMode = QueuePositionValue | 'now' | 'custom';
const scheduleModeChosen = ref(
    Boolean(props.initialPost) || Boolean(composition.scheduledAt.value),
);
const scheduleMode = ref<ComposerScheduleMode>(
    props.initialPost?.schedule_mode === ScheduleMode.Queue &&
        props.initialPost.status === PostStatus.Scheduled
        ? 'next'
        : composition.scheduledAt.value
          ? 'custom'
          : 'now',
);
const expandedAccountId = ref<string | null>(null);
const previewAccountId = ref<string | null>(null);
const cropping = ref(false);
const cropTarget = ref<{
    accountId: string | null;
    indexes: number[];
    initialIndex: number;
    tab: EditorTab;
} | null>(null);
const {
    uploading: cropUploading,
    failed: cropError,
    swap: swapEditedMedia,
} = useMediaEditSwap();
const availableSignatures = ref([...props.signatures]);
watch(
    () => props.signatures,
    (signatures) => {
        availableSignatures.value = [...signatures];
    },
);

const selectedAccounts = composition.selectedAccounts;
const isSingleChannel = computed(() => selectedAccounts.value.length === 1);
const acceptsTextOnly = (account: ComposerAccount): boolean =>
    !getMediaRulesForContentType(
        getContentTypeOptions(account.platform)[0]?.value ?? '',
    ).requiresMedia;
const skipsSharedStep = computed(
    () =>
        isSingleChannel.value ||
        (selectedAccounts.value.length > 1 &&
            !composition.media.value.length &&
            selectedAccounts.value.every(acceptsTextOnly)),
);
const step = computed<1 | 2>(() =>
    skipsSharedStep.value ? 2 : chosenStep.value,
);
watch(skipsSharedStep, (skips) => {
    if (!skips) chosenStep.value = 1;
});
watch(
    () => selectedAccounts.value.map((account) => account.id),
    (ids) => {
        if (!ids.includes(expandedAccountId.value ?? '')) {
            expandedAccountId.value = ids[0] ?? null;
        }
        if (!ids.includes(previewAccountId.value ?? '')) {
            previewAccountId.value = ids[0] ?? null;
        }
    },
    { immediate: true },
);
const page = usePage<SharedData>();
const mediaUploadLimits = (): MediaUploadLimits =>
    page.props.mediaUploadLimits ?? {
        max_bytes: { image: 0, video: 0, document: 0 },
        extensions: { image: [], video: [], document: [] },
        upload_retention_hours: 0,
        heic: false,
    };
const appendMedia = (
    accountId: string | null,
    item: MediaItem,
    replaces: string | null = null,
): void => {
    const account = accountId
        ? selectedAccounts.value.find((selected) => selected.id === accountId)
        : undefined;
    if (!accountId || !account) {
        composition.media.value = withMediaAdded(
            composition.media.value,
            item,
            replaces,
        );
        return;
    }
    composition.setOverride(
        accountId,
        'media',
        withMediaAdded(
            composition.resolvedDestination(account).media,
            item,
            replaces,
        ),
    );
};
const uploadScope = effectScope();
const uploaders = shallowReactive(new Map<string, MediaUploader>());
const createUploader = (accountId: string | null): void => {
    const key = accountId ?? '';
    if (uploaders.has(key)) return;
    uploaders.set(
        key,
        uploadScope.run(() =>
            useMediaUpload({
                limits: mediaUploadLimits,
                onReady: (item, _key, replaces) =>
                    appendMedia(accountId, item, replaces ?? null),
            }),
        )!,
    );
};
createUploader(null);
const suggestedMedia = ref<Record<string, MediaItem[]>>({});
watch(
    () => composition.selectedAccountIds.value,
    (ids, previousIds) => {
        ids.forEach(createUploader);
        (previousIds ?? [])
            .filter((id) => !ids.includes(id))
            .forEach((id) => {
                uploaders.get(id)?.clear();
                delete suggestedMedia.value[id];
            });
    },
    { immediate: true },
);
const uploaderFor = (accountId: string | null): MediaUploader =>
    (isSingleChannel.value ? undefined : uploaders.get(accountId ?? '')) ??
    uploaders.get('')!;
const suggestionsFor = (accountId: string | null): MediaItem[] =>
    suggestedMedia.value[accountId ?? 'shared'] ?? [];
const setSuggestions = (
    accountId: string | null,
    items: MediaItem[],
): void => {
    suggestedMedia.value[accountId ?? 'shared'] = items;
};
watch(
    () => props.open,
    (open) => {
        if (!open) suggestedMedia.value = {};
    },
);
const unsplashOpen = ref(false);
const unsplashAccountId = ref<string | null>(null);
const openUnsplash = (accountId: string | null): void => {
    unsplashAccountId.value = accountId;
    unsplashOpen.value = true;
};
const mediaImport = useMediaImport();
const onImportStarted = (
    started: MediaImportStarted,
    accountId: string | null,
): void => mediaImport.track(uploaderFor(accountId), started);
const activeUploaders = computed(() => [
    uploaderFor(null),
    ...composition.selectedAccountIds.value.flatMap((id) => {
        const uploader = uploaders.get(id);

        return uploader ? [uploader] : [];
    }),
]);
const mediaUploading = computed(() =>
    activeUploaders.value.some((uploader) => uploader.busy.value),
);
const mediaFailed = computed(() =>
    activeUploaders.value.some((uploader) => uploader.failed.value),
);
const mediaKey = (item: MediaItem): string => item.upload_token ?? item.id;
const ownsMediaOverride = (accountId: string): boolean =>
    Object.hasOwn(composition.overrides.value[accountId] ?? {}, 'media');
type SubmittedMedia = {
    shared: MediaItem[];
    destinations: { accountId: string; overridden: boolean; media: MediaItem[] }[];
};
const snapshotMedia = (): SubmittedMedia => ({
    shared: [...composition.media.value],
    destinations: selectedAccounts.value.map((account) => ({
        accountId: account.id,
        overridden: ownsMediaOverride(account.id),
        media: [...composition.resolvedDestination(account).media],
    })),
});
const submittedMedia = ref<SubmittedMedia | null>(null);
const mediaErrorKeys = computed(() => {
    const submitted = submittedMedia.value ?? snapshotMedia();
    const found: Record<string, Record<string, string>> = {};
    const record = (
        target: string,
        items: MediaItem[],
        index: number,
        message: string,
    ): void => {
        const item = items[index];
        if (!item) return;
        found[target] ??= {};
        found[target][mediaKey(item)] ??= message;
    };
    for (const [key, message] of Object.entries(errors.value)) {
        const shared = /^media\.(\d+)(\.|$)/.exec(key);
        if (shared) {
            record('', submitted.shared, Number(shared[1]), message);
            continue;
        }
        const match = /^destinations\.(\d+)\.media\.(\d+)(\.|$)/.exec(key);
        const destination = match
            ? submitted.destinations[Number(match[1])]
            : undefined;
        if (!match || !destination) continue;
        record(
            destination.overridden ? destination.accountId : '',
            destination.media,
            Number(match[2]),
            message,
        );
    }

    return found;
});
const mediaErrorsFor = (accountId: string | null): Record<number, string> => {
    const account = accountId
        ? selectedAccounts.value.find((selected) => selected.id === accountId)
        : undefined;
    const target = account && ownsMediaOverride(account.id) ? account.id : '';
    const keyed = mediaErrorKeys.value[target] ?? {};
    const items = account
        ? composition.resolvedDestination(account).media
        : composition.media.value;

    return Object.fromEntries(
        items.flatMap((item, index) => {
            const message = keyed[mediaKey(item)];

            return message ? [[index, message]] : [];
        }),
    );
};
const onMediaDropped = (event: DragEvent, accountId: string | null): void => {
    const files = Array.from(event.dataTransfer?.files ?? []);
    if (!files.length || cropUploading.value) return;
    uploaderFor(accountId).add(files);
};
const onMediaPasted = (
    event: ClipboardEvent,
    accountId: string | null,
): void => {
    const files = Array.from(event.clipboardData?.files ?? []);
    if (
        !files.length ||
        event.clipboardData?.getData('text/plain').trim() ||
        cropUploading.value
    ) {
        return;
    }
    event.preventDefault();
    uploaderFor(accountId).add(files);
};
const queueAvailable = computed(
    () =>
        selectedAccounts.value.length > 0 &&
        selectedAccounts.value.every((account) => account.has_posting_schedule),
);
const accountsWithoutSlots = computed(() =>
    selectedAccounts.value.filter((account) => !account.has_posting_schedule),
);
const queueBlocked = computed(() => accountsWithoutSlots.value.length > 0);
const accountsWithoutSlotsLabel = computed(() =>
    accountsWithoutSlots.value
        .map((account) => account.display_label || account.display_name)
        .join(', '),
);
const isQueueMode = computed(
    () => scheduleMode.value === 'next' || scheduleMode.value === 'top',
);
watch(
    queueAvailable,
    (available) => {
        if (
            available &&
            !scheduleModeChosen.value &&
            (defaultPostAction === 'next' || defaultPostAction === 'top')
        ) {
            scheduleMode.value = defaultPostAction;
        }
    },
    { immediate: true },
);
watch(
    queueBlocked,
    (blocked) => {
        if (blocked && isQueueMode.value) {
            scheduleMode.value = 'custom';
        }
    },
    { immediate: true },
);
const currentDefaultPostAction = computed(
    () =>
        (page.props.auth?.user as User | null)?.default_post_action ?? 'next',
);
const preferenceHttp = useHttp<{ default_post_action: string }>({
    default_post_action: '',
});
let confirmedDefaultPostAction: User['default_post_action'] | null = null;
let savingDefaultPostAction = false;
let queuedDefaultPostAction: User['default_post_action'] | null = null;
const saveDefaultPostAction = async (
    user: User,
    action: User['default_post_action'],
): Promise<void> => {
    savingDefaultPostAction = true;
    preferenceHttp.default_post_action = action;
    let saved = false;
    try {
        await preferenceHttp.patch(updatePreferences.url(), {
            onSuccess: () => {
                saved = true;
            },
        });
    } catch {
        saved = false;
    }
    savingDefaultPostAction = false;
    if (saved) confirmedDefaultPostAction = action;

    const next = queuedDefaultPostAction;
    queuedDefaultPostAction = null;
    if (next !== null && next !== confirmedDefaultPostAction) {
        await saveDefaultPostAction(user, next);
        return;
    }
    if (!saved && next === null && confirmedDefaultPostAction) {
        user.default_post_action = confirmedDefaultPostAction;
        toast.error(trans('posts.composer.queue.set_default_failed'));
    }
};
const setDefaultPostAction = (action: User['default_post_action']): void => {
    const user = page.props.auth?.user as User | null;
    if (!user || user.default_post_action === action) return;
    confirmedDefaultPostAction ??= user.default_post_action;
    user.default_post_action = action;
    if (savingDefaultPostAction) {
        queuedDefaultPostAction = action;
        return;
    }
    void saveDefaultPostAction(user, action);
};
const isInstagramPlatform = (platform: string): boolean =>
    platform === Platform.Instagram || platform === Platform.InstagramFacebook;
const selectedLabels = computed(() =>
    props.labels.filter((label) =>
        composition.labelIds.value.includes(label.id),
    ),
);
const filteredLabels = computed(() => {
    const query = labelSearch.value.trim().toLocaleLowerCase();

    return query
        ? props.labels.filter((label) =>
              label.name.toLocaleLowerCase().includes(query),
          )
        : props.labels;
});
watch(labelsOpen, (isOpen) => {
    if (!isOpen) {
        labelSearch.value = '';
    }
});
const filteredAccounts = computed(() => {
    const query = accountSearch.value.trim().toLocaleLowerCase();

    return query
        ? props.socialAccounts.filter((account) =>
              [
                  account.display_name,
                  account.username,
                  getPlatformLabel(account.platform),
              ].some((value) => value.toLocaleLowerCase().includes(query)),
          )
        : props.socialAccounts;
});
const recentlyUsedAccounts = computed(() =>
    props.socialAccounts.filter((account) =>
        lastUsedAccountIds.value.includes(account.id),
    ),
);
const hasSharedPreview = computed(
    () =>
        Boolean(composition.content.value.trim()) ||
        composition.media.value.length > 0,
);

onMounted(() => {
    try {
        const saved = JSON.parse(
            localStorage.getItem('trypost:composer:last-used-accounts') ?? '[]',
        );
        if (Array.isArray(saved)) {
            lastUsedAccountIds.value = saved.filter(
                (id): id is string => typeof id === 'string',
            );
        }
    } catch {
        lastUsedAccountIds.value = [];
    }
});
const previewAccount = computed(
    () =>
        selectedAccounts.value.find(
            (account) => account.id === previewAccountId.value,
        ) ?? selectedAccounts.value[0],
);
const previewDestination = computed(() =>
    previewAccount.value
        ? composition.resolvedDestination(previewAccount.value)
        : null,
);
const expandedAccount = computed(() =>
    selectedAccounts.value.find(
        (account) => account.id === expandedAccountId.value,
    ),
);
const expandedDestination = computed(() =>
    expandedAccount.value
        ? composition.resolvedDestination(expandedAccount.value)
        : null,
);
const expandedOverride = computed(() =>
    expandedAccountId.value
        ? (composition.overrides.value[expandedAccountId.value] ?? {})
        : {},
);
const assistantContent = computed(() =>
    step.value === 2 && expandedDestination.value
        ? expandedDestination.value.content
        : composition.content.value,
);
const assistantChannel = computed<AssistantChannel | null>(() => {
    const account =
        step.value === 2
            ? expandedAccount.value
            : selectedAccounts.value.length === 1
              ? selectedAccounts.value[0]
              : undefined;
    if (!account) return null;
    const limit = props.platformConfigs[account.id]?.maxContentLength;

    return {
        platform: account.platform,
        label: getPlatformLabel(account.platform),
        limit: typeof limit === 'number' ? limit : null,
    };
});
const canSubmit = computed(
    () =>
        selectedAccounts.value.length > 0 &&
        !props.submitting &&
        !cropUploading.value &&
        !mediaUploading.value &&
        !mediaFailed.value,
);
const remainingCharacters = (account: ComposerAccount): number | null => {
    const limit = props.platformConfigs[account.id]?.maxContentLength;
    if (typeof limit !== 'number' || limit <= 0) return null;

    return (
        limit -
        contentFor(
            composition.resolvedDestination(account).content,
            account.platform,
        ).length
    );
};
type DestinationIssue = {
    key: string;
    params: Record<string, string>;
    warning?: MediaValidationWarning;
    contentType: string;
};
const destinationIssues = (account: ComposerAccount): DestinationIssue[] => {
    const destination = composition.resolvedDestination(account);
    const contentType = destination.content_type;
    const aspectIssues = isInstagramPlatform(account.platform)
        ? getInstagramImageAspectIssues(contentType, destination.media)
        : [];
    const mediaWarning = getMediaValidationWarning(
        contentType,
        destination.media,
    );
    const remaining = remainingCharacters(account);
    const meta = evaluatePlatformMeta(account.platform, destination.meta ?? {});
    const issues: DestinationIssue[] = [];

    if (
        mediaWarning &&
        !(aspectIssues.length && mediaWarning.key.startsWith('aspect_ratio_'))
    ) {
        issues.push({
            key: `posts.form.warnings.${mediaWarning.key}`,
            params: {},
            warning: mediaWarning,
            contentType,
        });
    }
    for (const aspectIssue of aspectIssues) {
        issues.push({
            key: 'posts.composer.instagram_image_aspect_issue',
            params: {
                image: String(aspectIssue.index + 1),
                current: aspectIssue.ratio.toFixed(2),
                min: aspectIssue.min.toFixed(2),
                max: aspectIssue.max.toFixed(2),
            },
            contentType,
        });
    }
    if (remaining !== null && remaining < 0) {
        issues.push({
            key: 'posts.form.content_exceeds_platform',
            params: {
                platform: getPlatformLabel(account.platform),
                over: String(-remaining),
                limit: String(
                    props.platformConfigs[account.id]?.maxContentLength ?? '',
                ),
            },
            contentType,
        });
    }
    if (!meta.valid) {
        issues.push({
            key: meta.tooltipKey ?? 'posts.edit.compliance_incomplete',
            params: {},
            contentType,
        });
    }

    return issues;
};
const destinationIssueLabel = (account: ComposerAccount): string => {
    const count = destinationIssues(account).length;

    return transChoice('posts.composer.destination_issues', count, {
        count: String(count),
    });
};
const firstMedia = (account: ComposerAccount): MediaItem | undefined =>
    composition.resolvedDestination(account).media[0];
const blockingIssue = computed(() => {
    for (const account of selectedAccounts.value) {
        const [issue] = destinationIssues(account);
        if (issue) return { ...issue, account };
    }

    return null;
});
const requiresMediaWarning = (account: ComposerAccount | undefined): boolean => {
    if (!account) return false;
    const destination = composition.resolvedDestination(account);

    return (
        getMediaValidationWarning(destination.content_type, destination.media)
            ?.key === 'requires_media'
    );
};
const hasBlockingIssues = computed(() => blockingIssue.value !== null);
const isBatch = computed(
    () => !props.postId && selectedAccounts.value.length > 1,
);
const scheduleLabelKey = computed(() =>
    scheduleMode.value === 'next' || scheduleMode.value === 'top'
        ? `posts.composer.queue.${scheduleMode.value}`
        : 'posts.composer.now',
);
const scheduleDateLabel = computed(() =>
    scheduleMode.value === 'custom' && composition.scheduledAt.value
        ? date.formatLocalMonthDayTime(composition.scheduledAt.value)
        : null,
);
const scheduleTriggerIcon = computed(() => {
    if (scheduleMode.value === 'custom' && composition.scheduledAt.value) {
        return { name: 'pin', component: IconPin };
    }

    return scheduleMode.value === 'next' || scheduleMode.value === 'top'
        ? { name: 'calendar-clock', component: IconCalendarClock }
        : { name: 'send', component: IconSend };
});
watch(scheduleMenuOpen, (open) => {
    if (open) {
        schedulePanel.value =
            scheduleMode.value === 'custom' && composition.scheduledAt.value
                ? 'picker'
                : 'menu';
    }
});
const scheduleOptions = computed(() => [
    ...(['next', 'top'] as const).map((mode) => ({
        mode,
        titleKey: `posts.composer.queue.${mode}`,
        descriptionKey: `posts.composer.queue.${mode}_description`,
    })),
    {
        mode: 'now' as const,
        titleKey: 'posts.composer.now',
        descriptionKey: 'posts.composer.now_description',
    },
    {
        mode: 'custom' as const,
        titleKey: 'posts.composer.set_date_time',
        descriptionKey: 'posts.composer.set_date_time_description',
    },
]);
const videoDurationSec = computed(
    () =>
        Math.ceil(
            expandedDestination.value?.media.find(
                (item) => item.type === 'video',
            )?.meta?.duration ?? 0,
        ) || null,
);

const selectAccount = (account: ComposerAccount): void => {
    composition.toggleAccount(account.id);
    if (composition.selectedAccountIds.value.includes(account.id)) {
        previewAccountId.value = account.id;
        if (step.value === 2) expandedAccountId.value = account.id;
    }
};

const selectAccounts = (accounts: ComposerAccount[]): void => {
    if (props.initialPost) return;

    const allSelected = accounts.every((account) =>
        composition.selectedAccountIds.value.includes(account.id),
    );
    for (const account of accounts) {
        if (
            composition.selectedAccountIds.value.includes(account.id) ===
            allSelected
        ) {
            selectAccount(account);
        }
    }
};

const toggleLabel = (id: string): void => {
    composition.labelIds.value = composition.labelIds.value.includes(id)
        ? composition.labelIds.value.filter((selected) => selected !== id)
        : [...composition.labelIds.value, id];
};

const saveSignature = (signature: {
    id: string;
    name: string;
    content: string;
}): void => {
    availableSignatures.value = [
        signature,
        ...availableSignatures.value.filter(
            (existing) => existing.id !== signature.id,
        ),
    ];
};

const appendSignature = (
    signature: { content: string },
    accountId: string | null,
): void => {
    const account = selectedAccounts.value.find(
        (selected) => selected.id === accountId,
    );
    const current = account
        ? composition.resolvedDestination(account).content
        : composition.content.value;
    const next = `${current}${current.trim() ? '\n\n' : ''}${signature.content}`;
    if (account) {
        composition.setOverride(account.id, 'content', next);
    } else {
        composition.content.value = next;
    }
};

const appendEmoji = (emoji: string, accountId: string | null): void => {
    const account = selectedAccounts.value.find(
        (selected) => selected.id === accountId,
    );
    if (account) {
        composition.setOverride(
            account.id,
            'content',
            `${composition.resolvedDestination(account).content}${emoji}`,
        );
    } else {
        composition.content.value += emoji;
    }
};

const writeAssistantTarget = (text: string): void => {
    if (step.value === 2 && expandedAccount.value) {
        composition.setOverride(expandedAccount.value.id, 'content', text);
    } else {
        composition.content.value = text;
    }
};

const insertAssistantText = (text: string): void => {
    const target = assistantContent.value;
    writeAssistantTarget(target.trim() ? `${target}\n\n${text}` : text);
    mobilePanelOpen.value = false;
};

const showSidePanel = (panel: ComposerSidePanel): void => {
    sidePanel.value = panel;
    mobilePanelOpen.value = true;
};

const editorAccount = (accountId: string | null): ComposerAccount | null =>
    accountId === null
        ? null
        : (selectedAccounts.value.find(
              (candidate) => candidate.id === accountId,
          ) ?? null);
const editorMedia = (accountId: string | null): MediaItem[] => {
    const account = editorAccount(accountId);

    return account
        ? composition.resolvedDestination(account).media
        : composition.media.value;
};
const editorContentTypes = (accountId: string | null): string[] => {
    const account = editorAccount(accountId);

    return (
        account ? [account] : accountId === null ? selectedAccounts.value : []
    )
        .map((candidate) => composition.resolvedDestination(candidate).content_type)
        .filter((contentType) => Boolean(contentType));
};

const openEditor = (
    target: { accountId: string | null },
    index: number,
    tab: EditorTab,
): void => {
    if (cropUploading.value) return;
    if (target.accountId !== null && !editorAccount(target.accountId)) return;
    const rules = rulesFor(editorContentTypes(target.accountId));
    const indexes = editorMedia(target.accountId).flatMap((candidate, position) =>
        editorTabsFor(candidate, rules).length > 0 ? [position] : [],
    );
    if (!indexes.includes(index)) return;
    cropError.value = false;
    cropTarget.value = {
        accountId: target.accountId,
        indexes,
        initialIndex: indexes.indexOf(index),
        tab,
    };
    cropping.value = true;
};

const cropContentTypes = computed(() =>
    cropTarget.value ? editorContentTypes(cropTarget.value.accountId) : [],
);
const cropItems = computed(() => {
    if (!cropTarget.value) return [];
    const media = editorMedia(cropTarget.value.accountId);

    return cropTarget.value.indexes.flatMap((index) =>
        media[index] ? [media[index]] : [],
    );
});
const cropAspectBounds = computed(() => {
    if (!cropTarget.value?.accountId) return {};
    const rules = getMediaRulesForContentType(cropContentTypes.value[0] ?? '');

    return { min: rules.aspectRatioMin, max: rules.aspectRatioMax };
});

const onMediaEdited = async (changes: MediaEditChange[]): Promise<void> => {
    const target = cropTarget.value;
    if (!target) return;
    await swapEditedMedia({
        changes,
        indexes: target.indexes,
        items: () => editorMedia(target.accountId),
        write: (items) => {
            if (target.accountId === null) {
                composition.media.value = items;
            } else {
                composition.setOverride(target.accountId, 'media', items);
            }
        },
    });
    cropTarget.value = null;
};

const goToCustomization = (): void => {
    if (selectedAccounts.value.length) chosenStep.value = 2;
};

const submit = (status: PostComposition['status']): void => {
    if (
        !canSubmit.value ||
        (status !== 'draft' && hasBlockingIssues.value)
    )
        return;
    submittedMedia.value = snapshotMedia();
    const queue =
        status === 'scheduled' &&
        (scheduleMode.value === 'next' || scheduleMode.value === 'top')
            ? scheduleMode.value
            : null;
    const payload = composition.materialize(status, queue);
    if (status === 'scheduled' && !queue) {
        payload.scheduled_at = date.formatLocalDateTimeForApi(
            composition.scheduledAt.value,
        );
        if (!payload.scheduled_at) return;
    }
    try {
        localStorage.setItem(
            'trypost:composer:last-used-accounts',
            JSON.stringify(composition.selectedAccountIds.value),
        );
    } catch {
        // Browser storage is optional; publishing must still work without it.
    }
    autosave.flush();
    emit('submit', payload, createAnother.value && !props.postId);
};

const submitSelectedSchedule = (): void => {
    submit(scheduleMode.value === 'now' ? 'publishing' : 'scheduled');
};

const isScheduleModeDisabled = (mode: ComposerScheduleMode): boolean =>
    (mode === 'next' || mode === 'top') && queueBlocked.value;
const selectScheduleMode = (mode: ComposerScheduleMode): void => {
    if (isScheduleModeDisabled(mode)) return;
    if (mode === 'custom') {
        schedulePanel.value = 'picker';
        return;
    }
    scheduleMenuOpen.value = false;
    scheduleModeChosen.value = true;
    scheduleMode.value = mode;
};

const confirmScheduledAt = (value: string): void => {
    composition.scheduledAt.value = value;
    scheduleModeChosen.value = true;
    scheduleMode.value = 'custom';
    scheduleMenuOpen.value = false;
};

const autosaveEnabled =
    !props.initialPost && !props.postId && !props.initialDraft;
const autosave = useComposerAutosave(
    computed(() =>
        composerAutosaveKey(
            page.props.auth?.user?.id,
            page.props.auth?.currentWorkspace?.id,
        ),
    ),
);
const resumeAccounts = computed(() =>
    (autosave.saved.value?.accountIds ?? []).flatMap((id) => {
        const account = props.socialAccounts.find(
            (candidate) => candidate.id === id,
        );

        return account ? [account] : [];
    }),
);
const resumeMediaCount = computed(
    () =>
        (autosave.saved.value?.media ?? []).filter(
            (item) =>
                !isExpiredUpload(
                    item,
                    mediaUploadLimits().upload_retention_hours,
                ),
        ).length,
);
const resumePreview = computed(
    () =>
        (autosave.saved.value?.content ?? '')
            .split('\n')
            .map((line) => line.trim())
            .find(Boolean) ?? '',
);
const resumeOpen = ref(
    autosaveEnabled &&
        Boolean(
            resumePreview.value ||
                resumeAccounts.value.length ||
                resumeMediaCount.value,
        ),
);
const autosaveSnapshot = (): AutosaveSnapshot => ({
    version: 1,
    savedAt: dayjs().toISOString(),
    content: composition.content.value,
    overrides: Object.fromEntries(
        Object.entries(composition.overrides.value).map(
            ([id, { media, ...override }]) => [
                id,
                media
                    ? { ...override, media: media.map(toAutosaveMedia) }
                    : override,
            ],
        ),
    ),
    accountIds: [...composition.selectedAccountIds.value],
    media: composition.media.value.map(toAutosaveMedia),
    labelIds: [...composition.labelIds.value],
    scheduleMode: scheduleModeChosen.value ? scheduleMode.value : null,
    scheduledAt:
        scheduleModeChosen.value && scheduleMode.value === 'custom'
            ? composition.scheduledAt.value || null
            : null,
});
const compositionIsEmpty = (): boolean =>
    !composition.content.value.trim() &&
    composition.media.value.length === 0 &&
    composition.selectedAccountIds.value.length === 0 &&
    composition.labelIds.value.length === 0;
if (autosaveEnabled) {
    watch(
        [
            composition.content,
            composition.media,
            composition.overrides,
            composition.selectedAccountIds,
            composition.labelIds,
            scheduleMode,
            scheduleModeChosen,
            composition.scheduledAt,
        ],
        () => {
            if (resumeOpen.value || props.submitting) return;
            if (compositionIsEmpty()) {
                autosave.clear();
                return;
            }
            autosave.save(autosaveSnapshot());
        },
        { deep: true },
    );
}
const resumeUnfinishedPost = (): void => {
    const snapshot = autosave.saved.value;
    resumeOpen.value = false;
    if (!snapshot) return;
    const retentionHours = mediaUploadLimits().upload_retention_hours;
    const restoreMedia = (items: AutosaveMediaRef[]): MediaItem[] =>
        items
            .filter((item) => !isExpiredUpload(item, retentionHours))
            .map(fromAutosaveMedia);
    [...composition.selectedAccountIds.value].forEach((id) =>
        composition.toggleAccount(id),
    );
    snapshot.accountIds.forEach((id) => composition.toggleAccount(id));
    const selectedIds = composition.selectedAccountIds.value;
    composition.content.value = snapshot.content;
    composition.media.value = restoreMedia(snapshot.media);
    composition.labelIds.value = snapshot.labelIds.filter((id) =>
        props.labels.some((label) => label.id === id),
    );
    composition.overrides.value = Object.fromEntries(
        Object.entries(snapshot.overrides)
            .filter(([id]) => selectedIds.includes(id))
            .map(([id, { media, ...override }]) => [
                id,
                Array.isArray(media)
                    ? { ...override, media: restoreMedia(media) }
                    : override,
            ]),
    );
    composition.adoptSingleDestination();
    previewAccountId.value = selectedIds[0] ?? null;
    const mode = snapshot.scheduleMode;
    if (
        mode === 'now' ||
        ((mode === 'next' || mode === 'top') && !queueBlocked.value)
    ) {
        scheduleModeChosen.value = true;
        scheduleMode.value = mode;
    } else if (
        mode === 'custom' &&
        snapshot.scheduledAt &&
        dayjs(snapshot.scheduledAt).isAfter(dayjs())
    ) {
        composition.scheduledAt.value = snapshot.scheduledAt;
        scheduleModeChosen.value = true;
        scheduleMode.value = 'custom';
    }
};
const discardUnfinishedPost = (): void => {
    autosave.clear();
    resumeOpen.value = false;
};

const close = (): void => emit('update:open', false);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="motion-resize top-0 left-0 flex h-dvh max-h-dvh w-screen max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-none p-0 sm:top-1/2 sm:left-1/2 sm:max-w-none sm:-translate-x-1/2 sm:-translate-y-1/2"
            :class="
                expandedDialog
                    ? 'sm:h-dvh sm:max-h-dvh sm:w-screen sm:rounded-none'
                    : 'sm:h-[calc(100dvh-3rem)] sm:max-h-[888px] sm:w-[min(1100px,calc(100vw-3rem))] sm:rounded-2xl'
            "
            :show-close-button="false"
            :aria-describedby="undefined"
            data-testid="post-composer-dialog"
            :disable-outside-pointer-events="!isGooglePickerOpen"
            @interact-outside="isGooglePickerOpen && $event.preventDefault()"
        >
            <header
                data-testid="composer-header"
                class="flex shrink-0 flex-row flex-wrap items-center justify-between gap-2 border-b px-4 py-3 sm:min-h-16 sm:flex-nowrap sm:py-4 sm:ps-8 sm:pe-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <Button
                        v-if="step === 2 && !skipsSharedStep"
                        type="button"
                        variant="ghost"
                        size="icon"
                        data-testid="composer-back"
                        :aria-label="$t('common.back')"
                        @click="chosenStep = 1"
                        ><IconArrowLeft class="size-4"
                    /></Button>
                    <DialogTitle class="font-sans">{{
                        initialPost || initialDraft
                            ? $t('posts.edit.title')
                            : $t('posts.create.title')
                    }}</DialogTitle>
                    <Popover v-model:open="labelsOpen">
                        <PopoverTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="default"
                                class="max-w-72"
                                data-testid="composer-tags-trigger"
                            >
                                <IconTag
                                    v-if="!selectedLabels.length"
                                    class="size-4"
                                />
                                <span v-if="!selectedLabels.length">{{
                                    $t('posts.edit.labels')
                                }}</span>
                                <span
                                    v-else
                                    class="flex min-w-0 items-center gap-1.5 overflow-hidden"
                                >
                                    <span
                                        v-for="label in selectedLabels.slice(
                                            0,
                                            2,
                                        )"
                                        :key="label.id"
                                        class="flex min-w-0 items-center gap-1"
                                    >
                                        <span
                                            class="size-2 shrink-0 rounded-full"
                                            :style="{
                                                backgroundColor: label.color,
                                            }"
                                        />
                                        <span class="truncate">{{
                                            label.name
                                        }}</span>
                                    </span>
                                    <span
                                        v-if="selectedLabels.length > 2"
                                        class="shrink-0 text-muted-foreground"
                                        >+{{ selectedLabels.length - 2 }}</span
                                    >
                                </span>
                                <IconChevronDown
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-72 p-3" align="start">
                            <div class="relative mb-2">
                                <IconSearch
                                    class="pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <input
                                    v-model="labelSearch"
                                    type="search"
                                    role="combobox"
                                    aria-controls="composer-label-options"
                                    :aria-expanded="labelsOpen"
                                    :aria-label="
                                        $t('posts.label_search_placeholder')
                                    "
                                    :placeholder="
                                        $t('posts.label_search_placeholder')
                                    "
                                    data-testid="composer-label-search"
                                    class="h-8 w-full rounded-lg border border-input bg-background pr-2 pl-8 text-sm transition-control outline-none placeholder:text-subtle-foreground focus-visible:border-primary-text"
                                />
                            </div>
                            <p
                                v-if="!filteredLabels.length"
                                class="px-2 py-3 text-sm text-muted-foreground"
                            >
                                {{ $t('posts.no_labels') }}
                            </p>
                            <div
                                id="composer-label-options"
                                role="listbox"
                                aria-multiselectable="true"
                                class="max-h-60 space-y-0.5 overflow-y-auto"
                            >
                                <button
                                    v-for="label in filteredLabels"
                                    :key="label.id"
                                    type="button"
                                    role="option"
                                    :data-testid="`composer-label-${label.id}`"
                                    :aria-selected="
                                        composition.labelIds.value.includes(
                                            label.id,
                                        )
                                    "
                                    class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-control hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                    @click.stop="toggleLabel(label.id)"
                                >
                                    <span
                                        class="flex size-4 shrink-0 items-center justify-center rounded-sm border"
                                        :class="
                                            composition.labelIds.value.includes(
                                                label.id,
                                            )
                                                ? 'border-primary-strong bg-primary-strong text-primary-strong-foreground'
                                                : 'border-input'
                                        "
                                    >
                                        <IconCheck
                                            v-if="
                                                composition.labelIds.value.includes(
                                                    label.id,
                                                )
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor: label.color,
                                        }"
                                    />
                                    <span class="truncate">{{
                                        label.name
                                    }}</span>
                                </button>
                            </div>
                        </PopoverContent>
                    </Popover>
                </div>
                <div
                    class="flex w-full min-w-0 items-center justify-end gap-2 sm:w-auto"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        :aria-pressed="sidePanel === 'templates'"
                        data-testid="composer-templates-toggle"
                        class="max-sm:w-8 max-sm:px-0"
                        :class="
                            sidePanel === 'templates'
                                ? 'bg-primary-subtle text-primary-text hover:bg-primary-subtle hover:text-primary-text'
                                : 'text-muted-foreground'
                        "
                        @click="showSidePanel('templates')"
                        ><IconTemplate class="size-4" /><span class="max-sm:sr-only">{{
                            $t('create.templates.panel.title')
                        }}</span></Button
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        :aria-pressed="sidePanel === 'assistant'"
                        data-testid="composer-ai-assistant"
                        class="max-sm:w-8 max-sm:px-0"
                        :class="
                            sidePanel === 'assistant'
                                ? 'bg-primary-subtle text-primary-text hover:bg-primary-subtle hover:text-primary-text'
                                : 'text-muted-foreground'
                        "
                        @click="showSidePanel('assistant')"
                        ><IconWand class="size-4" /><span class="max-sm:sr-only">{{
                            $t('posts.composer.assistant_title')
                        }}</span></Button
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        :aria-pressed="sidePanel === 'preview'"
                        data-testid="composer-preview-toggle"
                        class="max-sm:w-8 max-sm:px-0"
                        :class="
                            sidePanel === 'preview'
                                ? 'bg-primary-subtle text-primary-text hover:bg-primary-subtle hover:text-primary-text'
                                : 'text-muted-foreground'
                        "
                        @click="showSidePanel('preview')"
                        ><IconEye class="size-4" /><span class="max-sm:sr-only">{{
                            $t('posts.edit.tabs.preview')
                        }}</span></Button
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="
                            expandedDialog
                                ? $t('posts.composer.collapse')
                                : $t('posts.composer.expand')
                        "
                        data-testid="composer-expand-dialog"
                        @click="expandedDialog = !expandedDialog"
                    >
                        <IconArrowsMinimize
                            v-if="expandedDialog"
                            class="size-4"
                        />
                        <IconArrowsMaximize v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('posts.edit.cancel')"
                        data-testid="composer-close"
                        @click="close"
                        ><IconX class="size-4"
                    /></Button>
                </div>
            </header>

            <div
                class="grid min-h-0 flex-1 md:grid-cols-[minmax(0,1fr)_380px]"
            >
                <div
                    class="min-h-0 overflow-y-auto px-4 pt-4 pb-5 sm:px-8"
                    :class="[
                        step === 1 ? 'flex flex-col gap-6' : 'space-y-6',
                        mobilePanelOpen ? 'max-md:hidden' : '',
                    ]"
                >
                    <p
                        v-if="Object.keys(errors).length"
                        data-testid="composer-errors"
                        class="rounded-lg border border-destructive bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {{ Object.values(errors)[0] }}
                    </p>
                    <div
                        class="relative mx-auto flex h-12 w-full max-w-[744px] shrink-0 items-center gap-2"
                    >
                        <div
                            class="-my-3 flex min-w-0 gap-4 overflow-x-auto py-3 pr-3 empty:hidden"
                            data-testid="composer-accounts"
                        >
                            <ComposerAccountChip
                                v-for="account in selectedAccounts"
                                :key="account.id"
                                :account="account"
                                :active="
                                    (step === 1
                                        ? previewAccountId
                                        : expandedAccountId) === account.id
                                "
                                :removable="!initialPost"
                                @focus="
                                    expandedAccountId = account.id;
                                    previewAccountId = account.id;
                                "
                                @remove="selectAccount(account)"
                            />
                        </div>
                        <Popover
                            v-if="!initialPost"
                            v-model:open="accountPickerOpen"
                        >
                            <PopoverAnchor as-child>
                                <span
                                    class="pointer-events-none absolute inset-x-0 top-0 h-full"
                                    aria-hidden="true"
                                />
                            </PopoverAnchor>
                            <PopoverTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :size="
                                        selectedAccounts.length
                                            ? 'icon-lg'
                                            : 'lg'
                                    "
                                    class="shrink-0"
                                    :aria-label="
                                        $t(
                                            'posts.edit.platforms_dialog.title',
                                        )
                                    "
                                    data-testid="composer-add-account"
                                >
                                    <IconPlus class="size-4" />
                                    <span v-if="!selectedAccounts.length">{{
                                        $t('posts.edit.tabs.channels')
                                    }}</span>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                class="w-[min(380px,calc(100vw-2rem))] p-3"
                                :side-offset="8"
                                align="start"
                            >
                                <ComposerAccountOptions
                                    v-model:search="accountSearch"
                                    :accounts="filteredAccounts"
                                    :selected-ids="
                                        composition.selectedAccountIds.value
                                    "
                                    @toggle="selectAccount"
                                    @toggle-all="selectAccounts"
                                />
                            </PopoverContent>
                        </Popover>
                        <template
                            v-if="
                                !selectedAccounts.length &&
                                socialAccounts.length > 1
                            "
                        >
                            <Button
                                v-if="recentlyUsedAccounts.length"
                                type="button"
                                variant="outline"
                                size="lg"
                                class="shrink-0 border-dashed ps-4 pe-2"
                                data-testid="composer-last-used"
                                @click="
                                    selectAccounts(recentlyUsedAccounts)
                                "
                                >{{ $t('posts.composer.last_used') }}
                                <ComposerAccountStack
                                    :accounts="recentlyUsedAccounts"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                class="shrink-0 border-dashed ps-4 pe-2"
                                data-testid="composer-all-accounts"
                                @click="selectAccounts(socialAccounts)"
                                >{{ $t('sidebar.posts.all') }}
                                <ComposerAccountStack
                                    :accounts="socialAccounts"
                                />
                            </Button>
                        </template>
                    </div>
                    <template v-if="step === 1">
                        <div
                            class="mx-auto flex min-h-64 w-full max-w-[744px] flex-1 flex-col rounded-xl border border-border bg-card p-4"
                            @dragover.prevent
                            @drop.prevent="onMediaDropped($event, null)"
                        >
                            <div class="relative flex min-h-40 flex-1 flex-col">
                                <textarea
                                    v-model="composition.content.value"
                                    data-testid="composer-base-content"
                                    @paste="onMediaPasted($event, null)"
                                    :aria-label="$t('posts.composer.content_label')"
                                    :aria-describedby="
                                        composition.content.value
                                            ? undefined
                                            : 'composer-templates-inspire-hint'
                                    "
                                    class="-mt-[3px] min-h-40 w-full flex-1 resize-none bg-transparent px-[9px] pt-0.5 pb-1 text-sm outline-none"
                                />
                                <p
                                    v-if="!composition.content.value"
                                    id="composer-templates-inspire-hint"
                                    class="pointer-events-none absolute start-0 top-0 -mt-[3px] px-[9px] pt-0.5 text-sm text-subtle-foreground"
                                >
                                    {{
                                        $t('create.templates.panel.inspire_prefix')
                                    }}
                                    <button
                                        type="button"
                                        class="pointer-events-auto cursor-pointer font-medium text-primary-text underline-offset-2 hover:underline"
                                        data-testid="composer-templates-inspire"
                                        @click="showSidePanel('templates')"
                                    >
                                        {{
                                            $t(
                                                'create.templates.panel.inspire_link',
                                            )
                                        }}
                                    </button>
                                </p>
                            </div>
                            <div>
                                <MediaTray
                                    class="pt-5"
                                    test-id-prefix="composer"
                                    :items="composition.media.value"
                                    :limits="mediaUploadLimits()"
                                    :uploader="uploaderFor(null)"
                                    :suggested="suggestionsFor(null)"
                                    @update:suggested="setSuggestions(null, $event)"
                                    :item-errors="mediaErrorsFor(null)"
                                    :content-types="editorContentTypes(null)"
                                    :disabled="cropUploading"
                                    @update:items="composition.media.value = $event"
                                    @import-started="onImportStarted($event, null)"
                                    @edit="
                                        openEditor(
                                            { accountId: null },
                                            $event.index,
                                            $event.tab,
                                        )
                                    "
                                />
                                <p
                                    v-if="cropError"
                                    class="mt-2 text-sm text-destructive"
                                    data-testid="composer-crop-error"
                                >
                                    {{
                                        $t('posts.composer.crop_upload_failed')
                                    }}
                                </p>
                            </div>
                            <ComposerEditorToolbar
                                test-id-prefix="composer-base"
                                :signatures="availableSignatures"
                                @import-started="onImportStarted($event, null)"
                                @open-unsplash="openUnsplash(null)"
                                @select-emoji="appendEmoji($event, null)"
                                @select-signature="
                                    appendSignature($event, null)
                                "
                                @save-signature="saveSignature"
                            />
                        </div>
                    </template>

                    <template v-else>
                        <div
                            v-if="expandedAccount && expandedDestination"
                            :key="expandedAccount.id"
                            class="mx-auto flex min-h-[540px] w-full max-w-[744px] gap-3 rounded-xl border bg-card p-3"
                            data-testid="composer-customization"
                            @dragover.prevent
                            @drop.prevent="
                                onMediaDropped($event, expandedAccount.id)
                            "
                        >
                            <PlatformLogo
                                :platform="expandedAccount.platform"
                                :size="24"
                            />
                            <div class="flex min-w-0 flex-1 flex-col gap-4">
                            <span class="sr-only">{{
                                expandedAccount.display_name ||
                                expandedAccount.username
                            }}</span>
                            <ContentTypeRadioGroup
                                v-if="
                                    getContentTypeOptions(
                                        expandedAccount.platform,
                                    ).length > 1
                                "
                                :options="
                                    getContentTypeOptions(
                                        expandedAccount.platform,
                                    )
                                "
                                :model-value="expandedDestination.content_type"
                                :test-id-prefix="`composer-type-${expandedAccount.id}`"
                                :disabled="cropUploading"
                                @update:model-value="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'content_type',
                                        $event,
                                    )
                                "
                            />
                            <div
                                v-if="requiresMediaWarning(expandedAccount)"
                                role="status"
                                class="flex items-center gap-2 rounded-md bg-warning/15 px-3 py-1.5 text-sm"
                                :data-testid="`composer-media-warning-${expandedAccount.id}`"
                            >
                                <IconAlertTriangle class="size-4 text-warning" />
                                {{ $t('posts.form.warnings.requires_media') }}
                            </div>
                            <ChannelMediaWarnings
                                :platform="expandedAccount.platform"
                                :content-type="expandedDestination.content_type"
                                :media="expandedDestination.media"
                                :media-editing="true"
                                :disabled="cropUploading"
                                @edit:media="
                                    openEditor(
                                        { accountId: expandedAccount.id },
                                        $event,
                                        'edit',
                                    )
                                "
                            />
                            <div class="flex min-h-40 flex-1 flex-col">
                                <div
                                    class="mb-1 flex items-center justify-between"
                                >
                                    <label
                                        class="sr-only"
                                        :for="`composer-caption-${expandedAccount.id}`"
                                        >{{ $t('posts.edit.caption') }}</label
                                    ><Button
                                        v-if="
                                            Object.hasOwn(
                                                expandedOverride,
                                                'content',
                                            )
                                        "
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="
                                            composition.clearOverride(
                                                expandedAccount.id,
                                                'content',
                                            )
                                        "
                                        >{{
                                            $t('posts.composer.use_shared')
                                        }}</Button
                                    >
                                </div>
                                <textarea
                                    :id="`composer-caption-${expandedAccount.id}`"
                                    :value="expandedDestination.content"
                                    :data-testid="`composer-caption-${expandedAccount.id}`"
                                    @paste="
                                        onMediaPasted(
                                            $event,
                                            expandedAccount.id,
                                        )
                                    "
                                    class="min-h-40 w-full flex-1 resize-none bg-transparent px-[9px] pt-0.5 pb-1 text-sm outline-none placeholder:text-subtle-foreground/33"
                                    @input="
                                        composition.setOverride(
                                            expandedAccount.id,
                                            'content',
                                            (
                                                $event.target as HTMLTextAreaElement
                                            ).value,
                                        )
                                    "
                                />
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="sr-only">{{
                                        $t('posts.create.steps.media_title')
                                    }}</span
                                    ><Button
                                        v-if="
                                            Object.hasOwn(
                                                expandedOverride,
                                                'media',
                                            )
                                        "
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="
                                            composition.clearOverride(
                                                expandedAccount.id,
                                                'media',
                                            )
                                        "
                                        >{{
                                            $t('posts.composer.use_shared')
                                        }}</Button
                                    >
                                </div>
                                <MediaTray
                                    :test-id-prefix="`composer-${expandedAccount.id}`"
                                    :items="expandedDestination.media"
                                    :limits="mediaUploadLimits()"
                                    :uploader="uploaderFor(expandedAccount.id)"
                                    :suggested="suggestionsFor(expandedAccount.id)"
                                    @update:suggested="
                                        setSuggestions(expandedAccount.id, $event)
                                    "
                                    :item-errors="
                                        mediaErrorsFor(expandedAccount.id)
                                    "
                                    :content-types="
                                        editorContentTypes(expandedAccount.id)
                                    "
                                    :disabled="cropUploading"
                                    @update:items="
                                        composition.setOverride(
                                            expandedAccount.id,
                                            'media',
                                            $event,
                                        )
                                    "
                                    @edit="
                                        openEditor(
                                            { accountId: expandedAccount.id },
                                            $event.index,
                                            $event.tab,
                                        )
                                    "
                                    @import-started="
                                        onImportStarted(
                                            $event,
                                            expandedAccount.id,
                                        )
                                    "
                                />
                                <p
                                    v-if="cropError"
                                    class="mt-2 text-sm text-destructive"
                                    :data-testid="`composer-${expandedAccount.id}-crop-error`"
                                >
                                    {{
                                        $t('posts.composer.crop_upload_failed')
                                    }}
                                </p>
                                <ComposerEditorToolbar
                                    :test-id-prefix="`composer-${expandedAccount.id}`"
                                    :signatures="availableSignatures"
                                    @import-started="
                                        onImportStarted(
                                            $event,
                                            expandedAccount.id,
                                        )
                                    "
                                    @open-unsplash="
                                        openUnsplash(expandedAccount.id)
                                    "
                                    @select-emoji="
                                        appendEmoji($event, expandedAccount.id)
                                    "
                                    @select-signature="
                                        appendSignature(
                                            $event,
                                            expandedAccount.id,
                                        )
                                    "
                                    @save-signature="saveSignature"
                                >
                                    <span
                                        v-if="
                                            remainingCharacters(
                                                expandedAccount,
                                            ) !== null
                                        "
                                        :data-testid="`composer-char-count-${expandedAccount.id}`"
                                        class="rounded-sm border px-1 py-0.5 text-xs leading-3 tabular-nums"
                                        :class="
                                            (remainingCharacters(
                                                expandedAccount,
                                            ) ?? 0) < 0
                                                ? 'border-destructive font-medium text-destructive-text'
                                                : 'border-border-strong text-subtle-foreground'
                                        "
                                    >
                                        {{
                                            remainingCharacters(expandedAccount)
                                        }}
                                    </span>
                                </ComposerEditorToolbar>
                            </div>
                            <FacebookSettings
                                v-if="expandedAccount.platform === 'facebook'"
                                :content-type="expandedDestination.content_type"
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            <TikTokSettings
                                v-else-if="
                                    expandedAccount.platform === 'tiktok'
                                "
                                :social-account="expandedAccount"
                                :publish-config="
                                    platformConfigs[expandedAccount.id]
                                        ?.publishConfig ?? null
                                "
                                :creator-info="
                                    tiktokCreatorInfos[expandedAccount.id] ??
                                    null
                                "
                                :video-duration-sec="videoDurationSec"
                                :content-type="expandedDestination.content_type"
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            <YouTubeSettings
                                v-else-if="
                                    expandedAccount.platform === 'youtube'
                                "
                                :platform-index="
                                    selectedAccounts.findIndex(
                                        (account) =>
                                            account.id === expandedAccountId,
                                    )
                                "
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            <PinterestSettings
                                v-else-if="
                                    expandedAccount.platform === 'pinterest'
                                "
                                :social-account="expandedAccount"
                                :boards="
                                    pinterestBoards[expandedAccount.id]
                                        ?.boards ?? []
                                "
                                :boards-truncated="
                                    pinterestBoards[expandedAccount.id]
                                        ?.truncated ?? false
                                "
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            <LinkedInSettings
                                v-else-if="
                                    ['linkedin', 'linkedin-page'].includes(
                                        expandedAccount.platform,
                                    )
                                "
                                :account-id="expandedAccount.id"
                                :media="expandedDestination.media"
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            <GoogleBusinessSettings
                                v-else-if="
                                    expandedAccount.platform ===
                                    'google_business'
                                "
                                :platform-index="0"
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            <DiscordSettings
                                v-else-if="
                                    expandedAccount.platform === 'discord'
                                "
                                :social-account="expandedAccount"
                                :meta="expandedDestination.meta"
                                @update:meta="
                                    composition.setOverride(
                                        expandedAccount.id,
                                        'meta',
                                        $event,
                                    )
                                "
                            />
                            </div>
                        </div>
                        <div class="mx-auto w-full max-w-[744px] space-y-2">
                            <button
                                v-for="account in selectedAccounts.filter(
                                    (selected) =>
                                        selected.id !== expandedAccountId,
                                )"
                                :key="account.id"
                                type="button"
                                :data-testid="`composer-expand-${account.id}`"
                                class="flex min-h-[50px] w-full items-center gap-3 rounded-xl border bg-card px-3 py-1 text-left transition-control hover:bg-accent"
                                @click="
                                    expandedAccountId = account.id;
                                    previewAccountId = account.id;
                                "
                            >
                                <PlatformLogo
                                    :platform="account.platform"
                                    :size="24"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm text-muted-foreground"
                                    >{{
                                        composition.resolvedDestination(account)
                                            .content ||
                                        account.display_name ||
                                        account.username
                                    }}</span
                                >
                                <span
                                    v-if="destinationIssues(account).length > 0"
                                    :data-testid="`composer-issues-${account.id}`"
                                    class="flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                                    :aria-label="destinationIssueLabel(account)"
                                >
                                    <IconAlertTriangle class="size-3.5" />
                                    {{ destinationIssues(account).length }}
                                </span>
                                <img
                                    v-if="isImage(firstMedia(account))"
                                    :src="firstMedia(account)!.url"
                                    alt=""
                                    :data-testid="`composer-thumb-${account.id}`"
                                    class="size-10 shrink-0 rounded-md border object-cover"
                                />
                                <video
                                    v-else-if="isVideo(firstMedia(account))"
                                    :src="firstMedia(account)!.url"
                                    :data-testid="`composer-thumb-${account.id}`"
                                    class="size-10 shrink-0 rounded-md border object-cover"
                                    muted
                                    playsinline
                                    preload="metadata"
                                />
                                <IconChevronDown
                                    class="size-4 -rotate-90 text-muted-foreground"
                                />
                            </button>
                        </div>
                    </template>
                </div>

                <aside
                    class="min-h-0 flex-col bg-muted"
                    :class="mobilePanelOpen ? 'flex' : 'hidden md:flex'"
                >
                    <div class="border-b px-4 py-2 md:hidden">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            data-testid="composer-mobile-compose"
                            @click="mobilePanelOpen = false"
                        >
                            <IconArrowLeft class="size-4" />
                            {{ $t('posts.edit.tabs.compose') }}
                        </Button>
                    </div>
                    <ComposerTemplatesPanel
                        v-if="sidePanel === 'templates'"
                        @select="insertAssistantText"
                    />
                    <template v-else-if="sidePanel === 'assistant'">
                        <h3
                            class="shrink-0 px-8 pt-[22px] pb-[18px] text-base leading-5 font-medium"
                        >
                            {{ $t('posts.composer.assistant_title') }}
                        </h3>
                        <div
                            class="min-h-0 flex-1 overflow-y-auto px-8 pb-6"
                            data-testid="composer-assistant-panel"
                        >
                            <WritingAssistantPanel
                                :key="
                                    step === 2
                                        ? `account-${expandedAccountId}`
                                        : 'shared'
                                "
                                :content="assistantContent"
                                :channel="assistantChannel"
                                @insert="insertAssistantText"
                                @replace="writeAssistantTarget"
                            />
                        </div>
                    </template>
                    <template v-else>
                        <PreviewPanelTitle
                            :title="
                                step === 1
                                    ? $t('posts.composer.post_previews')
                                    : $t('posts.composer.network_preview', {
                                          network: getPlatformLabel(
                                              previewAccount?.platform ?? '',
                                          ),
                                      })
                            "
                        />
                        <div
                            class="min-h-0 flex-1 space-y-10 overflow-x-hidden overflow-y-auto px-8 pb-8"
                            data-testid="composer-previews-scroll"
                        >
                            <template
                                v-if="
                                    step === 1 &&
                                    selectedAccounts.length &&
                                    hasSharedPreview
                                "
                            >
                                <section
                                    v-for="account in selectedAccounts"
                                    :key="account.id"
                                    data-testid="composer-preview-card"
                                    class="space-y-3"
                                >
                                    <h4
                                        class="flex items-center gap-2.5 text-[15px] leading-5 text-muted-foreground"
                                        data-testid="composer-preview-label"
                                    >
                                        <PlatformLogo
                                            :platform="account.platform"
                                            :size="18"
                                            :title="null"
                                            class="opacity-80 grayscale"
                                        />
                                        {{ getPlatformLabel(account.platform) }}
                                    </h4>
                                    <PlatformPreview
                                        data-testid="composer-preview-frame"
                                        :platform="account.platform"
                                        :social-account="account"
                                        :content="
                                            composition.resolvedDestination(
                                                account,
                                            ).content
                                        "
                                        :media="
                                            composition.resolvedDestination(
                                                account,
                                            ).media
                                        "
                                        :content-type="
                                            composition.resolvedDestination(
                                                account,
                                            ).content_type
                                        "
                                        :meta="
                                            composition.resolvedDestination(
                                                account,
                                            ).meta
                                        "
                                    />
                                </section>
                            </template>
                            <div
                                v-else-if="
                                    step === 2 &&
                                    previewAccount &&
                                    previewDestination &&
                                    (previewDestination.content.trim() ||
                                        previewDestination.media.length)
                                "
                            >
                                <PlatformPreview
                                    data-testid="composer-preview-frame"
                                    :platform="previewAccount.platform"
                                    :social-account="previewAccount"
                                    :content="previewDestination.content"
                                    :media="previewDestination.media"
                                    :content-type="
                                        previewDestination.content_type
                                    "
                                    :meta="previewDestination.meta"
                                />
                            </div>
                            <div
                                v-else
                                class="flex h-full min-h-64 flex-col items-center justify-center gap-5 text-center text-muted-foreground"
                                data-testid="composer-empty-preview"
                            >
                                <div
                                    class="w-36 overflow-hidden rounded-xl border bg-background"
                                >
                                    <div
                                        class="flex items-center gap-2 border-b bg-background p-3"
                                    >
                                        <span
                                            class="size-4 rounded-full bg-muted"
                                        />
                                        <span
                                            class="h-1.5 w-16 rounded-full bg-muted"
                                        />
                                    </div>
                                    <div
                                        class="flex h-24 items-center justify-center bg-secondary"
                                    >
                                        <IconLibraryPhoto
                                            class="size-7 text-muted-foreground/40"
                                        />
                                    </div>
                                    <div class="space-y-2 bg-background p-3">
                                        <span
                                            class="block h-1.5 w-full rounded-full bg-muted"
                                        /><span
                                            class="block h-1.5 w-2/3 rounded-full bg-muted"
                                        />
                                    </div>
                                </div>
                                <p class="text-base">
                                    {{ $t('posts.composer.preview_empty') }}
                                </p>
                            </div>
                        </div>
                    </template>
                </aside>
            </div>

            <footer
                class="flex shrink-0 flex-col gap-3 border-t px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8"
            >
                <div class="flex flex-1 items-center gap-3.5">
                    <label
                        v-if="!postId"
                        class="flex cursor-pointer items-center gap-2 text-sm font-medium"
                    >
                        <Checkbox
                            v-model="createAnother"
                            data-testid="composer-create-another"
                        />
                        {{ $t('posts.composer.create_another') }}
                    </label>
                    <Button
                        type="button"
                        variant="ghost"
                        size="lg"
                        data-testid="composer-save-draft"
                        :disabled="!canSubmit"
                        @click="submit('draft')"
                        >{{
                            $t(
                                isBatch
                                    ? 'posts.composer.save_drafts'
                                    : 'posts.composer.save_draft',
                            )
                        }}</Button
                    >
                    <p
                        v-if="mediaFailed"
                        role="alert"
                        data-testid="composer-upload-blocked"
                        class="text-sm text-destructive-text"
                    >
                        {{ $t('posts.composer.upload_blocked') }}
                    </p>
                </div>
                <Popover v-model:open="scheduleMenuOpen">
                    <PopoverAnchor as-child>
                        <div class="flex items-center gap-0">
                            <PopoverTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="lg"
                                    class="rounded-l-xl rounded-r-none ps-3 pe-2"
                                    data-testid="composer-schedule-trigger"
                                >
                                    <component
                                        :is="scheduleTriggerIcon.component"
                                        class="size-4"
                                        data-testid="composer-schedule-trigger-icon"
                                        :data-icon="scheduleTriggerIcon.name"
                                    />{{
                                        scheduleDateLabel ??
                                        $t(scheduleLabelKey)
                                    }}<IconChevronUp
                                        class="size-4"
                                        data-testid="composer-schedule-trigger-chevron"
                                    />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="end"
                                side="top"
                                :class="
                                    schedulePanel === 'picker'
                                        ? 'w-[21rem] p-0'
                                        : 'w-80 space-y-0.5 p-3'
                                "
                            >
                                <ComposerSchedulePicker
                                    v-if="schedulePanel === 'picker'"
                                    :model-value="composition.scheduledAt.value"
                                    @back="schedulePanel = 'menu'"
                                    @confirm="confirmScheduledAt"
                                />
                                <template v-else>
                                <TooltipProvider :delay-duration="150">
                                    <div
                                        v-for="option in scheduleOptions"
                                        :key="option.mode"
                                        class="group/option relative"
                                        :data-testid="`composer-schedule-row-${option.mode}`"
                                    >
                                        <Tooltip
                                            :disabled="
                                                !isScheduleModeDisabled(
                                                    option.mode,
                                                )
                                            "
                                        >
                                            <TooltipTrigger as-child>
                                                <span class="block">
                                                        <button
                                                            type="button"
                                                            :data-testid="`composer-schedule-${option.mode}`"
                                                            :aria-pressed="
                                                                scheduleMode ===
                                                                option.mode
                                                            "
                                                            :disabled="
                                                                isScheduleModeDisabled(
                                                                    option.mode,
                                                                )
                                                            "
                                                            class="w-full space-y-1.5 rounded-md py-2 ps-3 pe-11 text-left text-sm transition-control outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                            :class="
                                                                scheduleMode ===
                                                                option.mode
                                                                    ? 'bg-primary-subtle text-primary-text'
                                                                    : 'text-foreground enabled:hover:bg-accent focus-visible:bg-accent'
                                                            "
                                                            @click="
                                                                selectScheduleMode(
                                                                    option.mode,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="flex items-center gap-1 leading-[17.5px] font-emphasis"
                                                                ><IconCheck
                                                                    v-if="
                                                                        scheduleMode ===
                                                                        option.mode
                                                                    "
                                                                    class="size-4 shrink-0"
                                                                /><span
                                                                    v-else
                                                                    class="size-4 shrink-0"
                                                                    aria-hidden="true"
                                                                />{{
                                                                    $t(option.titleKey)
                                                                }}</span
                                                            >
                                                            <span
                                                                class="block ps-5 leading-[21px]"
                                                                >{{
                                                                    $t(
                                                                        option.descriptionKey,
                                                                    )
                                                                }}</span
                                                            >
                                                        </button>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                side="left"
                                                :data-testid="`composer-schedule-blocked-${option.mode}`"
                                            >
                                                {{
                                                    $t(
                                                        'posts.composer.queue.no_slots_tooltip',
                                                        {
                                                            channels:
                                                                accountsWithoutSlotsLabel,
                                                        },
                                                    )
                                                }}
                                            </TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    type="button"
                                                    :data-testid="`composer-schedule-default-${option.mode}`"
                                                    :aria-pressed="
                                                        currentDefaultPostAction ===
                                                        option.mode
                                                    "
                                                    :aria-label="
                                                        $t(
                                                            'posts.composer.queue.set_default',
                                                            {
                                                                option: $t(
                                                                    option.titleKey,
                                                                ),
                                                            },
                                                        )
                                                    "
                                                    class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-md text-muted-foreground transition-[opacity,background-color,color] duration-150 outline-none hover:bg-sidebar-action-hover focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-ring"
                                                    :class="
                                                        currentDefaultPostAction ===
                                                        option.mode
                                                            ? 'opacity-100'
                                                            : 'opacity-0 group-hover/option:opacity-100'
                                                    "
                                                    @click.stop="
                                                        setDefaultPostAction(
                                                            option.mode,
                                                        )
                                                    "
                                                >
                                                    <IconStarFilled
                                                        v-if="
                                                            currentDefaultPostAction ===
                                                            option.mode
                                                        "
                                                        class="size-4"
                                                        :stroke-width="2.2"
                                                    />
                                                    <IconStar
                                                        v-else
                                                        class="size-4"
                                                        :stroke-width="2.2"
                                                    />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top">
                                                {{
                                                    $t(
                                                        'posts.composer.queue.set_default',
                                                        {
                                                            option: $t(
                                                                option.titleKey,
                                                            ),
                                                        },
                                                    )
                                                }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>
                                <div
                                    v-if="queueBlocked"
                                    data-testid="composer-queue-hint"
                                    class="mt-2 space-y-1 border-t border-border-strong px-3 pt-3 pb-1 text-xs text-muted-foreground"
                                >
                                    <p>{{ $t('posts.composer.queue.no_slots_hint') }}</p>
                                    <a
                                        v-if="accountsWithoutSlots.length === 1"
                                        :href="
                                            channelSettings.url(
                                                accountsWithoutSlots[0].id,
                                            )
                                        "
                                        data-testid="composer-queue-manage-slots"
                                        class="font-medium text-foreground underline underline-offset-2"
                                        >{{
                                            $t('posts.composer.queue.manage_slots')
                                        }}</a
                                    >
                                    <p v-else>
                                        {{
                                            $t(
                                                'posts.composer.queue.no_slots_channels',
                                                {
                                                    channels: accountsWithoutSlots
                                                        .map(
                                                            (account) =>
                                                                account.display_label ||
                                                                account.display_name,
                                                        )
                                                        .join(', '),
                                                },
                                            )
                                        }}
                                    </p>
                                </div>
                                </template>
                            </PopoverContent>
                        <Button
                            v-if="step === 1"
                            type="button"
                            size="lg"
                            class="rounded-l-none rounded-r-xl"
                            data-testid="composer-next"
                            :disabled="selectedAccounts.length === 0"
                            @click="goToCustomization"
                            >{{ $t('posts.composer.customize_networks')
                            }}<IconArrowRight class="size-4"
                        /></Button>
                        <TooltipProvider v-else :delay-duration="150">
                            <Tooltip :disabled="!blockingIssue">
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        size="lg"
                                        class="rounded-l-none rounded-r-xl aria-disabled:cursor-not-allowed aria-disabled:bg-border-strong aria-disabled:text-subtle-foreground aria-disabled:hover:bg-border-strong aria-disabled:active:translate-y-0"
                                        data-testid="composer-submit"
                                        :data-schedule-mode="scheduleMode"
                                        :disabled="
                                            !canSubmit ||
                                            (scheduleMode === 'custom' &&
                                                !composition.scheduledAt.value)
                                        "
                                        :aria-disabled="
                                            hasBlockingIssues ? 'true' : undefined
                                        "
                                        @click="submitSelectedSchedule"
                                        ><IconLoader2
                                            v-if="submitting || cropUploading"
                                            class="size-4 animate-spin"
                                        />{{
                                            isQueueMode && !postId
                                                ? isBatch
                                                    ? $t('posts.composer.queue.add_many', {
                                                          count: String(
                                                              selectedAccounts.length,
                                                          ),
                                                      })
                                                    : $t('posts.composer.queue.add')
                                                : scheduleMode === 'now'
                                                ? $t(
                                                      isBatch
                                                          ? 'posts.composer.publish_posts'
                                                          : 'posts.composer.publish_now',
                                                  )
                                                : $t(
                                                      isBatch
                                                          ? 'posts.composer.schedule_posts'
                                                          : 'posts.edit.schedule',
                                                  )
                                        }}</Button
                                    >
                                </TooltipTrigger>
                                <TooltipContent
                                    v-if="blockingIssue"
                                    side="top"
                                    data-testid="composer-blocked-tooltip"
                                >
                                    {{
                                        $t('posts.composer.blocked_tooltip', {
                                            issue: $t(
                                                blockingIssue.key,
                                                blockingIssue.warning
                                                    ? mediaWarningParams(
                                                          blockingIssue.warning,
                                                          blockingIssue.contentType,
                                                          $t,
                                                      )
                                                    : blockingIssue.params,
                                            ),
                                            channel:
                                                blockingIssue.account.display_name ||
                                                blockingIssue.account.username,
                                        })
                                    }}
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        </div>
                    </PopoverAnchor>
                </Popover>
            </footer>
        </DialogContent>
    </Dialog>

    <MediaEditorDialog
        v-model:open="cropping"
        :items="cropItems"
        :initial-index="cropTarget?.initialIndex ?? 0"
        :initial-tab="cropTarget?.tab ?? 'edit'"
        :content-types="cropContentTypes"
        :aspect-bounds="cropAspectBounds"
        @apply="onMediaEdited"
    />
    <UnsplashDialog
        v-model:open="unsplashOpen"
        @picked="appendMedia(unsplashAccountId, $event)"
    />
    <ResumeUnfinishedPostDialog
        :open="resumeOpen"
        :accounts="resumeAccounts"
        :preview="resumePreview"
        :media-count="resumeMediaCount"
        @update:open="if (!$event) resumeOpen = false;"
        @discard="discardUnfinishedPost"
        @resume="resumeUnfinishedPost"
    />
</template>
