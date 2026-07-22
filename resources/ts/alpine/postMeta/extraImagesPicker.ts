import type { WPMediaAttachment, WPMediaFrame } from '../../modules/types';
import { imageItemHtml } from '../../modules/utils';
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
                list.insertAdjacentHTML('beforeend', imageItemHtml(att, 'teksttv_images[]'));
            }
        });
        mediaFrame.open();
    };
}
