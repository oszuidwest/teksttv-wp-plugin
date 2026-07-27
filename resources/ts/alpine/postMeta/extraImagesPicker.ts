import type { WPMediaFrame } from '../../modules/types';
import { imageItemHtml } from '../../modules/utils';
import { pickImages } from '../../modules/wpMedia';

/** Reuse one wp.media frame per post-meta instance so its selection state survives reopening. */
export function createExtraImagesOpener(): (e: Event) => void {
    let mediaFrame: WPMediaFrame | null = null;
    return (e: Event) => {
        e.preventDefault();
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = pickImages(
            (attachments) => {
                const list = document.querySelector('#teksttv-images-list');
                if (!list) return;
                for (const att of attachments) {
                    list.insertAdjacentHTML('beforeend', imageItemHtml(att, 'teksttv_images[]'));
                }
            },
            { title: 'Afbeeldingen selecteren', button: { text: 'Toevoegen' } },
        );
    };
}
