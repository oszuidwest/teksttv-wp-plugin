import Sortable from 'sortablejs';
import { markFormDirty } from '../../modules/dirtyForms';
import { reindexNames, siblingFocusTarget } from '../../modules/dom';
import { initTomSelectIn } from '../../modules/tomSelect';
import { debounce } from '../../modules/utils';
import { BLOCK_SORTABLE_OPTS, type WorkbenchOpts } from './constants';
import {
    handleBlocksClick,
    moveClosestBlock,
    removeClosestBlock,
    setBlockOpen,
    toggleBlockOpen,
    updateBlockOrderControls,
} from './handleBlocksClick';
import { applySchedulingToggle } from './scheduling';
import { updateBlockSummaries } from './summaries';
import type { BlocksWorkbenchContext } from './workbenchContext';

/** Shared loop + campaigns blocks UI (spread into Alpine `x-data`; call `init` via `.call(this)`). */
export function createBlocksWorkbench(opts: WorkbenchOpts) {
    let blocksEl: HTMLElement | null = null;
    let tickerEl: HTMLElement | null = null;
    let groupsTbody: HTMLTableSectionElement | null = null;
    let newGroupSeq = 0;

    function ensureEmptyState(root: HTMLElement): void {
        const emptyState = root.querySelector<HTMLElement>(':scope > .teksttv-empty-state');
        if (root.querySelector(':scope > .teksttv-block')) {
            emptyState?.remove();
            return;
        }
        if (emptyState || !root.dataset.emptyText) return;

        const state = document.createElement('div');
        state.className = 'teksttv-empty-state';
        const icon = document.createElement('span');
        icon.className = `dashicons dashicons-${root.dataset.emptyIcon || 'info-outline'}`;
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('p');
        text.textContent = root.dataset.emptyText;
        state.append(icon, text);
        root.append(state);
    }

    function ensureGroupEmptyState(): void {
        if (!groupsTbody) return;
        const emptyRow = groupsTbody.querySelector('.teksttv-table-empty');
        if (groupsTbody.querySelector('.teksttv-group-row')) {
            emptyRow?.remove();
            return;
        }
        if (emptyRow) return;

        const row = document.createElement('tr');
        row.className = 'teksttv-table-empty';
        const cell = document.createElement('td');
        cell.colSpan = 2;
        cell.textContent = 'Nog geen groepen. Voeg een groep toe om campagnes te ordenen.';
        row.append(cell);
        groupsTbody.append(row);
    }

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

    function reindexFieldIds(root: HTMLElement): void {
        root.querySelectorAll<HTMLElement>(':scope > .teksttv-block').forEach((block, index) => {
            block.querySelectorAll<HTMLElement>('[data-teksttv-field]').forEach((control) => {
                const key = control.dataset.teksttvField;
                if (!key) return;
                const id = `${root.id}-${index}-${key}`;
                control.id = id;

                // Tom Select moves label focus to its generated text input.
                // Keep that id in step when a block is reordered or removed.
                const tomSelect = (
                    control as HTMLElement & {
                        tomselect?: { control_input?: HTMLInputElement };
                    }
                ).tomselect;
                if (tomSelect?.control_input) tomSelect.control_input.id = `${id}-ts-control`;
            });
            block.querySelectorAll<HTMLLabelElement>('[data-teksttv-label]').forEach((label) => {
                const key = label.dataset.teksttvLabel;
                if (!key) return;
                const control = block.querySelector<HTMLElement & { tomselect?: { control_input?: HTMLInputElement } }>(
                    `[data-teksttv-field="${key}"]`,
                );
                label.htmlFor = control?.tomselect?.control_input?.id ?? `${root.id}-${index}-${key}`;
            });
        });
    }

    function reindexBlocks(): void {
        if (!blocksEl) return;
        reindexNames(blocksEl, ':scope > .teksttv-block', /(teksttv_(?:blocks|campaigns))\[\d+\]/);
        reindexDisclosureIds(blocksEl);
        reindexFieldIds(blocksEl);
        updateBlockOrderControls(blocksEl);
        ensureEmptyState(blocksEl);
    }

    function reindexTicker(): void {
        if (!tickerEl) return;
        reindexNames(tickerEl, ':scope > .teksttv-block', /(teksttv_ticker)\[\d+\]/);
        reindexDisclosureIds(tickerEl);
        reindexFieldIds(tickerEl);
        updateBlockOrderControls(tickerEl);
        ensureEmptyState(tickerEl);
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
        setBlockOpen(newBlock, true, false);
        // A no-op for templates without .teksttv-tomselect fields — the
        // class on the rendered fields is the declaration.
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

    return {
        menuBlockOpen: false,
        menuTickerOpen: false,

        init(): void {
            blocksEl = document.querySelector<HTMLElement>('#teksttv-blocks, #teksttv-campaigns');
            if (!blocksEl) return;

            new Sortable(blocksEl, {
                ...BLOCK_SORTABLE_OPTS,
                onEnd: (evt) => {
                    if (evt.oldIndex !== evt.newIndex) {
                        reindexBlocks();
                        markFormDirty(blocksEl as HTMLElement);
                    }
                },
            });

            tickerEl = document.querySelector<HTMLElement>('#teksttv-ticker');
            if (opts.ticker && tickerEl) {
                new Sortable(tickerEl, {
                    ...BLOCK_SORTABLE_OPTS,
                    onEnd: (evt) => {
                        if (evt.oldIndex !== evt.newIndex) {
                            reindexTicker();
                            markFormDirty(tickerEl as HTMLElement);
                        }
                    },
                });
            }

            refreshSummaries();
            updateBlockOrderControls(blocksEl);
            ensureEmptyState(blocksEl);
            if (tickerEl) updateBlockOrderControls(tickerEl);
            if (tickerEl) ensureEmptyState(tickerEl);

            if (opts.groups) {
                groupsTbody = document.querySelector('#teksttv-groups')?.querySelector('tbody') ?? null;
                ensureGroupEmptyState();
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

            const moveUp = e.target.closest('.teksttv-move-block-up');
            if (moveUp && tickerEl.contains(moveUp)) {
                moveClosestBlock(moveUp, -1, () => {
                    reindexTicker();
                    refreshSummaries();
                });
                return;
            }

            const moveDown = e.target.closest('.teksttv-move-block-down');
            if (moveDown && tickerEl.contains(moveDown)) {
                moveClosestBlock(moveDown, 1, () => {
                    reindexTicker();
                    refreshSummaries();
                });
                return;
            }

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
            groupsTbody.querySelector('.teksttv-table-empty')?.remove();
            // New rows have an empty id; the server derives a stable id from the
            // label on save. The index only needs to be unique within the form.
            const key = `new-${newGroupSeq++}`;
            const row =
                '<tr class="teksttv-group-row">' +
                '<td>' +
                `<input type="hidden" name="teksttv_campaign_groups[${key}][id]" value="" />` +
                `<label class="teksttv-mobile-field-label" for="teksttv-group-${key}-label">Naam</label>` +
                `<input type="text" id="teksttv-group-${key}-label" name="teksttv_campaign_groups[${key}][label]" value="" class="regular-text" required placeholder="bijv. Campagne" autocomplete="off" />` +
                '</td>' +
                '<td class="teksttv-table-actions"><button type="button" class="button-link button-link-delete teksttv-remove-group" aria-label="Groep verwijderen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>' +
                '</tr>';
            groupsTbody.insertAdjacentHTML('beforeend', row);
            markFormDirty(groupsTbody);
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
            ensureGroupEmptyState();
            markFormDirty(groupsTbody);
            focusTarget?.focus();
        },
    };
}
