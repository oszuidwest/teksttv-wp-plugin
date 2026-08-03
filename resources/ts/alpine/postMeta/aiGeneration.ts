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
}

export function getAiGenerationErrorMessage(
    error: { message?: string; data?: { status?: number } } | null | undefined,
): string {
    return typeof error?.data?.status === 'number' && error.message
        ? error.message
        : 'Er ging iets mis bij het genereren.';
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

    wp.apiFetch<GenerateResponse>({
        url: config.generateUrl,
        method: 'POST',
        data: { post_id: config.postId, field, has_photo: hasPhoto },
    })
        .then((data) => {
            if (field === 'both') {
                if (data.title) applyTeksttvTitle(data.title);
                if (data.body) applyTeksttvBody(data.body);
            } else if (field === 'title' && data.content) {
                applyTeksttvTitle(data.content);
            } else if (field === 'body' && data.content) {
                applyTeksttvBody(data.content);
            }

            onApplied?.();

            if (data.warning && statusEl) {
                statusEl.textContent = data.warning;
                statusEl.classList.add('is-warning');
            }
        })
        .catch((error) => showError(getAiGenerationErrorMessage(error)))
        .finally(() => {
            window.clearInterval(msgInterval);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}
