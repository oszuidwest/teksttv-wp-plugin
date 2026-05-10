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

/** Header summary line per `.teksttv-block` inside the blocks container. */
export function updateBlockSummaries(blocksEl: HTMLElement): void {
    blocksEl.querySelectorAll(':scope > .teksttv-block').forEach((blockEl) => {
        if (!(blockEl instanceof HTMLElement)) return;
        const type = blockEl.dataset.type ?? '';
        const scheduling = getSchedulingSuffix(blockEl);
        let summary = '';

        if (type === 'image') {
            const imageId = blockEl.querySelector<HTMLInputElement>('.teksttv-block-image-id')?.value ?? '';
            summary = imageId && imageId !== '0' ? 'Afbeelding' : 'Geen afbeelding';
        } else if (type === 'campaign') {
            const groups: string[] = [];
            blockEl.querySelectorAll('select').forEach((select) => {
                select.querySelectorAll<HTMLOptionElement>('option:checked').forEach((opt) => {
                    if (opt.value) groups.push(opt.text ?? '');
                });
            });
            const limit = blockEl.querySelector<HTMLInputElement>('input[name$="[limit]"]')?.value ?? '';
            const parts: string[] = [];
            parts.push(groups.length ? groups.join(', ') : 'Geen groep');
            if (limit) parts.push(`max ${limit}`);
            summary = parts.join(' · ');
        } else if (type === 'weather') {
            const rawW = blockEl.querySelector<HTMLInputElement>('input[type="text"]')?.value?.trim() ?? '';
            summary = rawW || 'Geen locatie';
        } else if (type === 'campaign_item') {
            const rawC = blockEl.querySelector<HTMLInputElement>('input[type="text"]')?.value?.trim() ?? '';
            summary = rawC || 'Naamloze campagne';
        } else {
            const count = blockEl.querySelector<HTMLInputElement>('input[type="number"]')?.value ?? '?';
            const parts = [`${count}x`];
            blockEl.querySelectorAll('select').forEach((select) => {
                const names: string[] = [];
                select.querySelectorAll<HTMLOptionElement>('option:checked').forEach((opt) => {
                    if (opt.value) names.push(opt.text ?? '');
                });
                if (names.length) parts.push(names.join(', '));
            });
            if (parts.length === 1) parts.push('alle');
            summary = parts.join(' · ');
        }

        const sumEl = blockEl.querySelector('.teksttv-block-summary');
        if (sumEl) sumEl.textContent = summary + scheduling;
    });
}
