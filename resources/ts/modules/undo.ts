type UndoRemovalOptions = {
    message: string;
    focusAfterRemove?: HTMLElement | null;
    focusAfterRestore?: (element: HTMLElement) => HTMLElement | null;
    focusUndo?: boolean;
    onChange?: () => void;
};

type PendingUndo = {
    restore: () => void;
    dismiss: (restoreFocus?: boolean) => void;
};

let pendingUndo: PendingUndo | null = null;

function getSnackbar(): {
    root: HTMLElement;
    message: HTMLElement;
    action: HTMLButtonElement;
    dismiss: HTMLButtonElement;
} {
    let root = document.querySelector<HTMLElement>('#teksttv-snackbar');
    if (!root) {
        root = document.createElement('div');
        root.id = 'teksttv-snackbar';
        root.className = 'teksttv-snackbar';
        root.hidden = true;
        root.innerHTML =
            '<span class="teksttv-snackbar-message" role="status" aria-live="polite" aria-atomic="true"></span>' +
            '<button type="button" class="teksttv-snackbar-action">Ongedaan maken</button>' +
            '<button type="button" class="teksttv-snackbar-dismiss" aria-label="Melding sluiten">' +
            '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
        document.body.append(root);
        root.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !pendingUndo) return;
            event.preventDefault();
            pendingUndo.dismiss();
        });
    }

    const message = root.querySelector<HTMLElement>('.teksttv-snackbar-message');
    const action = root.querySelector<HTMLButtonElement>('.teksttv-snackbar-action');
    const dismiss = root.querySelector<HTMLButtonElement>('.teksttv-snackbar-dismiss');
    if (!(message && action && dismiss)) throw new Error('TekstTV snackbar kon niet worden geïnitialiseerd.');
    return { root, message, action, dismiss };
}

function dismissSnackbar(root: HTMLElement): void {
    root.hidden = true;
    pendingUndo = null;
}

/**
 * Remove a form item with one-level undo and input-aware focus handling.
 */
export function removeElementWithUndo(element: HTMLElement, options: UndoRemovalOptions): void {
    const parent = element.parentNode;
    if (!parent) return;

    const nextSibling = element.nextSibling;
    const snackbar = getSnackbar();
    pendingUndo?.dismiss(false);

    element.remove();
    options.onChange?.();

    snackbar.message.textContent = options.message;
    snackbar.root.hidden = false;

    const restore = (): void => {
        const anchor = nextSibling?.parentNode === parent ? nextSibling : null;
        parent.insertBefore(element, anchor);
        options.onChange?.();
        dismissSnackbar(snackbar.root);
        options.focusAfterRestore?.(element)?.focus();
    };

    const dismiss = (restoreFocus = snackbar.root.contains(document.activeElement)): void => {
        dismissSnackbar(snackbar.root);
        if (restoreFocus) options.focusAfterRemove?.focus();
    };
    pendingUndo = { restore, dismiss };

    snackbar.action.onclick = () => {
        if (!pendingUndo) return;
        pendingUndo.restore();
    };
    snackbar.dismiss.onclick = () => pendingUndo?.dismiss();

    if (options.focusUndo) snackbar.action.focus();
    else options.focusAfterRemove?.focus();
}
