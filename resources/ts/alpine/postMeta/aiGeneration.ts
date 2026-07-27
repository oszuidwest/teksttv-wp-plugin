import { dispatchInput } from '../../modules/dom';
import type { TeksttvPostConfig } from '../../modules/types';
import { stripTags } from '../../modules/utils';
import { getTeksttvEditorHtml } from './editorContent';

export function teksttvHasExistingGeneratedContent(): boolean {
    const title = (document.querySelector<HTMLInputElement>('#teksttv-title')?.value ?? '').trim();
    const body = stripTags(getTeksttvEditorHtml()).trim();
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

export type AiField = 'title' | 'body' | 'both';

interface GenerateResponse {
    title?: string;
    body?: string;
    content?: string;
    warning?: string;
    // Every failure - plugin WP_Error or core rejection (expired nonce,
    // invalid param) - arrives as {code, message} with a non-ok status.
    message?: string;
}

export function requestAiGeneration(
    config: TeksttvPostConfig,
    btn: HTMLButtonElement,
    field: AiField,
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

    const showError = (message: string): void => {
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.classList.add('is-error');
        } else {
            // Errors must never depend on the status element existing.
            console.error('TekstTV AI-generatie:', message);
        }
    };

    fetch(config.generateUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.restNonce,
        },
        body: JSON.stringify({ post_id: config.postId, field, has_photo: hasPhoto }),
    })
        .then(async (res) => ({ ok: res.ok, data: (await res.json()) as GenerateResponse }))
        .then(({ ok, data }) => {
            if (!ok) {
                showError(data.message || 'Er ging iets mis bij het genereren.');
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
            if (!badge && statusEl) {
                const span = document.createElement('span');
                span.className = 'teksttv-ai-badge';
                span.id = 'teksttv-ai-badge';
                span.innerHTML = '<span class="dashicons dashicons-admin-generic"></span> AI gegenereerd';
                statusEl.insertAdjacentElement('afterend', span);
                badge = span;
            }

            if (data.warning && statusEl) {
                statusEl.textContent = data.warning;
                statusEl.classList.add('is-warning');
            }
        })
        .catch(() => {
            showError('Er ging iets mis bij het genereren.');
        })
        .finally(() => {
            window.clearInterval(msgInterval);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}
