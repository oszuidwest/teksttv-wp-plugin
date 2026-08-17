import type { WPMediaFrame } from '../../modules/types';
import { appendImageItems } from '../../modules/utils';
import { pickImages } from '../../modules/wpMedia';

/** Reuse one wp.media frame per post-meta component. */
export function createExtraImagesOpener(onChanged?: () => void): (e: Event) => void {
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
                appendImageItems(list, attachments, 'teksttv_images[]');
                onChanged?.();
            },
            { title: 'Afbeeldingen selecteren', button: { text: 'Toevoegen' } },
        );
    };
}
