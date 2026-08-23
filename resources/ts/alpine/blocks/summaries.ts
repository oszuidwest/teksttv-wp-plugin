function getSchedulingSuffix(block: HTMLElement): string {
    const dates = block.querySelectorAll<HTMLInputElement>('.teksttv-field-grid--scheduling input[type="date"]');
    if (!dates.length) return '';
    const ds = dates[0]?.value ?? '';
    const de = dates[dates.length - 1]?.value ?? '';
    if (ds || de) {
        return ` · ${ds || '…'} – ${de || '…'}`;
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
    if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) return el.value.trim();
    return '';
}

/**
 * Build deduplicated block summaries from `data-summary` fields.
 * `data-summary-label` overrides values; `data-summary-empty` handles empty fields.
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
        if (blockEl.dataset.summaryAsTitle) {
            const title = blockEl.querySelector('.teksttv-block-title');
            if (title) title.textContent = summary || blockEl.dataset.summaryAsTitle;
        } else if (sumEl) {
            sumEl.textContent = summary + getSchedulingSuffix(blockEl);
        }
    });
}
