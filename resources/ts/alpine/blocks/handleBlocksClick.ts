import { hide, show, siblingFocusTarget, slideDown, slideUp } from '../../modules/dom';
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

/**
 * Delegated `#teksttv-blocks` / `#teksttv-campaigns` clicks: remove, accordion, campaign slides, image pickers.
 * Keeps `workbench.ts` readable; context holds DOM roots and refresh helpers.
 */
export function handleBlocksClick(e: MouseEvent, ctx: BlocksWorkbenchContext): void {
    if (!(e.target instanceof Element) || !ctx.blocksEl) return;
    const blocksRoot = ctx.blocksEl;

    const rem = e.target.closest('.teksttv-remove-block');
    if (rem && blocksRoot.contains(rem)) {
        e.stopPropagation();
        removeClosestBlock(rem, () => {
            ctx.reindexBlocks();
            ctx.refreshSummaries();
        });
        return;
    }

    const toggle = e.target.closest('.teksttv-block-toggle-control');
    if (toggle && blocksRoot.contains(toggle)) {
        toggleBlockOpen(toggle);
        return;
    }

    const slidesBtn = e.target.closest('.teksttv-campaign-add-slides');
    if (slidesBtn && blocksRoot.contains(slidesBtn)) {
        e.preventDefault();
        const section = slidesBtn.closest('.teksttv-campaign-slides-section');
        const list = section?.querySelector<HTMLElement>('.teksttv-campaign-slides');
        const baseName = list?.dataset.name;
        if (!list || !baseName) return;
        pickImages((attachments) => {
            appendImageItems(list, attachments, baseName);
        });
        return;
    }

    const imgItemRm = e.target.closest('.teksttv-remove-image');
    if (imgItemRm && blocksRoot.contains(imgItemRm)) {
        e.preventDefault();
        removeImageItem(imgItemRm);
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
    }
}
