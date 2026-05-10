/** Mutable roots + callbacks passed into delegated event helpers (narrow surface for tests / reuse). */
export type BlocksWorkbenchContext = {
    blocksEl: HTMLElement | null;
    reindexBlocks(): void;
    refreshSummaries(): void;
};
