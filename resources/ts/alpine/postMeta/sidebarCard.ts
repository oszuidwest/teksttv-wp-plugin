/**
 * Select a sidebar image card and sync the hidden field: '' uses automatic image
 * resolution, while '0' suppresses the image (see PostMeta::process_save).
 */
export function applySidebarCardState(state: string, refreshPreview: () => void): void {
    for (const c of document.querySelectorAll('.teksttv-image-card')) {
        c.classList.remove('is-active');
    }
    document.querySelector(`.teksttv-image-card[data-state="${state}"]`)?.classList.add('is-active');
    const cards = document.querySelector('.teksttv-image-cards');
    if (cards instanceof HTMLElement) cards.dataset.active = state;

    if (state === 'none') {
        const sidNone = document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id');
        if (sidNone) sidNone.value = '0';
    } else if (state === 'default') {
        const sid = document.querySelector<HTMLInputElement>('#teksttv-sidebar-image-id');
        if (sid) sid.value = '';
    }
    refreshPreview();
}
