/** Loop / campaigns block workbench (+ optional ticker Sortable). */
export type WorkbenchOpts = {
    ticker: boolean;
    groups: boolean;
    campaignAdd: boolean;
};

export const BLOCK_SORTABLE_OPTS = {
    handle: '.teksttv-block-handle',
    // The always-rendered (CSS-hidden) empty state lives inside the list root
    // and must never count as a sortable item.
    draggable: '.teksttv-block',
    ghostClass: 'teksttv-sortable-ghost',
    dragClass: 'teksttv-sortable-drag',
    animation: 150,
} as const;
