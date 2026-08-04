/** Sidebar-afbeeldingskiezer: alleen staat/actieve kaart en verborgen veld. Preview herbereken extern. */
export function applySidebarCardState(state: string, refreshPreview: () => void): void {
    for (const c of document.querySelectorAll('.teksttv-image-card')) {
        const selected = c instanceof HTMLElement && c.dataset.state === state;
        c.classList.toggle('is-active', selected);
        c.setAttribute('aria-pressed', String(selected));
    }
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
