/** Helpers to replace lightweight jQuery-style patterns in WP admin scripts. */

export function ready(callback: () => void): void {
    if (document.readyState !== 'loading') {
        callback();
    } else {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    }
}

export function delegate(
    root: Document | HTMLElement,
    eventType: string,
    selector: string,
    handler: (event: Event, matched: HTMLElement) => void,
    options?: AddEventListenerOptions,
): void {
    root.addEventListener(
        eventType,
        (event: Event) => {
            const t = event.target;
            if (!t || !(t instanceof Element)) return;
            const matched = t.closest(selector);
            if (!(matched instanceof HTMLElement) || !root.contains(matched)) return;
            handler(event, matched);
        },
        options,
    );
}

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

export function tmplHtml(templateId: string): string | null {
    const el = document.getElementById(templateId);
    return el?.innerHTML?.trim() ? el.innerHTML : null;
}

export function dispatchInput(el: Element): void {
    el.dispatchEvent(new Event('input', { bubbles: true }));
}
