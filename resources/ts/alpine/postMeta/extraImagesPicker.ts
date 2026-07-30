import type { WPMediaFrame } from '../../modules/types';
import { imageItemHtml } from '../../modules/utils';
import { pickImages } from '../../modules/wpMedia';

/** Per post-meta Alpine instance: één hergebruikt wp.media frame voor extra afbeeldingen. */
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
                const firstNewIndex = list.children.length;
                for (const att of attachments) {
                    list.insertAdjacentHTML('beforeend', imageItemHtml(att, 'teksttv_images[]'));
                }
                onChanged?.();
                window.setTimeout(() => {
                    list.children[firstNewIndex]?.querySelector<HTMLButtonElement>('.teksttv-remove-image')?.focus();
                });
            },
            { title: 'Afbeeldingen selecteren', button: { text: 'Toevoegen' } },
        );
    };
}
