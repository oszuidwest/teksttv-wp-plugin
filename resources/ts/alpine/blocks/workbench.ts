import Sortable from 'sortablejs';
import { hide, show, slideDown, slideToggle, slideUp, tmplHtml } from '../../modules/dom';
import { initTomSelectIn } from '../../modules/utils';
import { BLOCK_SORTABLE_OPTS, type WorkbenchOpts } from './constants';
import { handleBlocksClick } from './handleBlocksClick';
import { reindexBlocks as reindexBlocksDom, reindexTicker as reindexTickerDom } from './reindex';
import { applySchedulingToggle } from './scheduling';
import { updateBlockSummaries } from './summaries';
import type { BlocksWorkbenchContext } from './workbenchContext';

/** Shared loop + campaigns blocks UI (spread into Alpine `x-data`; call `init` via `.call(this)`). */
export function createBlocksWorkbench(opts: WorkbenchOpts) {
    let blocksEl: HTMLElement | null = null;
    let tickerEl: HTMLElement | null = null;
    let groupsTbody: HTMLTableSectionElement | null = null;
    let newGroupSeq = 0;

    function reindexBlocks(): void {
        if (!blocksEl) return;
        reindexBlocksDom(blocksEl);
    }

    function reindexTicker(): void {
        if (!tickerEl) return;
        reindexTickerDom(tickerEl);
    }

    function refreshSummaries(): void {
        if (!blocksEl) return;
        updateBlockSummaries(blocksEl);
    }

    const clickCtx: BlocksWorkbenchContext = {
        get blocksEl() {
            return blocksEl;
        },
        reindexBlocks,
        refreshSummaries,
    };

    return {
        menuBlockOpen: false,
        menuTickerOpen: false,

        init(): void {
            blocksEl = document.querySelector<HTMLElement>('#teksttv-blocks, #teksttv-campaigns');
            if (!blocksEl) return;

            new Sortable(blocksEl, {
                ...BLOCK_SORTABLE_OPTS,
                onEnd: (evt) => {
                    if (evt.oldIndex !== evt.newIndex) reindexBlocks();
                },
            });

            tickerEl = document.querySelector<HTMLElement>('#teksttv-ticker');
            if (opts.ticker && tickerEl) {
                new Sortable(tickerEl, {
                    ...BLOCK_SORTABLE_OPTS,
                    onEnd: (evt) => {
                        if (evt.oldIndex !== evt.newIndex) reindexTicker();
                    },
                });
                tickerEl.querySelectorAll(':scope > .teksttv-block').forEach((block) => {
                    const body = block.querySelector<HTMLElement>('.teksttv-block-body');
                    if (body) hide(body);
                });
            }

            refreshSummaries();
            blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((block) => {
                const body = block.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) hide(body);
            });

            if (opts.groups) {
                groupsTbody = document.querySelector('#teksttv-groups')?.querySelector('tbody') ?? null;
            }
        },

        addLoopBlock(type: string): void {
            if (!blocksEl) return;
            document.querySelector('#teksttv-empty-state')?.remove();
            const templateHtml = tmplHtml(`tmpl-teksttv-block-${type}`);
            if (!templateHtml) return;
            const index = blocksEl.querySelectorAll(':scope > .teksttv-block').length;
            const rendered = templateHtml.replace(/__INDEX__/g, String(index));
            blocksEl.insertAdjacentHTML('beforeend', rendered);
            const newBlock = blocksEl.querySelector(':scope > .teksttv-block:last-of-type');
            refreshSummaries();
            if (newBlock instanceof HTMLElement) {
                const body = newBlock.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) show(body);
                newBlock.classList.add('is-expanded');
                initTomSelectIn(newBlock);
            }
            this.menuBlockOpen = false;
        },

        addCampaignBlock(): void {
            if (!blocksEl || !opts.campaignAdd) return;
            document.querySelector('#teksttv-empty-state')?.remove();
            const html = tmplHtml('tmpl-teksttv-campaign');
            if (!html) return;
            const index = blocksEl.querySelectorAll(':scope > .teksttv-block').length;
            blocksEl.insertAdjacentHTML('beforeend', html.replace(/__INDEX__/g, String(index)));
            const newBlock = blocksEl.querySelector(':scope > .teksttv-block:last-of-type');
            if (newBlock instanceof HTMLElement) {
                const body = newBlock.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) show(body);
                newBlock.classList.add('is-expanded');
            }
            refreshSummaries();
        },

        addTickerBlock(type: string): void {
            if (!(opts.ticker && tickerEl)) return;
            const root = tickerEl;
            const html = tmplHtml(`tmpl-teksttv-ticker-${type}`);
            if (!html) return;
            const index = root.querySelectorAll(':scope > .teksttv-block').length;
            root.insertAdjacentHTML('beforeend', html.replace(/__TINDEX__/g, String(index)));
            const newBlock = root.querySelector(':scope > .teksttv-block:last-of-type');
            if (newBlock instanceof HTMLElement) {
                const body = newBlock.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) show(body);
                newBlock.classList.add('is-expanded');
                initTomSelectIn(newBlock);
                newBlock.querySelector<HTMLInputElement>('input[type="text"]')?.focus();
            }
            this.menuTickerOpen = false;
        },

        expandAllBlocks(): void {
            if (!blocksEl) return;
            blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((block) => {
                if (!(block instanceof HTMLElement)) return;
                block.classList.add('is-expanded');
                const body = block.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) slideDown(body, 150);
            });
        },

        collapseAllBlocks(): void {
            if (!blocksEl) return;
            blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((block) => {
                if (!(block instanceof HTMLElement)) return;
                block.classList.remove('is-expanded');
                const body = block.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) slideUp(body, 150);
            });
        },

        blocksClick(e: MouseEvent): void {
            handleBlocksClick(e, clickCtx);
        },

        blocksFieldChange(e: Event): void {
            const t = e.target;
            if (!(t instanceof HTMLElement) || !blocksEl?.contains(t)) return;
            if (t instanceof HTMLInputElement && t.matches('.teksttv-scheduling-checkbox')) {
                applySchedulingToggle(t);
            }
            if (t.closest('.teksttv-block-body')) {
                refreshSummaries();
            }
        },

        tickerClick(e: MouseEvent): void {
            if (!(e.target instanceof Element) || !tickerEl) return;

            const header = e.target.closest('.teksttv-block-header');
            if (header && tickerEl.contains(header)) {
                if (e.target.closest('.teksttv-remove-block')) return;
                const block = header.closest('.teksttv-block');
                if (!(block instanceof HTMLElement)) return;
                block.classList.toggle('is-expanded');
                const body = block.querySelector<HTMLElement>('.teksttv-block-body');
                if (body) slideToggle(body, 150);
                return;
            }

            const rem = e.target.closest('.teksttv-remove-block');
            if (rem && tickerEl.contains(rem)) {
                e.stopPropagation();
                const block = rem.closest('.teksttv-block');
                if (!(block instanceof HTMLElement)) return;
                slideUp(block, 200, () => {
                    block.remove();
                    reindexTicker();
                });
            }
        },

        tickerFieldChange(e: Event): void {
            const t = e.target;
            if (!(t instanceof HTMLElement) || !tickerEl?.contains(t)) return;
            if (t instanceof HTMLInputElement && t.matches('.teksttv-scheduling-checkbox')) {
                applySchedulingToggle(t);
            }
        },

        addGroupRow(): void {
            if (!groupsTbody) return;
            // New rows have an empty id; the server derives a stable id from the
            // label on save. The index only needs to be unique within the form.
            const key = `new-${newGroupSeq++}`;
            const row =
                '<tr class="teksttv-group-row">' +
                '<td>' +
                `<input type="hidden" name="teksttv_campaign_groups[${key}][id]" value="" />` +
                `<input type="text" name="teksttv_campaign_groups[${key}][label]" value="" class="regular-text" required placeholder="Bijv. Campagne" />` +
                '</td>' +
                '<td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-group"><span class="dashicons dashicons-trash"></span></button></td>' +
                '</tr>';
            groupsTbody.insertAdjacentHTML('beforeend', row);
        },

        groupsClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const tgt = e.target.closest('.teksttv-remove-group');
            if (!(tgt instanceof HTMLElement) || !groupsTbody?.contains(tgt)) return;
            tgt.closest('tr')?.remove();
        },
    };
}
