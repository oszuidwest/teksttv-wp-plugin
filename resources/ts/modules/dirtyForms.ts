const dirtyForms = new Set<HTMLFormElement>();

/** Mark the plugin form containing `source` as changed. */
export function markFormDirty(source: Element): void {
    const form = source.closest<HTMLFormElement>('.teksttv-admin form');
    if (form) dirtyForms.add(form);
}

/** Warn before leaving a Tekst TV admin form with unsaved changes. */
export function initDirtyFormGuards(): void {
    document.querySelectorAll<HTMLFormElement>('.teksttv-admin form').forEach((form) => {
        const markDirty = (): void => {
            dirtyForms.add(form);
        };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', () => dirtyForms.delete(form));
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirtyForms.size === 0) return;
        event.preventDefault();
        event.returnValue = '';
    });
}
