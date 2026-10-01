import type { PublicationAnalyticsDetail } from '@/types/analytics';
import type { MediaItem } from '@/types/media';
import type { PostStatusValue, ScheduleModeValue } from '@/types/post';

export type PublishTab = 'queue' | 'drafts' | 'sent';

export type PublishScope = 'all' | 'channel';

export type PublishCounts = Record<PublishTab, number>;

export interface PublishSocialAccount {
    id: string;
    platform: string;
    display_name: string | null;
    username: string;
    display_label: string;
    avatar_url: string | null;
    handle_label?: string;
    has_posting_schedule?: boolean;
}

export interface PublishChannel extends PublishSocialAccount {
    has_posting_schedule: boolean;
    posting_goal: number | null;
    sent_this_week: number;
}

export interface PostCardPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    status: string;
    platform_url?: string | null;
    social_account: PublishSocialAccount | null;
    content_type?: string;
    meta?: Record<string, any>;
}

export interface PostCardLabel {
    id: string;
    name: string;
    color: string;
}

export interface UnavailablePostMetrics {
    available: false;
    reason: string | null;
}

export type PostCardMetrics = PublicationAnalyticsDetail | UnavailablePostMetrics;

export interface PostCard {
    id: string;
    card_key?: string;
    content: string | null;
    status: PostStatusValue;
    created_at: string;
    updated_at: string;
    scheduled_at: string | null;
    schedule_mode?: ScheduleModeValue | null;
    published_at: string | null;
    user: { name: string } | null;
    post_platforms: PostCardPlatform[];
    labels: PostCardLabel[];
    notes_count: number;
    media?: MediaItem[];
    can_delete: boolean;
    metrics?: Record<string, PostCardMetrics | null>;
}

export interface QueueItem {
    type: 'post' | 'slot';
    at: string;
    channel_id: string;
    post_id: string | null;
}

export interface QueueDay {
    date: string;
    items: QueueItem[];
}

export interface QueuePostPosition {
    canMoveUp: boolean;
    canMoveDown: boolean;
    draggable: boolean;
}

export interface PublishQueue {
    days: QueueDay[];
    needsAttention: PostCard[];
    queueDays: number;
    maxQueueDays: number;
}

export interface QueueView {
    days: QueueDay[];
    posts: Record<string, PostCard>;
}

export interface ScrollPostCards {
    data: PostCard[];
}

export type PostCardMove = 'top' | 'up' | 'down';

export type PostScheduleAction = 'draft' | 'publish_now' | 'queue_next' | 'queue_top';

export type PostCardMenuAction =
    | 'publish_now'
    | 'move_top'
    | 'move_up'
    | 'move_down'
    | 'duplicate'
    | 'move_drafts'
    | 'details'
    | 'delete';

export interface CalendarPostPlatform {
    id: string;
    platform: string;
    status: string;
    social_account: {
        id: string;
        platform: string;
        display_name: string;
        username: string | null;
        display_label: string;
    } | null;
}

export interface CalendarPost {
    id: string;
    status: string;
    content: string | null;
    scheduled_at: string;
    schedule_mode?: ScheduleModeValue | null;
    media?: MediaItem[] | null;
    post_platforms: CalendarPostPlatform[];
}

export type CalendarView = 'week' | 'month';

export type CalendarStatus = 'all' | 'drafts' | 'scheduled' | 'sent';

export interface CalendarSlot {
    at: string;
    channel_id: string;
}

export interface UndatedDraft extends Omit<CalendarPost, 'scheduled_at'> {
    scheduled_at: null;
}

export interface ScrollUndatedDrafts {
    data: UndatedDraft[];
}
