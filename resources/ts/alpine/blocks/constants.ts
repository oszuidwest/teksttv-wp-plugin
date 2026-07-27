/** Which parts of the shared workbench a page uses: the loop page has a ticker, campaigns have groups. */
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
