import { slideToggle, slideUp } from '../../modules/dom';
import type { WPMediaAttachment } from '../../modules/types';
import { escAttr } from '../../modules/utils';
import { wpMedia } from '../../modules/wpMedia';
import type { BlocksWorkbenchContext } from './workbenchContext';

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
        const block = rem.closest('.teksttv-block');
        if (!(block instanceof HTMLElement)) return;
        slideUp(block, 200, () => {
            block.remove();
            ctx.reindexBlocks();
            ctx.refreshSummaries();
        });
        return;
    }

    const header = e.target.closest('.teksttv-block-header');
    if (header && blocksRoot.contains(header)) {
        if (e.target.closest('.teksttv-remove-block')) return;
        const block = header.closest('.teksttv-block');
        if (!(block instanceof HTMLElement)) return;
        block.classList.toggle('is-expanded');
        const body = block.querySelector<HTMLElement>('.teksttv-block-body');
        if (body) slideToggle(body, 150);
        return;
    }

    const slidesBtn = e.target.closest('.teksttv-campaign-add-slides');
    if (slidesBtn && blocksRoot.contains(slidesBtn)) {
        e.preventDefault();
        const section = slidesBtn.closest('.teksttv-campaign-slides-section');
        const list = section?.querySelector<HTMLElement>('.teksttv-campaign-slides');
        const baseName = list?.dataset.name;
        if (!list || !baseName) return;
        const frame = wpMedia({ multiple: true, library: { type: 'image' } });
        frame.on('select', () => {
            const attachments: WPMediaAttachment[] = frame.state().get('selection').toJSON();
            attachments.forEach((att) => {
                const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
                const fragment =
                    `<div class="teksttv-image-item" data-id="${escAttr(att.id)}">` +
                    `<img src="${escAttr(thumbUrl)}" alt="" />` +
                    `<input type="hidden" name="${escAttr(baseName)}" value="${escAttr(att.id)}" />` +
                    '<button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>';
                list.insertAdjacentHTML('beforeend', fragment);
            });
        });
        frame.open();
        return;
    }

    const imgSel = e.target.closest('.teksttv-block-image-select');
    if (imgSel && blocksRoot.contains(imgSel)) {
        e.preventDefault();
        const field = imgSel.closest('.teksttv-block-field, .teksttv-block-image-fields');
        if (!field) return;
        const mediaFrame = wpMedia({ multiple: false, library: { type: 'image' } });
        mediaFrame.on('select', () => {
            const att: WPMediaAttachment = mediaFrame.state().get('selection').first().toJSON();
            const url = att.sizes?.medium?.url ?? att.url;
            const idInput = field.querySelector<HTMLInputElement>('.teksttv-block-image-id');
            const thumb = field.querySelector<HTMLImageElement>('.teksttv-block-image-thumb');
            const previewBox = field.querySelector<HTMLElement>('.teksttv-block-image-preview');
            const removeBtn = field.querySelector<HTMLElement>('.teksttv-block-image-remove');
            if (idInput) idInput.value = String(att.id);
            if (thumb) thumb.src = url;
            previewBox?.classList.remove('is-hidden');
            removeBtn?.classList.remove('is-hidden');
            ctx.refreshSummaries();
        });
        mediaFrame.open();
        return;
    }

    const imgRm = e.target.closest('.teksttv-block-image-remove');
    if (imgRm && blocksRoot.contains(imgRm)) {
        const field = imgRm.closest('.teksttv-block-field, .teksttv-block-image-fields');
        if (!field) return;
        const hid = field.querySelector<HTMLInputElement>('.teksttv-block-image-id');
        if (hid) hid.value = '';
        field.querySelector<HTMLElement>('.teksttv-block-image-preview')?.classList.add('is-hidden');
        (imgRm as HTMLElement).classList.add('is-hidden');
        ctx.refreshSummaries();
    }
}
