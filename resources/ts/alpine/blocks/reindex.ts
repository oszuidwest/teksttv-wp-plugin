/** Rewrite `teksttv_blocks[N]` / `teksttv_campaigns[N]` indices after reorder or delete. */
export function reindexBlocks(blocksEl: HTMLElement): void {
    blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((block, i) => {
        block.querySelectorAll('input, select').forEach((input) => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/(teksttv_(?:blocks|campaigns))\[\d+\]/, `$1[${i}]`));
            }
        });
    });
}

/** Rewrite `teksttv_ticker[N]` after ticker reorder/delete. */
export function reindexTicker(tickerEl: HTMLElement): void {
    tickerEl.querySelectorAll(':scope > .teksttv-block').forEach((block, i) => {
        block.querySelectorAll('input, select').forEach((input) => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/teksttv_ticker\[\d+\]/, `teksttv_ticker[${i}]`));
            }
        });
    });
}
