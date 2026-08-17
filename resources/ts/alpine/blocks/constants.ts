import { prefersReducedMotion } from '../../modules/dom';

export type WorkbenchOpts = {
    ticker: boolean;
    commercialBlocks: boolean;
    campaignAdd: boolean;
};

export const BLOCK_SORTABLE_OPTS = {
    handle: '.teksttv-block-handle',
    // Exclude the CSS-hidden empty state from sorting.
    draggable: '.teksttv-block',
    ghostClass: 'teksttv-sortable-ghost',
    dragClass: 'teksttv-sortable-drag',
    animation: prefersReducedMotion() ? 0 : 150,
} as const;
