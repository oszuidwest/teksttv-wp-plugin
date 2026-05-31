import type { WPMediaAttachment, WPMediaFrame } from '../../modules/types';
import { escAttr } from '../../modules/utils';
import { wpMedia } from '../../modules/wpMedia';

/** Per post-meta Alpine instance: één hergebruikt wp.media frame voor extra afbeeldingen. */
export function createExtraImagesOpener(): (e: Event) => void {
    let mediaFrame: WPMediaFrame | null = null;
    return (e: Event) => {
        e.preventDefault();
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = wpMedia({
            title: 'Afbeeldingen selecteren',
            button: { text: 'Toevoegen' },
            multiple: true,
            library: { type: 'image' },
        });
        mediaFrame.on('select', () => {
            if (!mediaFrame) return;
            const attachments: WPMediaAttachment[] = mediaFrame.state().get('selection').toJSON();
            const list = document.querySelector('#teksttv-images-list');
            if (!list) return;
            for (const att of attachments) {
                const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
                const html =
                    `<div class="teksttv-image-item" data-id="${escAttr(att.id)}">` +
                    `<img src="${escAttr(thumbUrl)}" alt="" />` +
                    `<input type="hidden" name="teksttv_images[]" value="${escAttr(att.id)}" />` +
                    '<button type="button" class="button-link teksttv-remove-image"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>';
                list.insertAdjacentHTML('beforeend', html);
            }
        });
        mediaFrame.open();
    };
}
