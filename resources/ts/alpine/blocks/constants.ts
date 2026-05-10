/** Loop / campaigns block workbench (+ optional ticker Sortable). */
export type WorkbenchOpts = {
    ticker: boolean;
    groups: boolean;
    campaignAdd: boolean;
};

export const BLOCK_SORTABLE_OPTS = {
    handle: '.teksttv-block-handle',
    ghostClass: 'teksttv-sortable-ghost',
    dragClass: 'teksttv-sortable-drag',
    animation: 150,
} as const;
