import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import {
    draggable,
    dropTargetForElements,
    monitorForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { reorder } from '@atlaskit/pragmatic-drag-and-drop/reorder';
import { attachClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge/attach-closest-edge';
import { extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge/extract-closest-edge';
import { getReorderDestinationIndex } from '@atlaskit/pragmatic-drag-and-drop-hitbox/util/get-reorder-destination-index';
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { nextTick, onBeforeUnmount, ref, type Directive, type Ref } from 'vue';
import { toast } from 'vue-sonner';

import { reorder as reorderChannels } from '@/routes/app/channels';

export interface ChannelDropIndicator {
    channelId: string;
    edge: 'top' | 'bottom';
}

const ITEM_KEY = 'sortableChannel';
const CHANNEL_ORDER_PROPS = ['channels', 'connectedChannels'];

const optimisticOrder = ref<string[] | null>(null);
const saving = ref(false);

const isChannelItem = (
    data: Record<string | symbol, unknown>,
    list: string,
): data is Record<string | symbol, unknown> & { channelId: string } =>
    data[ITEM_KEY] === list && typeof data.channelId === 'string';

export const orderChannels = <T extends { id: string }>(items: T[]): T[] => {
    const order = optimisticOrder.value;

    if (!order) {
        return items;
    }

    const index = new Map(order.map((id, position) => [id, position]));

    return [...items].sort(
        (a, b) =>
            (index.get(a.id) ?? Number.MAX_SAFE_INTEGER) -
            (index.get(b.id) ?? Number.MAX_SAFE_INTEGER),
    );
};

const saveChannelOrder = (orderedIds: string[]): void => {
    if (saving.value) {
        return;
    }

    saving.value = true;
    optimisticOrder.value = orderedIds;

    const rollback = (message: string): void => {
        optimisticOrder.value = null;
        toast.error(message, { testId: 'channels-reorder-error-toast' });
        router.reload({ only: CHANNEL_ORDER_PROPS });
    };

    router.put(
        reorderChannels.url(),
        { social_account_ids: orderedIds },
        {
            preserveScroll: true,
            preserveState: true,
            only: CHANNEL_ORDER_PROPS,
            onError: (errors) =>
                rollback(
                    errors.social_account_ids ??
                        Object.values(errors)[0] ??
                        trans('channels.reorder.failed'),
                ),
            onHttpException: () => {
                rollback(trans('channels.reorder.failed'));

                return false;
            },
            onNetworkError: () => {
                rollback(trans('channels.reorder.failed'));

                return false;
            },
            onFinish: () => {
                saving.value = false;
                optimisticOrder.value = null;
            },
        },
    );
};

const focusHandle = (list: string, channelId: string): void => {
    nextTick(() =>
        document
            .querySelector<HTMLElement>(
                `[data-channel-handle="${list}:${channelId}"]`,
            )
            ?.focus(),
    );
};

/**
 * Drag-and-drop (by handle) and keyboard reordering for one rendered list of
 * channels. `list` keeps two lists on the same page from accepting each
 * other's drops; `order` returns the ids as currently rendered.
 */
export const useChannelOrder = (options: {
    list: string;
    order: () => string[];
}): {
    vSortableChannel: Directive<HTMLElement, string | null>;
    move: (channelId: string, offset: -1 | 1) => void;
    onHandleKeydown: (event: KeyboardEvent, channelId: string) => void;
    dropIndicator: Ref<ChannelDropIndicator | null>;
    saving: Ref<boolean>;
} => {
    const { list } = options;
    const dropIndicator = ref<ChannelDropIndicator | null>(null);

    const stopMonitor = monitorForElements({
        canMonitor: ({ source }) => isChannelItem(source.data, list),
        onDrop: ({ source, location }) => {
            dropIndicator.value = null;

            const target = location.current.dropTargets[0];

            if (
                !target ||
                !isChannelItem(source.data, list) ||
                !isChannelItem(target.data, list)
            ) {
                return;
            }

            const order = options.order();
            const startIndex = order.indexOf(source.data.channelId);
            const targetIndex = order.indexOf(target.data.channelId);

            if (startIndex === -1 || targetIndex === -1) {
                return;
            }

            const finishIndex = getReorderDestinationIndex({
                startIndex,
                indexOfTarget: targetIndex,
                closestEdgeOfTarget: extractClosestEdge(target.data),
                axis: 'vertical',
            });

            if (finishIndex !== startIndex) {
                saveChannelOrder(
                    reorder({ list: order, startIndex, finishIndex }),
                );
            }
        },
    });

    onBeforeUnmount(stopMonitor);

    const register = (
        row: HTMLElement,
        handle: HTMLElement,
        channelId: string,
    ): (() => void) =>
        combine(
            draggable({
                element: row,
                dragHandle: handle,
                canDrag: () => !saving.value,
                getInitialData: () => ({ [ITEM_KEY]: list, channelId }),
                onDragStart: () => row.setAttribute('data-dragging', ''),
                onDrop: () => row.removeAttribute('data-dragging'),
            }),
            dropTargetForElements({
                element: row,
                canDrop: ({ source }) => isChannelItem(source.data, list),
                getData: ({ input }) =>
                    attachClosestEdge(
                        { [ITEM_KEY]: list, channelId },
                        {
                            element: row,
                            input,
                            allowedEdges: ['top', 'bottom'],
                        },
                    ),
                onDrag: ({ self, source }) => {
                    const edge = extractClosestEdge(self.data);

                    dropIndicator.value =
                        edge &&
                        isChannelItem(source.data, list) &&
                        source.data.channelId !== channelId
                            ? {
                                  channelId,
                                  edge: edge === 'bottom' ? 'bottom' : 'top',
                              }
                            : null;
                },
                onDragLeave: () => {
                    if (dropIndicator.value?.channelId === channelId) {
                        dropIndicator.value = null;
                    }
                },
                onDrop: () => {
                    dropIndicator.value = null;
                },
            }),
        );

    const cleanups = new WeakMap<HTMLElement, () => void>();

    const unbind = (row: HTMLElement): void => {
        cleanups.get(row)?.();
        cleanups.delete(row);
    };

    const bind = (row: HTMLElement, channelId: string | null): void => {
        const handle = channelId
            ? row.querySelector<HTMLElement>(
                  `[data-channel-handle="${list}:${channelId}"]`,
              )
            : null;

        if (channelId && handle) {
            cleanups.set(row, register(row, handle, channelId));
        }
    };

    const vSortableChannel: Directive<HTMLElement, string | null> = {
        mounted: (row, { value }) => bind(row, value),
        updated: (row, { value, oldValue }) => {
            if (value === oldValue && cleanups.has(row) === (value !== null)) {
                return;
            }

            unbind(row);
            bind(row, value);
        },
        unmounted: (row) => unbind(row),
    };

    const move = (channelId: string, offset: -1 | 1): void => {
        const order = options.order();
        const startIndex = order.indexOf(channelId);
        const finishIndex = startIndex + offset;

        if (startIndex === -1 || finishIndex < 0 || finishIndex >= order.length) {
            return;
        }

        saveChannelOrder(reorder({ list: order, startIndex, finishIndex }));
        focusHandle(list, channelId);
    };

    const onHandleKeydown = (event: KeyboardEvent, channelId: string): void => {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
            return;
        }

        event.preventDefault();
        move(channelId, event.key === 'ArrowUp' ? -1 : 1);
    };

    return { vSortableChannel, move, onHandleKeydown, dropIndicator, saving };
};
