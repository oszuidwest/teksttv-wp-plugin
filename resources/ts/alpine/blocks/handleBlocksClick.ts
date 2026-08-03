import { markFormDirty } from '../../modules/dirtyForms';
import { cancelSlideAnimation, hide, show, siblingFocusTarget, slideDown, slideUp } from '../../modules/dom';
import { appendImageItems, removeImageItem } from '../../modules/utils';
import { pickImages, pickSingleImage } from '../../modules/wpMedia';
import type { BlocksWorkbenchContext } from './workbenchContext';

/** Set one block accordion state and keep its disclosure button in sync. */
export function setBlockOpen(block: HTMLElement, expanded: boolean, animate = true): void {
    const wasExpanded = block.classList.contains('is-expanded');
    block.classList.toggle('is-expanded', expanded);
    block
        .querySelector<HTMLButtonElement>('.teksttv-block-toggle-control')
        ?.setAttribute('aria-expanded', String(expanded));

    const body = block.querySelector<HTMLElement>('.teksttv-block-body');
    if (!body) return;
    if (!animate) {
        cancelSlideAnimation(body);
        if (expanded) show(body);
        else hide(body);
        return;
    }
    if (wasExpanded === expanded) return;
    if (expanded) slideDown(body, 150);
    else slideUp(body, 150);
}

/** Toggle the accordion body of the block owning `trigger`. */
export function toggleBlockOpen(trigger: Element): void {
    const block = trigger.closest('.teksttv-block');
    if (!(block instanceof HTMLElement)) return;
    setBlockOpen(block, !block.classList.contains('is-expanded'));
}

/** Slide up and remove the block owning `trigger`, then run `onRemoved`. */
export function removeClosestBlock(trigger: Element, onRemoved: () => void): void {
    const block = trigger.closest('.teksttv-block');
    if (!(block instanceof HTMLElement)) return;
    // The list root declares where focus goes when its last block is removed.
    const emptyFocus = block.parentElement?.dataset.emptyFocus;
    const focusTarget = siblingFocusTarget(
        block,
        '.teksttv-block-toggle-control',
        emptyFocus ? document.querySelector<HTMLElement>(emptyFocus) : null,
    );
    // Fire while the block is still connected so the event reaches the form,
    // including when this is the last block in the list.
    markFormDirty(block);

    block
        .querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea')
        .forEach((control) => {
            control.disabled = true;
        });
    slideUp(block, 200, () => {
        block.remove();
        onRemoved();
        focusTarget?.focus();
    });
}

/** Keep the first/last keyboard reorder buttons in sync with list position. */
export function updateBlockOrderControls(root: HTMLElement): void {
    const blocks = Array.from(root.querySelectorAll<HTMLElement>(':scope > .teksttv-block'));
    blocks.forEach((block, index) => {
        const up = block.querySelector<HTMLButtonElement>('.teksttv-move-block-up');
        const down = block.querySelector<HTMLButtonElement>('.teksttv-move-block-down');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === blocks.length - 1;
    });
}

/** Move a block one position for keyboard and switch-control users. */
export function moveClosestBlock(trigger: Element, direction: -1 | 1, onMoved: () => void): void {
    const block = trigger.closest<HTMLElement>('.teksttv-block');
    const root = block?.parentElement;
    if (!block || !root) return;
    const sibling = direction < 0 ? block.previousElementSibling : block.nextElementSibling;
    if (!(sibling instanceof HTMLElement) || !sibling.classList.contains('teksttv-block')) return;

    if (direction < 0) root.insertBefore(block, sibling);
    else root.insertBefore(sibling, block);
    onMoved();
    markFormDirty(block);
    if (trigger instanceof HTMLElement) trigger.focus();
}

/**
 * Shared block-header controls: keyboard reorder, remove, accordion toggle.
 * Returns true when the click was one of them. Summaries are derived from a
 * block's own fields, so reordering or removing never changes them.
 */
