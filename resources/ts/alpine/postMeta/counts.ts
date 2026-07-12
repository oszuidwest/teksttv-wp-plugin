import type { TeksttvPostConfig } from '../../modules/types';
import { getTeksttvEditorHtml } from './editorContent';

export function updateTeksttvCharCount(config: TeksttvPostConfig | undefined): void {
    const cc = document.querySelector('#teksttv-charcount');
    if (!(cc instanceof HTMLElement)) return;

    const limit = config?.titleCharLimit ?? 0;
    const title = (document.querySelector<HTMLInputElement>('#teksttv-title')?.value ?? '').trim();
    const len = title.length;

    if (limit > 0 && len > 0) {
        const over = len > limit;
        cc.innerHTML = `<span${over ? ' class="teksttv-charcount-over"' : ''}>${len} / ${limit} tekens</span>`;
    } else {
        cc.textContent = '';
    }
}

export function updateTeksttvWordCount(config: TeksttvPostConfig | undefined, hasPhoto = false): void {
    const content = getTeksttvEditorHtml();
    const wc = document.querySelector('#teksttv-wordcount');
    if (!(wc instanceof HTMLElement)) return;

    const text = content
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    const pageCount = content.split(/<p[^>]*>\s*-{3,}\s*<\/p>|\n*-{3,}\n*/i).length;
    const totalWords = text ? text.split(/\s+/).length : 0;

    const wordLimit = (hasPhoto ? config?.wordLimitPhoto : config?.wordLimit) ?? 0;
    let wordHtml: string;
    if (wordLimit > 0 && totalWords > 0) {
        const over = totalWords > wordLimit;
        wordHtml = `<span${over ? ' class="teksttv-charcount-over"' : ''}>${totalWords} / ${wordLimit} woorden</span>`;
    } else {
        wordHtml = `<span>${totalWords} woorden</span>`;
    }
    const parts = [wordHtml];
    if (pageCount > 1) {
        parts.push(`<span>${pageCount} slides</span>`);
    }
    wc.innerHTML = parts.join(' · ');
}
