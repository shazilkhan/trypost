<script setup lang="ts">
import { computed } from 'vue';

import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import SettingsRow from '@/components/posts/editor/SettingsRow.vue';
import SettingsSection from '@/components/posts/editor/SettingsSection.vue';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePageErrors } from '@/composables/usePageErrors';
import {
    GOOGLE_BUSINESS_CTA_OPTIONS,
    GOOGLE_BUSINESS_EVENT_TOPIC_TYPES,
    GOOGLE_BUSINESS_TOPIC_TYPES,
    GoogleBusinessCtaAction,
    GoogleBusinessTopicType,
    googleBusinessAllowsCallToAction,
    googleBusinessEventDateTimeParts,
    googleBusinessEventDateTimeValue,
    resolveGoogleBusinessCtaAction,
    resolveGoogleBusinessTopicType,
    type GoogleBusinessCtaActionValue,
    type GoogleBusinessTopicTypeValue,
} from '@/lib/googleBusiness';

interface Props {
    /** This panel's position in the submitted `platforms` array — see findError. */
    platformIndex: number;
    meta: Record<string, any>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<{
    'update:meta': [value: Record<string, any>];
}>();

const updateMeta = (patch: Record<string, any>) => {
    emit('update:meta', { ...props.meta, ...patch });
};

const updateEvent = (patch: Record<string, any>) => {
    updateMeta({ event: { ...props.meta?.event, ...patch } });
};

const topicType = computed<GoogleBusinessTopicTypeValue>({
    get: () => resolveGoogleBusinessTopicType(props.meta?.topic_type),
    set: (value: GoogleBusinessTopicTypeValue) => {
        if (value === GoogleBusinessTopicType.Standard) {
            updateMeta({ topic_type: value, event: null, offer: null });
            return;
        }

        if (value === GoogleBusinessTopicType.Event) {
            updateMeta({ topic_type: value, offer: null });
            return;
        }

        updateMeta({ topic_type: value, call_to_action: null });
    },
});

const ctaActionType = computed<GoogleBusinessCtaActionValue>({
    get: () =>
        resolveGoogleBusinessCtaAction(props.meta?.call_to_action?.action_type),
    set: (value: GoogleBusinessCtaActionValue) =>
        updateMeta({
            call_to_action: {
                ...props.meta?.call_to_action,
                action_type: value,
            },
        }),
});

const ctaLabelKey = computed(
    () =>
        GOOGLE_BUSINESS_CTA_OPTIONS.find(
            (option) => option.value === ctaActionType.value,
        )?.labelKey ?? GOOGLE_BUSINESS_CTA_OPTIONS[0].labelKey,
);

const showCallToAction = computed(() =>
    googleBusinessAllowsCallToAction(topicType.value),
);

const showCtaUrl = computed(
    () =>
        showCallToAction.value &&
        ctaActionType.value !== GoogleBusinessCtaAction.None &&
        ctaActionType.value !== GoogleBusinessCtaAction.Call,
);

const showEventFields = computed(() =>
    GOOGLE_BUSINESS_EVENT_TOPIC_TYPES.includes(topicType.value),
);

const ctaUrl = computed<string>({
    get: () => props.meta?.call_to_action?.url || '',
    set: (value: string) =>
        updateMeta({
            call_to_action: {
                ...props.meta?.call_to_action,
                url: value.trim() === '' ? null : value,
            },
        }),
});

const eventTitle = computed<string>({
    get: () => props.meta?.event?.title || '',
    set: (value: string) =>
        updateEvent({ title: value.trim() === '' ? null : value }),
});

const eventDateTime = (
    dateKey: 'start_date' | 'end_date',
    timeKey: 'start_time' | 'end_time',
) =>
    computed({
        get: (): string =>
            googleBusinessEventDateTimeValue(
                props.meta?.event?.[dateKey],
                props.meta?.event?.[timeKey],
            ),
        set: (value: string | null) => {
            const parts = googleBusinessEventDateTimeParts(value);
            updateEvent({ [dateKey]: parts.date, [timeKey]: parts.time });
        },
    });

const eventStart = eventDateTime('start_date', 'start_time');
const eventEnd = eventDateTime('end_date', 'end_time');

const eventTitleLabelKey = computed(() =>
    topicType.value === GoogleBusinessTopicType.Offer
        ? 'posts.form.google_business.offer_title'
        : 'posts.form.google_business.event_title',
);

const eventTitlePlaceholderKey = computed(() =>
    topicType.value === GoogleBusinessTopicType.Offer
        ? 'posts.form.google_business.offer_title_placeholder'
        : 'posts.form.google_business.event_title_placeholder',
);

const offerField = (
    key: 'coupon_code' | 'redeem_online_url' | 'terms_conditions',
) =>
    computed<string>({
        get: () => props.meta?.offer?.[key] || '',
        set: (value: string) =>
            updateMeta({
                offer: {
                    ...props.meta?.offer,
                    [key]: value.trim() === '' ? null : value,
                },
            }),
    });

const offerCouponCode = offerField('coupon_code');
const offerRedeemUrl = offerField('redeem_online_url');
const offerTerms = offerField('terms_conditions');

// Backend validation errors are keyed `platforms.{index}.meta.*`. Matching the
// full key keeps a location's error off the other locations' panels when a post
// targets more than one Google Business Profile.
const errors = usePageErrors();
const findError = (field: string) =>
    computed<string | undefined>(
        () => errors.value[`platforms.${props.platformIndex}.meta.${field}`],
    );
const eventTitleError = findError('event.title');
const eventStartDateError = findError('event.start_date');
const eventEndDateError = findError('event.end_date');
const eventStartTimeError = findError('event.start_time');
const eventEndTimeError = findError('event.end_time');
const offerCouponCodeError = findError('offer.coupon_code');
const offerRedeemUrlError = findError('offer.redeem_online_url');
const offerTermsError = findError('offer.terms_conditions');
const ctaActionTypeError = findError('call_to_action.action_type');
const ctaUrlError = findError('call_to_action.url');
</script>

<template>
    <SettingsSection>
        <SettingsRow :label="$t('posts.form.google_business.topic_type_label')">
            <RadioGroup
                v-model="topicType"
                :disabled="disabled"
                orientation="horizontal"
                :aria-label="$t('posts.form.google_business.topic_type_label')"
                class="flex min-h-8 flex-wrap items-center gap-x-5 gap-y-2"
            >
                <label
                    v-for="type in GOOGLE_BUSINESS_TOPIC_TYPES"
                    :key="type.value"
                    class="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <RadioGroupItem
                        :value="type.value"
                        :data-testid="`google-business-topic-${type.value}`"
                    />
                    {{ $t(type.labelKey) }}
                </label>
            </RadioGroup>
        </SettingsRow>

