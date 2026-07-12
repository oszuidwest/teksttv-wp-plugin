function getSchedulingSuffix(block: HTMLElement): string {
    const dates = Array.from(
        block.querySelectorAll<HTMLInputElement>('.teksttv-block-fields--scheduling input[type="date"]'),
    );
    if (!dates.length) return '';
    const ds = dates[0]?.value ?? '';
    const de = dates.at(-1)?.value ?? '';
    if (ds || de) {
        return ` · ${ds || '...'} – ${de || '...'}`;
    }
    return '';
}

function fieldSummaryValue(el: Element): string {
    if (el instanceof HTMLSelectElement) {
        return Array.from(el.selectedOptions)
            .filter((opt) => opt.value)
            .map((opt) => opt.text)
            .join(', ');
    }
    if (el instanceof HTMLInputElement) return el.value.trim();
    return '';
}

/**
 * Header summary line per `.teksttv-block`, driven by `data-summary` markers
 * that each block's PHP `render()` puts on its own fields — this module has no
 * knowledge of block types, so registry-registered blocks work automatically.
 *
 * Field contract:
 * - `data-summary`         — include this field; the attribute value is an
 *                            optional format with `%s` (e.g. `%sx`, `max %s`).
 * - `data-summary-label`   — show this text instead of the value when filled.
 * - `data-summary-empty`   — show this text when the field is empty; identical
 *                            parts are deduplicated (e.g. several taxonomy
 *                            filters that all fall back to 'alle').
 */
export function updateBlockSummaries(blocksEl: HTMLElement): void {
    blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((blockEl) => {
        if (!(blockEl instanceof HTMLElement)) return;

        const parts: string[] = [];
        blockEl.querySelectorAll<HTMLElement>('[data-summary]').forEach((field) => {
            const value = fieldSummaryValue(field);
            if (value !== '') {
                const format = field.dataset.summary ?? '';
                parts.push(field.dataset.summaryLabel ?? (format ? format.replace('%s', value) : value));
            } else if (field.dataset.summaryEmpty) {
                parts.push(field.dataset.summaryEmpty);
            }
        });

        const summary = [...new Set(parts)].join(' · ');
        const sumEl = blockEl.querySelector('.teksttv-block-summary');
        if (sumEl) sumEl.textContent = summary + getSchedulingSuffix(blockEl);
    });
}
