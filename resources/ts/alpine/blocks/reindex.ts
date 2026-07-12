import { reindexNames } from '../../modules/dom';

/** Rewrite `teksttv_blocks[N]` / `teksttv_campaigns[N]` indices after reorder or delete. */
export function reindexBlocks(blocksEl: HTMLElement): void {
    reindexNames(blocksEl, ':scope > .teksttv-block', /(teksttv_(?:blocks|campaigns))\[\d+\]/);
}

/** Rewrite `teksttv_ticker[N]` after ticker reorder/delete. */
export function reindexTicker(tickerEl: HTMLElement): void {
    reindexNames(tickerEl, ':scope > .teksttv-block', /(teksttv_ticker)\[\d+\]/);
}