        <template v-if="showEventFields">
            <SettingsRow
                :label="$t(eventTitleLabelKey)"
                :label-for="`google-business-event-title-${platformIndex}`"
                align-top
            >
                <Input
                    :id="`google-business-event-title-${platformIndex}`"
                    v-model="eventTitle"
                    type="text"
                    :placeholder="$t(eventTitlePlaceholderKey)"
                    :disabled="disabled"
                    :aria-invalid="eventTitleError ? true : undefined"
                />
                <InputError :message="eventTitleError" />
            </SettingsRow>
            <SettingsRow
                :label="$t('posts.form.google_business.event_start_date')"
                align-top
            >
                <DatePicker
                    v-model="eventStart"
                    align="start"
                    :show-time="true"
                    :disabled="disabled"
                    :placeholder="
                        $t('posts.form.google_business.event_start_date')
                    "
                    :class="
                        eventStartDateError || eventStartTimeError
                            ? 'border-destructive'
                            : undefined
                    "
                />
                <InputError
                    :message="eventStartDateError || eventStartTimeError"
                />
            </SettingsRow>
            <SettingsRow
                :label="$t('posts.form.google_business.event_end_date')"
                align-top
            >
                <DatePicker
                    v-model="eventEnd"
                    align="start"
                    :show-time="true"
                    :disabled="disabled"
                    :placeholder="
                        $t('posts.form.google_business.event_end_date')
                    "
                    :class="
                        eventEndDateError || eventEndTimeError
                            ? 'border-destructive'
                            : undefined
                    "
                />
                <InputError :message="eventEndDateError || eventEndTimeError" />
                <p
                    class="text-xs text-muted-foreground"
                    data-testid="google-business-event-timezone-hint"
                >
                    {{
                        $t(
                            'posts.form.google_business.event_times_use_location',
                        )
                    }}
                </p>
            </SettingsRow>
        </template>

        <template v-if="topicType === GoogleBusinessTopicType.Offer">
            <SettingsRow
                :label="$t('posts.form.google_business.offer_coupon_code')"
                :label-for="`google-business-coupon-${platformIndex}`"
                align-top
            >
                <Input
                    :id="`google-business-coupon-${platformIndex}`"
                    v-model="offerCouponCode"
                    type="text"
                    :disabled="disabled"
                    :aria-invalid="offerCouponCodeError ? true : undefined"
                />
                <InputError :message="offerCouponCodeError" />
            </SettingsRow>
            <SettingsRow
                :label="$t('posts.form.google_business.offer_redeem_url')"
                :label-for="`google-business-redeem-${platformIndex}`"
                align-top
            >
                <Input
                    :id="`google-business-redeem-${platformIndex}`"
                    v-model="offerRedeemUrl"
                    type="text"
                    :disabled="disabled"
                    :aria-invalid="offerRedeemUrlError ? true : undefined"
                />
                <InputError :message="offerRedeemUrlError" />
            </SettingsRow>
            <SettingsRow
                :label="$t('posts.form.google_business.offer_terms')"
                :label-for="`google-business-terms-${platformIndex}`"
                align-top
            >
                <Input
                    :id="`google-business-terms-${platformIndex}`"
                    v-model="offerTerms"
                    type="text"
                    :disabled="disabled"
                    :aria-invalid="offerTermsError ? true : undefined"
                />
                <InputError :message="offerTermsError" />
            </SettingsRow>
        </template>

        <SettingsRow
            v-if="showCallToAction"
            :label="$t('posts.form.google_business.cta_label')"
            :label-for="`google-business-cta-${platformIndex}`"
            align-top
        >
            <Select v-model="ctaActionType" :disabled="disabled">
                <SelectTrigger
                    :id="`google-business-cta-${platformIndex}`"
                    class="w-full"
                    :aria-invalid="ctaActionTypeError ? true : undefined"
                >
                    <SelectValue>{{ $t(ctaLabelKey) }}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in GOOGLE_BUSINESS_CTA_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ $t(option.labelKey) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="ctaActionTypeError" />
        </SettingsRow>

        <SettingsRow
            v-if="showCtaUrl"
            :label="$t('posts.form.google_business.cta_url')"
            :label-for="`google-business-cta-url-${platformIndex}`"
            align-top
        >
            <Input
                :id="`google-business-cta-url-${platformIndex}`"
                v-model="ctaUrl"
                type="text"
                :placeholder="
                    $t('posts.form.google_business.cta_url_placeholder')
                "
                :disabled="disabled"
                :aria-invalid="ctaUrlError ? true : undefined"
            />
            <InputError :message="ctaUrlError" />
        </SettingsRow>
    </SettingsSection>
</template>
