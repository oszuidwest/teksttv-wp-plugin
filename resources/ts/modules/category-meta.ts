import type { WPMediaAttachment, WPMediaFrame } from './types';

/** Category edit page: sidebar image picker. */
export function initCategoryMeta(): void {
    const $ = jQuery;

    let frame: WPMediaFrame | null = null;
    $('#teksttv-cat-image-select').on('click', (e) => {
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({ multiple: false, library: { type: 'image' } });
        frame.on('select', () => {
            if (!frame) return;
            const att: WPMediaAttachment = frame.state().get('selection').first().toJSON();
            const thumbUrl = att.sizes?.thumbnail?.url ?? att.url;
            $('#teksttv-cat-image-id').val(att.id);
            $('#teksttv-cat-image-preview').attr('src', thumbUrl).removeClass('is-hidden');
            $('#teksttv-cat-image-remove').removeClass('is-hidden');
        });
        frame.open();
    });

    $('#teksttv-cat-image-remove').on('click', function () {
        $('#teksttv-cat-image-id').val('');
        $('#teksttv-cat-image-preview').addClass('is-hidden');
        $(this).addClass('is-hidden');
    });
}