export function handleBlockControlsClick(e: MouseEvent, root: HTMLElement, reindex: () => void): boolean {
    if (!(e.target instanceof Element)) return false;

    const move = e.target.closest('.teksttv-block-order-control');
    if (move && root.contains(move)) {
        moveClosestBlock(move, move.matches('.teksttv-move-block-up') ? -1 : 1, reindex);
        return true;
    }

    const rem = e.target.closest('.teksttv-remove-block');
    if (rem && root.contains(rem)) {
        e.stopPropagation();
        removeClosestBlock(rem, reindex);
        return true;
    }

    const toggle = e.target.closest('.teksttv-block-toggle-control');
    if (toggle && root.contains(toggle)) {
        toggleBlockOpen(toggle);
        return true;
    }

    return false;
}

/**
 * Delegated `#teksttv-blocks` / `#teksttv-campaigns` clicks: remove, accordion, campaign slides, image pickers.
 * Keeps `workbench.ts` readable; context holds DOM roots and refresh helpers.
 */
export function handleBlocksClick(e: MouseEvent, ctx: BlocksWorkbenchContext): void {
    if (!(e.target instanceof Element) || !ctx.blocksEl) return;
    const blocksRoot = ctx.blocksEl;

    if (handleBlockControlsClick(e, blocksRoot, ctx.reindexBlocks)) return;

    const slidesBtn = e.target.closest('.teksttv-campaign-add-slides');
    if (slidesBtn && blocksRoot.contains(slidesBtn)) {
        e.preventDefault();
        const section = slidesBtn.closest('.teksttv-campaign-slides-section');
        const list = section?.querySelector<HTMLElement>('.teksttv-campaign-slides');
        const baseName = list?.dataset.name;
        if (!list || !baseName) return;
        pickImages((attachments) => {
            appendImageItems(list, attachments, baseName);
            markFormDirty(list);
        });
        return;
    }

    const imgItemRm = e.target.closest('.teksttv-remove-image');
    if (imgItemRm && blocksRoot.contains(imgItemRm)) {
        e.preventDefault();
        removeImageItem(imgItemRm);
        markFormDirty(blocksRoot);
        return;
    }

    const imgSel = e.target.closest('.teksttv-block-image-select');
    if (imgSel && blocksRoot.contains(imgSel)) {
        e.preventDefault();
        const picker = imgSel.closest('.teksttv-image-picker');
        if (!picker) return;
        pickSingleImage((att) => {
            const url = att.sizes?.medium?.url ?? att.url;
            const idInput = picker.querySelector<HTMLInputElement>('.teksttv-block-image-id');
            const thumb = picker.querySelector<HTMLImageElement>('.teksttv-block-image-thumb');
            const previewBox = picker.querySelector<HTMLElement>('.teksttv-block-image-preview');
            const removeBtn = picker.querySelector<HTMLElement>('.teksttv-block-image-remove');
            if (idInput) idInput.value = String(att.id);
            if (thumb) thumb.src = url;
            previewBox?.classList.remove('is-hidden');
            removeBtn?.classList.remove('is-hidden');
            ctx.refreshSummaries();
            markFormDirty(picker);
        });
        return;
    }

    const imgRm = e.target.closest('.teksttv-block-image-remove');
    if (imgRm && blocksRoot.contains(imgRm)) {
        const picker = imgRm.closest('.teksttv-image-picker');
        if (!picker) return;
        const hid = picker.querySelector<HTMLInputElement>('.teksttv-block-image-id');
        if (hid) hid.value = '';
        const thumb = picker.querySelector<HTMLImageElement>('.teksttv-block-image-thumb');
        if (thumb) thumb.removeAttribute('src');
        picker.querySelector<HTMLElement>('.teksttv-block-image-preview')?.classList.add('is-hidden');
        (imgRm as HTMLElement).classList.add('is-hidden');
        ctx.refreshSummaries();
        markFormDirty(picker);
    }
}
