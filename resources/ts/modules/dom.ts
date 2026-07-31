/** Helpers to replace lightweight jQuery-style patterns in WP admin scripts. */

export function hide(el: HTMLElement): void {
    el.style.display = 'none';
}

export function show(el: HTMLElement): void {
    el.style.removeProperty('display');
}

/** True when computed display is none. */
export function isHidden(el: HTMLElement): boolean {
    return window.getComputedStyle(el).display === 'none';
}

export function slideDown(el: HTMLElement, durationMs = 150): void {
    show(el);
    el.style.overflow = 'hidden';
    const target = el.scrollHeight;
    el.style.height = '0';
    el.style.transition = '';
    void el.offsetHeight;
    el.style.transition = `height ${durationMs}ms ease`;
    el.style.height = `${target}px`;
    window.setTimeout(() => {
        el.style.removeProperty('height');
        el.style.removeProperty('overflow');
        el.style.removeProperty('transition');
    }, durationMs);
}

export function slideUp(el: HTMLElement, durationMs = 150, onComplete?: () => void): void {
    el.style.overflow = 'hidden';
    el.style.transition = '';
    el.style.height = `${el.offsetHeight}px`;
    void el.offsetHeight;
    el.style.transition = `height ${durationMs}ms ease`;
    el.style.height = '0';
    window.setTimeout(() => {
        hide(el);
        el.style.removeProperty('height');
        el.style.removeProperty('overflow');
        el.style.removeProperty('transition');
        onComplete?.();
    }, durationMs);
}

export function slideToggle(el: HTMLElement, durationMs = 150): void {
    if (isHidden(el)) {
        slideDown(el, durationMs);
    } else {
        slideUp(el, durationMs);
    }
}

export function fadeOutRemove(el: HTMLElement, durationMs: number, onRemoved?: () => void): void {
    el.style.transition = `opacity ${durationMs}ms ease`;
    el.style.opacity = '0';
    window.setTimeout(() => {
        el.remove();
        onRemoved?.();
    }, durationMs);
}

/**
 * Focus target for after removing a list item: the same control in the next
 * sibling, else the previous sibling, else `fallback`. Resolve it before the
 * item is removed (or animated out) so the siblings still exist.
 */
export function siblingFocusTarget(item: Element, selector: string, fallback: HTMLElement | null): HTMLElement | null {
    return (
        item.nextElementSibling?.querySelector<HTMLElement>(selector) ??
        item.previousElementSibling?.querySelector<HTMLElement>(selector) ??
        fallback
    );
}

export function tmplHtml(templateId: string): string | null {
    const el = document.getElementById(templateId);
    return el?.innerHTML?.trim() ? el.innerHTML : null;
}

export function dispatchInput(el: Element): void {
    el.dispatchEvent(new Event('input', { bubbles: true }));
}

/**
 * Rewrite indexed `name` attributes on inputs/selects/textareas after reorder
 * or delete, plus `data-name` on containers (the campaign slides list stores
 * its field name there and later-added hidden inputs derive from it).
 * `pattern` must capture the name prefix in group 1; each match becomes `$1[<item index>]`.
 */
export function reindexNames(container: HTMLElement, itemSelector: string, pattern: RegExp): void {
    container.querySelectorAll(itemSelector).forEach((item, i) => {
        item.querySelectorAll('input, select, textarea').forEach((input) => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(pattern, `$1[${i}]`));
            }
        });
        item.querySelectorAll<HTMLElement>('[data-name]').forEach((el) => {
            const dataName = el.dataset.name;
            if (dataName) {
                el.dataset.name = dataName.replace(pattern, `$1[${i}]`);
            }
        });
    });
}
