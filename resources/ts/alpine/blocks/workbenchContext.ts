export type BlocksWorkbenchContext = {
    blocksEl: HTMLElement | null;
    reindexBlocks(): void;
    refreshSummaries(): void;
};
