import Sortable from 'sortablejs';
import { reindexNames, siblingFocusTarget } from '../../modules/dom';
import { initTomSelectIn } from '../../modules/tomSelect';
import { debounce } from '../../modules/utils';
import { BLOCK_SORTABLE_OPTS, type WorkbenchOpts } from './constants';
import { handleBlocksClick, removeClosestBlock, setBlockOpen, toggleBlockOpen } from './handleBlocksClick';
import { applySchedulingToggle } from './scheduling';
import { updateBlockSummaries } from './summaries';
import type { BlocksWorkbenchContext } from './workbenchContext';

/** Shared loop + campaigns blocks UI (spread into Alpine `x-data`; call `init` via `.call(this)`). */
export function createBlocksWorkbench(opts: WorkbenchOpts) {
    let blocksEl: HTMLElement | null = null;
    let tickerEl: HTMLElement | null = null;
    let groupsTbody: HTMLTableSectionElement | null = null;
    let newGroupSeq = 0;

    function reindexDisclosureIds(root: HTMLElement): void {
        // The id scheme must match the server-rendered ids (AdminPage /
        // CampaignsPage build them from the option prefix, which mirrors the
        // list root's id).
        root.querySelectorAll<HTMLElement>(':scope > .teksttv-block').forEach((block, index) => {
            const body = block.querySelector<HTMLElement>('.teksttv-block-body');
            const toggle = block.querySelector<HTMLButtonElement>('.teksttv-block-toggle-control');
            if (!(body && toggle)) return;
            const bodyId = `${root.id}-${index}-body`;
            body.id = bodyId;
            toggle.setAttribute('aria-controls', bodyId);
        });
    }

    function reindexBlocks(): void {
        if (!blocksEl) return;
        reindexNames(blocksEl, ':scope > .teksttv-block', /(teksttv_(?:blocks|campaigns))\[\d+\]/);
        reindexDisclosureIds(blocksEl);
    }

    function reindexTicker(): void {
        if (!tickerEl) return;
        reindexNames(tickerEl, ':scope > .teksttv-block', /(teksttv_ticker)\[\d+\]/);
        reindexDisclosureIds(tickerEl);
    }

    function refreshSummaries(): void {
        if (blocksEl) updateBlockSummaries(blocksEl);
        if (tickerEl) updateBlockSummaries(tickerEl);
    }

    const scheduleSummaries = debounce(refreshSummaries, 150);

    /** Insert a block from a template, expand it, and optionally focus its first text input. */
    function insertBlockFromTemplate(
        root: HTMLElement,
        templateId: string,
        options: { focusText?: boolean } = {},
    ): void {
        const reindex = root === tickerEl ? reindexTicker : root === blocksEl ? reindexBlocks : null;
        if (!reindex) return;
        const template = document.getElementById(templateId);
        if (!(template instanceof HTMLTemplateElement)) return;
        const newBlock = template.content.firstElementChild?.cloneNode(true);
        if (!(newBlock instanceof HTMLElement)) return;
        root.append(newBlock);
        reindex();
        if (root === blocksEl) {
            // Only the blocks list renders an empty-state; a ticker insert must not remove it.
            document.querySelector('#teksttv-empty-state')?.remove();
        }
        setBlockOpen(newBlock, true, false);
        // A no-op for templates without .teksttv-tomselect fields — the
        // class on the rendered fields is the declaration.
        initTomSelectIn(newBlock);
        const focusTarget = options.focusText
            ? newBlock.querySelector<HTMLInputElement>('input[type="text"]')
            : newBlock.querySelector<HTMLButtonElement>('.teksttv-block-toggle-control');
        focusTarget?.focus();
        refreshSummaries();
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
            }

            refreshSummaries();

            if (opts.groups) {
                groupsTbody = document.querySelector('#teksttv-groups')?.querySelector('tbody') ?? null;
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

        expandAllBlocks(): void {
            if (!blocksEl) return;
            blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((block) => {
                if (!(block instanceof HTMLElement)) return;
                setBlockOpen(block, true);
            });
        },

        collapseAllBlocks(): void {
            if (!blocksEl) return;
            blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((block) => {
                if (!(block instanceof HTMLElement)) return;
                setBlockOpen(block, false);
            });
        },

        blocksClick(e: MouseEvent): void {
            handleBlocksClick(e, clickCtx);
        },

        blocksFieldChange(e: Event): void {
            handleFieldChange(blocksEl, e);
        },

        tickerClick(e: MouseEvent): void {
            if (!(e.target instanceof Element) || !tickerEl) return;

            const rem = e.target.closest('.teksttv-remove-block');
            if (rem && tickerEl.contains(rem)) {
                e.stopPropagation();
                removeClosestBlock(rem, () => {
                    reindexTicker();
                    refreshSummaries();
                });
                return;
            }

            const toggle = e.target.closest('.teksttv-block-toggle-control');
            if (toggle && tickerEl.contains(toggle)) {
                toggleBlockOpen(toggle);
            }
        },

        tickerFieldChange(e: Event): void {
            handleFieldChange(tickerEl, e);
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
                '<td class="teksttv-table-actions"><button type="button" class="button-link teksttv-remove-group" aria-label="Groep verwijderen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>' +
                '</tr>';
            groupsTbody.insertAdjacentHTML('beforeend', row);
            groupsTbody
                .querySelector<HTMLInputElement>(':scope > .teksttv-group-row:last-of-type input[name$="[label]"]')
                ?.focus();
        },

        groupsClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const tgt = e.target.closest('.teksttv-remove-group');
            if (!(tgt instanceof HTMLElement) || !groupsTbody?.contains(tgt)) return;
            const row = tgt.closest('tr');
            if (!row) return;
            const focusTarget = siblingFocusTarget(
                row,
                'input[name$="[label]"]',
                document.querySelector<HTMLElement>('#teksttv-add-group'),
            );
            row.remove();
            focusTarget?.focus();
        },
    };
}
