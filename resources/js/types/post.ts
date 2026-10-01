export const PostStatus = {
    Draft: 'draft',
    Scheduled: 'scheduled',
    Publishing: 'publishing',
    Published: 'published',
    PartiallyPublished: 'partially_published',
    Failed: 'failed',
} as const;

export type PostStatusValue = (typeof PostStatus)[keyof typeof PostStatus];

export const PostPlatformStatus = {
    Pending: 'pending',
    Publishing: 'publishing',
    Published: 'published',
    Failed: 'failed',
    Retrying: 'retrying',
    Rejected: 'rejected',
    PendingReview: 'pending_review',
} as const;

export type PostPlatformStatusValue = (typeof PostPlatformStatus)[keyof typeof PostPlatformStatus];

export const ScheduleMode = {
    Queue: 'queue',
    Custom: 'custom',
} as const;

export type ScheduleModeValue = (typeof ScheduleMode)[keyof typeof ScheduleMode];

export type QueuePositionValue = 'next' | 'top';
