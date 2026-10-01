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
import { onBeforeUnmount, ref, type Ref } from 'vue';

export interface IdeaBoardCardItem {
    ideaId: string;
    stageId: string | null;
}

export interface IdeaBoardColumnItem {
    stageId: string | null;
}

export interface IdeaCardIndicator {
    ideaId: string;
    edge: 'top' | 'bottom';
}

export interface IdeaColumnIndicator {
    stageId: string;
    edge: 'left' | 'right';
}

type DragData = Record<string | symbol, unknown>;

const CARD_KEY = 'ideaBoardCard';
const COLUMN_KEY = 'ideaBoardColumn';
const CARD_OVER_ATTRIBUTE = 'data-card-over';

const isCard = (data: DragData): data is DragData & IdeaBoardCardItem =>
    data[CARD_KEY] === true && typeof data.ideaId === 'string';

const isColumn = (data: DragData): data is DragData & IdeaBoardColumnItem =>
    data[COLUMN_KEY] === true && 'stageId' in data;

const isStageColumn = (
    data: DragData,
): data is DragData & { stageId: string } =>
    isColumn(data) && typeof data.stageId === 'string';

const byDocumentOrder = <T>([a]: [HTMLElement, T], [b]: [HTMLElement, T]): number =>
    a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;

export const useIdeaBoard = (options: {
    onMoveIdea: (
        ideaId: string,
        toStageId: string | null,
        orderedIdeaIds: string[],
    ) => void;
    onReorderStages: (orderedStageIds: string[]) => void;
}): {
    registerCard: (el: HTMLElement, item: IdeaBoardCardItem) => () => void;
    registerColumn: (el: HTMLElement, column: IdeaBoardColumnItem) => () => void;
    registerColumnHandle: (
        handle: HTMLElement,
        column: HTMLElement,
        item: { stageId: string },
    ) => () => void;
    cardIndicator: Ref<IdeaCardIndicator | null>;
    columnIndicator: Ref<IdeaColumnIndicator | null>;
} => {
    const cardIndicator = ref<IdeaCardIndicator | null>(null);
    const columnIndicator = ref<IdeaColumnIndicator | null>(null);
    const cards = new Map<HTMLElement, IdeaBoardCardItem>();
    const columns = new Map<HTMLElement, IdeaBoardColumnItem>();

    const columnIdeas = (stageId: string | null): string[] =>
        [...cards]
            .filter(([, item]) => item.stageId === stageId)
            .sort(byDocumentOrder)
            .map(([, item]) => item.ideaId);

    const stageOrder = (): string[] =>
        [...columns]
            .filter(([, column]) => column.stageId !== null)
            .sort(byDocumentOrder)
            .map(([, column]) => column.stageId as string);

    const clearIndicators = (): void => {
        cardIndicator.value = null;
        columnIndicator.value = null;
    };

    const dropCardOnCard = (
        source: IdeaBoardCardItem,
        target: DragData & IdeaBoardCardItem,
    ): void => {
        const edge = extractClosestEdge(target);

        if (source.stageId === target.stageId) {
            const order = columnIdeas(source.stageId);
            const startIndex = order.indexOf(source.ideaId);
            const targetIndex = order.indexOf(target.ideaId);

            if (startIndex === -1 || targetIndex === -1) {
                return;
            }

            const finishIndex = getReorderDestinationIndex({
                startIndex,
                indexOfTarget: targetIndex,
                closestEdgeOfTarget: edge,
                axis: 'vertical',
            });

            if (finishIndex !== startIndex) {
                options.onMoveIdea(
                    source.ideaId,
                    source.stageId,
                    reorder({ list: order, startIndex, finishIndex }),
                );
            }

            return;
        }

        const order = columnIdeas(target.stageId);
        const targetIndex = order.indexOf(target.ideaId);

        if (targetIndex === -1) {
            return;
        }

        order.splice(
            edge === 'bottom' ? targetIndex + 1 : targetIndex,
            0,
            source.ideaId,
        );
        options.onMoveIdea(source.ideaId, target.stageId, order);
    };

    const dropCardOnColumn = (
        source: IdeaBoardCardItem,
        column: IdeaBoardColumnItem,
    ): void => {
        const order = columnIdeas(column.stageId).filter(
            (id) => id !== source.ideaId,
        );

        if (
            source.stageId === column.stageId &&
            columnIdeas(column.stageId).at(-1) === source.ideaId
        ) {
            return;
        }

        options.onMoveIdea(source.ideaId, column.stageId, [
            ...order,
            source.ideaId,
        ]);
    };

    const dropColumnOnColumn = (
        sourceStageId: string,
        target: DragData & { stageId: string },
    ): void => {
        const order = stageOrder();
        const startIndex = order.indexOf(sourceStageId);
        const targetIndex = order.indexOf(target.stageId);

        if (startIndex === -1 || targetIndex === -1) {
            return;
        }

        const edge = extractClosestEdge(target);
        const finishIndex = getReorderDestinationIndex({
            startIndex,
            indexOfTarget: targetIndex,
            closestEdgeOfTarget: edge,
            axis: 'horizontal',
        });

        if (finishIndex !== startIndex) {
            options.onReorderStages(
                reorder({ list: order, startIndex, finishIndex }),
            );
        }
    };

    const stopMonitor = monitorForElements({
        canMonitor: ({ source }) =>
            isCard(source.data) || isStageColumn(source.data),
        onDrop: ({ source, location }) => {
            clearIndicators();

            const target = location.current.dropTargets[0];

            if (!target) {
                return;
            }

            if (isCard(source.data)) {
                const item = {
                    ideaId: source.data.ideaId,
                    stageId: source.data.stageId,
                };

                if (isCard(target.data)) {
                    dropCardOnCard(item, target.data);
                } else if (isColumn(target.data)) {
                    dropCardOnColumn(item, target.data);
                }

                return;
            }

            if (isStageColumn(source.data) && isStageColumn(target.data)) {
                dropColumnOnColumn(source.data.stageId, target.data);
            }
        },
    });

    onBeforeUnmount(stopMonitor);

    const registerCard = (
        el: HTMLElement,
        item: IdeaBoardCardItem,
    ): (() => void) => {
        cards.set(el, item);

        const data = (): DragData => ({ [CARD_KEY]: true, ...item });

        const cleanup = combine(
            draggable({
                element: el,
                getInitialData: data,
                onDragStart: () => el.setAttribute('data-dragging', ''),
                onDrop: () => el.removeAttribute('data-dragging'),
            }),
            dropTargetForElements({
                element: el,
                canDrop: ({ source }) => isCard(source.data),
                getData: ({ input }) =>
                    attachClosestEdge(data(), {
                        element: el,
                        input,
                        allowedEdges: ['top', 'bottom'],
                    }),
                onDrag: ({ self, source }) => {
                    const edge = extractClosestEdge(self.data);

                    cardIndicator.value =
                        edge && isCard(source.data) && source.data.ideaId !== item.ideaId
                            ? {
                                  ideaId: item.ideaId,
                                  edge: edge === 'bottom' ? 'bottom' : 'top',
                              }
                            : null;
                },
                onDragLeave: () => {
                    if (cardIndicator.value?.ideaId === item.ideaId) {
                        cardIndicator.value = null;
                    }
                },
            }),
        );

        return () => {
            cards.delete(el);
            cleanup();
        };
    };

    const registerColumn = (
        el: HTMLElement,
        column: IdeaBoardColumnItem,
    ): (() => void) => {
        columns.set(el, column);

        const data = (): DragData => ({ [COLUMN_KEY]: true, ...column });

        const cleanup = dropTargetForElements({
            element: el,
            canDrop: ({ source }) =>
                isCard(source.data) ||
                (column.stageId !== null && isStageColumn(source.data)),
            getData: ({ input }) =>
                attachClosestEdge(data(), {
                    element: el,
                    input,
                    allowedEdges: ['left', 'right'],
                }),
            onDrag: ({ self, source, location }) => {
                if (isCard(source.data)) {
                    if (location.current.dropTargets[0]?.element === el) {
                        el.setAttribute(CARD_OVER_ATTRIBUTE, '');
                        cardIndicator.value = null;
                    } else {
                        el.removeAttribute(CARD_OVER_ATTRIBUTE);
                    }

                    return;
                }

                const edge = extractClosestEdge(self.data);

                columnIndicator.value =
                    edge &&
                    column.stageId !== null &&
                    isStageColumn(source.data) &&
                    source.data.stageId !== column.stageId
                        ? {
                              stageId: column.stageId,
                              edge: edge === 'right' ? 'right' : 'left',
                          }
                        : null;
            },
            onDragLeave: () => {
                el.removeAttribute(CARD_OVER_ATTRIBUTE);

                if (columnIndicator.value?.stageId === column.stageId) {
                    columnIndicator.value = null;
                }
            },
            onDrop: () => el.removeAttribute(CARD_OVER_ATTRIBUTE),
        });

        return () => {
            columns.delete(el);
            cleanup();
        };
    };

    const registerColumnHandle = (
        handle: HTMLElement,
        column: HTMLElement,
        item: { stageId: string },
    ): (() => void) =>
        draggable({
            element: column,
            dragHandle: handle,
            getInitialData: () => ({ [COLUMN_KEY]: true, ...item }),
            onDragStart: () => column.setAttribute('data-dragging', ''),
            onDrop: () => column.removeAttribute('data-dragging'),
        });

    return {
        registerCard,
        registerColumn,
        registerColumnHandle,
        cardIndicator,
        columnIndicator,
    };
};
