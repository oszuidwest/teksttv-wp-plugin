const DIRTY_EVENT = 'teksttv:form-dirty';

/** Mark the plugin form containing `source` as changed. */
export function markFormDirty(source: Element): void {
    source.dispatchEvent(new CustomEvent(DIRTY_EVENT, { bubbles: true }));
}

/** Warn before leaving a Tekst TV admin form with unsaved changes. */
export function initDirtyFormGuards(root: ParentNode = document): void {
    const dirtyForms = new Set<HTMLFormElement>();
    const forms = root.querySelectorAll<HTMLFormElement>('.teksttv-admin form');

    forms.forEach((form) => {
        const markDirty = (): void => {
            dirtyForms.add(form);
        };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener(DIRTY_EVENT, markDirty);
        form.addEventListener('submit', () => dirtyForms.delete(form));
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirtyForms.size === 0) return;
        event.preventDefault();
        event.returnValue = '';
    });
}
