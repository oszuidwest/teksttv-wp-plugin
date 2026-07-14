import { slideToggle, slideUp } from '../../modules/dom';
import { imageItemHtml } from '../../modules/utils';
import { pickImages, pickSingleImage } from '../../modules/wpMedia';
import type { BlocksWorkbenchContext } from './workbenchContext';

/** Toggle the accordion body of the block owning `header`. */
export function toggleBlockOpen(header: Element): void {
    const block = header.closest('.teksttv-block');
    if (!(block instanceof HTMLElement)) return;
    block.classList.toggle('is-expanded');
    const body = block.querySelector<HTMLElement>('.teksttv-block-body');
    if (body) slideToggle(body, 150);
}

/** Slide up and remove the block owning `trigger`, then run `onRemoved`. */
export function removeClosestBlock(trigger: Element, onRemoved: () => void): void {
    const block = trigger.closest('.teksttv-block');
    if (!(block instanceof HTMLElement)) return;
    slideUp(block, 200, () => {
        block.remove();
        onRemoved();
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

    const header = e.target.closest('.teksttv-block-header');
    if (header && blocksRoot.contains(header)) {
        if (e.target.closest('.teksttv-remove-block')) return;
        toggleBlockOpen(header);
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
            attachments.forEach((att) => {
                list.insertAdjacentHTML('beforeend', imageItemHtml(att, baseName));
            });
        });
        return;
    }

    const imgSel = e.target.closest('.teksttv-block-image-select');
    if (imgSel && blocksRoot.contains(imgSel)) {
        e.preventDefault();
        const field = imgSel.closest('.teksttv-block-field, .teksttv-block-image-fields');
        if (!field) return;
        const picker = field.matches('.teksttv-block-image-fields') ? field.closest('.teksttv-block-image-row') : field;
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
        const field = imgRm.closest('.teksttv-block-field, .teksttv-block-image-fields');
        if (!field) return;
        const picker = field.matches('.teksttv-block-image-fields') ? field.closest('.teksttv-block-image-row') : field;
        if (!picker) return;
        const hid = picker.querySelector<HTMLInputElement>('.teksttv-block-image-id');
        if (hid) hid.value = '';
        picker.querySelector<HTMLElement>('.teksttv-block-image-preview')?.classList.add('is-hidden');
        picker.querySelector<HTMLImageElement>('.teksttv-block-image-thumb')?.removeAttribute('src');
        (imgRm as HTMLElement).classList.add('is-hidden');
        ctx.refreshSummaries();
    }
}
