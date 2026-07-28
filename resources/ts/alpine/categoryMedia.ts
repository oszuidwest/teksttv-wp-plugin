import type { WPMediaFrame } from '../modules/types';
import { pickSingleImage } from '../modules/wpMedia';

/** Category add/edit: pick or clear Tekst TV image. */
export function createCategoryMediaPage() {
    let frame: WPMediaFrame | null = null;

    return {
        pickImage(e: Event): void {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = pickSingleImage((att) => {
                const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
                const idInput = document.querySelector<HTMLInputElement>('#teksttv-cat-image-id');
                const preview = document.querySelector<HTMLImageElement>('#teksttv-cat-image-preview');
                const removeBtn = document.querySelector<HTMLButtonElement>('#teksttv-cat-image-remove');
                if (idInput) idInput.value = String(att.id);
                if (preview) {
                    preview.src = thumbUrl;
                    preview.classList.remove('is-hidden');
                }
                removeBtn?.classList.remove('is-hidden');
            });
        },

        clearImage(e: Event): void {
            const btn = e.currentTarget;
            const idField = document.querySelector<HTMLInputElement>('#teksttv-cat-image-id');
            if (idField) idField.value = '';
            document.querySelector<HTMLImageElement>('#teksttv-cat-image-preview')?.classList.add('is-hidden');
            if (btn instanceof HTMLElement) btn.classList.add('is-hidden');
        },
    };
}
