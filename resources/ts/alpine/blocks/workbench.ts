import Sortable from 'sortablejs';
import { markFormDirty } from '../../modules/dirtyForms';
import { cloneTemplate, reindexNames, siblingFocusTarget } from '../../modules/dom';
import { initTomSelectIn } from '../../modules/tomSelect';
import { removeElementWithUndo } from '../../modules/undo';
import { debounce } from '../../modules/utils';
import { BLOCK_SORTABLE_OPTS, type WorkbenchOpts } from './constants';
import { handleBlockControlsClick, handleBlocksClick, initDisclosureMenus, setBlockOpen } from './handleBlocksClick';
import { applySchedulingToggle } from './scheduling';
import { updateBlockSummaries } from './summaries';
import type { BlocksWorkbenchContext } from './workbenchContext';

export function createBlocksWorkbench(opts: WorkbenchOpts) {
    let blocksEl: HTMLElement | null = null;
    let tickerEl: HTMLElement | null = null;
    let commercialBlocksTbody: HTMLTableSectionElement | null = null;

    function reindexBlockUi(block: Element, index: number, total: number): void {
        const root = block.parentElement;
        const body = block.querySelector<HTMLElement>('.teksttv-block-body');
        const toggle = block.querySelector<HTMLButtonElement>('.teksttv-block-toggle-control');
        if (root && body && toggle) {
            const bodyId = `${root.id}-${index}-body`;
            body.id = bodyId;
            toggle.setAttribute('aria-controls', bodyId);
        }
        const up = block.querySelector<HTMLButtonElement>('.teksttv-move-block-up');
        const down = block.querySelector<HTMLButtonElement>('.teksttv-move-block-down');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === total - 1;
    }

    function reindexBlocks(): void {
        if (!blocksEl) return;
        reindexNames(blocksEl, ':scope > .teksttv-block', /(teksttv_(?:blocks|campaigns))\[\d+\]/, reindexBlockUi);
    }

    function reindexTicker(): void {
        if (!tickerEl) return;
        reindexNames(tickerEl, ':scope > .teksttv-block', /(teksttv_ticker)\[\d+\]/, reindexBlockUi);
    }

    // Only user-driven mutations reach this helper.
    function reindexCommercialBlocks(): void {
        if (!commercialBlocksTbody) return;
        reindexNames(commercialBlocksTbody, '.teksttv-commercial-block-row', /(teksttv_commercial_blocks)\[\d+\]/);
        markFormDirty(commercialBlocksTbody);
    }

    function refreshSummaries(): void {
        if (blocksEl) updateBlockSummaries(blocksEl);
        if (tickerEl) updateBlockSummaries(tickerEl);
    }

    const scheduleSummaries = debounce(refreshSummaries, 150);

    function insertBlockFromTemplate(
        root: HTMLElement,
        templateId: string,
        options: { focusText?: boolean } = {},
    ): void {
        const reindex = root === tickerEl ? reindexTicker : root === blocksEl ? reindexBlocks : null;
        if (!reindex) return;
        const newBlock = cloneTemplate(templateId);
        if (!newBlock) return;
        root.append(newBlock);
        reindex();
        setBlockOpen(newBlock, true, false);
        // Templates opt in with .teksttv-tomselect.
        initTomSelectIn(newBlock);
        const focusTarget = options.focusText
            ? newBlock.querySelector<HTMLInputElement>('input[type="text"]')
            : newBlock.querySelector<HTMLButtonElement>('.teksttv-block-toggle-control');
        focusTarget?.focus();
        refreshSummaries();
        markFormDirty(root);
    }

    const clickCtx: BlocksWorkbenchContext = {
        get blocksEl() {
            return blocksEl;
        },
        reindexBlocks,
        refreshSummaries,
    };

    function handleFieldChange(root: HTMLElement | null, e: Event): void {
        const t = e.target;
        if (!(t instanceof HTMLElement) || !root?.contains(t)) return;
        if (e.type === 'change' && t instanceof HTMLInputElement && t.matches('.teksttv-scheduling-checkbox')) {
            applySchedulingToggle(t);
        }
        if (t.closest('.teksttv-block-body')) {
            scheduleSummaries();
        }
    }

    function initSortable(root: HTMLElement, reindex: () => void): void {
        new Sortable(root, {
            ...BLOCK_SORTABLE_OPTS,
            onEnd: ({ oldIndex, newIndex }) => {
                if (oldIndex === newIndex) return;
                reindex();
                markFormDirty(root);
            },
        });
    }

    function setAllOpen(root: HTMLElement | null, expanded: boolean): void {
        root?.querySelectorAll<HTMLElement>(':scope > .teksttv-block').forEach((block) => {
            setBlockOpen(block, expanded);
        });
    }

    return {
        init(): void {
            blocksEl = document.querySelector<HTMLElement>('#teksttv-blocks, #teksttv-campaigns');
            if (!blocksEl) return;

            initSortable(blocksEl, reindexBlocks);
            initDisclosureMenus(blocksEl.closest('form') ?? blocksEl);

            tickerEl = document.querySelector<HTMLElement>('#teksttv-ticker');
            if (opts.ticker && tickerEl) {
                initSortable(tickerEl, reindexTicker);
            }

            refreshSummaries();
            reindexBlocks();
            if (tickerEl) reindexTicker();

            if (opts.commercialBlocks) {
                commercialBlocksTbody =
                    document.querySelector('#teksttv-commercial-blocks')?.querySelector('tbody') ?? null;
            }
        },

        addLoopBlock(type: string): void {
            if (!blocksEl) return;
            insertBlockFromTemplate(blocksEl, `tmpl-teksttv-block-${type}`);
        },

        addCampaignBlock(): void {
            if (!blocksEl || !opts.campaignAdd) return;
            insertBlockFromTemplate(blocksEl, 'tmpl-teksttv-campaign');
        },

        addTickerBlock(type: string): void {
            if (!(opts.ticker && tickerEl)) return;
            insertBlockFromTemplate(tickerEl, `tmpl-teksttv-ticker-${type}`, { focusText: true });
        },

        setAllBlocksOpen(expanded: boolean): void {
            setAllOpen(blocksEl, expanded);
        },

        setAllTickerOpen(expanded: boolean): void {
            setAllOpen(tickerEl, expanded);
        },

        blocksClick(e: MouseEvent): void {
            handleBlocksClick(e, clickCtx);
        },

        blocksFieldChange(e: Event): void {
            handleFieldChange(blocksEl, e);
        },

        tickerClick(e: MouseEvent): void {
            if (tickerEl) handleBlockControlsClick(e, tickerEl, reindexTicker);
        },

        tickerFieldChange(e: Event): void {
            handleFieldChange(tickerEl, e);
        },

        addCommercialBlockRow(): void {
            if (!commercialBlocksTbody) return;
            // The server derives stable IDs; reindexing keeps form keys unique.
            const row = cloneTemplate('tmpl-teksttv-commercial-block-row');
            if (!row) return;
            commercialBlocksTbody.append(row);
            reindexCommercialBlocks();
            row.querySelector<HTMLInputElement>('input[name$="[label]"]')?.focus();
        },

        commercialBlocksClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const tgt = e.target.closest('.teksttv-remove-commercial-block');
            if (!(tgt instanceof HTMLElement) || !commercialBlocksTbody?.contains(tgt)) return;
            const row = tgt.closest('tr');
            if (!row) return;
            const focusTarget = siblingFocusTarget(
                row,
                'input[name$="[label]"]',
                document.querySelector<HTMLElement>('#teksttv-add-commercial-block'),
            );
            removeElementWithUndo(row, {
                message: 'Reclameblok verwijderd.',
                focusAfterRemove: focusTarget,
                focusAfterRestore: (restored) => restored.querySelector('input[name$="[label]"]'),
                focusUndo: e.detail === 0,
                onChange: reindexCommercialBlocks,
            });
        },
    };
}
