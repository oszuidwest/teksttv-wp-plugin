import { dispatchInput } from '../../modules/dom';
import type { TeksttvPostConfig } from '../../modules/types';
import { getTeksttvEditorHtml } from './editorContent';

export function teksttvHasExistingGeneratedContent(): boolean {
    const title = (document.querySelector<HTMLInputElement>('#teksttv-title')?.value ?? '').trim();
    const body = getTeksttvEditorHtml()
        .replace(/<[^>]+>/g, '')
        .trim();
    return title.length > 0 || body.length > 0;
}

export function applyTeksttvTitle(content: string): void {
    const el = document.querySelector<HTMLInputElement>('#teksttv-title');
    if (!el) return;
    el.value = content;
    dispatchInput(el);
}

export function applyTeksttvBody(content: string): void {
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get('teksttv_content') : null;
    if (editor && !editor.isHidden()) {
        editor.setContent(content);
        editor.fire('change');
        return;
    }
    const ta = document.querySelector<HTMLTextAreaElement>('#teksttv_content');
    if (!ta) return;
    ta.value = content;
    dispatchInput(ta);
}

export function requestAiGeneration(
    config: TeksttvPostConfig,
    btn: HTMLButtonElement,
    field: string,
    hasPhoto: boolean,
    onApplied?: () => void,
): void {
    const statusEl = document.querySelector('#teksttv-generate-status');
    const originalHtml = btn.innerHTML;
    const loadingMessages = [
        'Even nadenken...',
        'Artikel aan het lezen...',
        'De essentie aan het vinden...',
        'Aan het samenvatten...',
        'Tekst TV klaar maken...',
        'Tekst aan het polijsten...',
    ];
    let msgIndex = 0;
    const spinnerHtml = '<span class="dashicons dashicons-update teksttv-spin teksttv-button-icon"></span> ';
    btn.disabled = true;
    btn.innerHTML = spinnerHtml + loadingMessages[0];
    const msgInterval = window.setInterval(() => {
        msgIndex = (msgIndex + 1) % loadingMessages.length;
        btn.innerHTML = spinnerHtml + loadingMessages[msgIndex];
    }, 2500);
    statusEl?.classList.remove('is-error', 'is-warning');
    if (statusEl) statusEl.textContent = '';

    fetch(config.generateUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.restNonce,
        },
        body: JSON.stringify({ post_id: config.postId, field, has_photo: hasPhoto }),
    })
        .then((res) => res.json())
        .then((data: { title?: string; body?: string; content?: string; error?: string; warning?: string }) => {
            if (data.error && statusEl) {
                statusEl.textContent = data.error;
                statusEl.classList.add('is-error');
                return;
            }

            if (field === 'both') {
                if (data.title) applyTeksttvTitle(data.title);
                if (data.body) applyTeksttvBody(data.body);
            } else if (field === 'title' && data.content) {
                applyTeksttvTitle(data.content);
            } else if (field === 'body' && data.content) {
                applyTeksttvBody(data.content);
            }

            onApplied?.();

            let badge = document.querySelector('#teksttv-ai-badge');
            const afterTarget = document.querySelector('#teksttv-generate-status');
            if (!badge && afterTarget) {
                const span = document.createElement('span');
                span.className = 'teksttv-ai-badge';
                span.id = 'teksttv-ai-badge';
                span.innerHTML = '<span class="dashicons dashicons-admin-generic"></span> AI gegenereerd';
                afterTarget.insertAdjacentElement('afterend', span);
                badge = span;
            }

            if (data.warning && statusEl) {
                statusEl.textContent = data.warning;
                statusEl.classList.add('is-warning');
            }
        })
        .catch(() => {
            if (statusEl) {
                statusEl.textContent = 'Er ging iets mis bij het genereren.';
                statusEl.classList.add('is-error');
            }
        })
        .finally(() => {
            window.clearInterval(msgInterval);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}
