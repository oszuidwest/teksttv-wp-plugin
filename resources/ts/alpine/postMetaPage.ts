import Sortable from 'sortablejs';
import { hide, prefersReducedMotion, show, slideDown, slideUp } from '../modules/dom';
import type { ImageData, Slide, TeksttvPostConfig, WPTinyMCEEditor } from '../modules/types';
import { debounce, previewSlideUrl, removeImageItem, retryUntil } from '../modules/utils';
import { requestAiGeneration, teksttvHasExistingGeneratedContent } from './postMeta/aiGeneration';
import { buildSlidesFromDom, hasSidebarPhoto } from './postMeta/buildSlides';
import { updateTeksttvCharCount, updateTeksttvWordCount } from './postMeta/counts';
import { syncDateEndResetButton } from './postMeta/dateEndUi';
import { getTeksttvEditorHtml } from './postMeta/editorContent';
import { createExtraImagesOpener } from './postMeta/extraImagesPicker';
import { initTeksttvEditorWhenDisplayed } from './postMeta/lazyEditor';
import { mountTeksttvPreviewOverlay } from './postMeta/previewOverlay';
import { updatePreviewThumbnails } from './postMeta/previewThumbnails';
import { applySidebarCardState } from './postMeta/sidebarCard';
import { createSidebarCustomPicker } from './postMeta/sidebarCustomPicker';

export function createPostMetaPage() {
    const config: TeksttvPostConfig | undefined = typeof teksttvPost !== 'undefined' ? teksttvPost : undefined;

    let currentSlideIndex = 0;
    let slides: Slide[] = [];
    let customImageData: ImageData | null = config?.customImage ? (config.customImage as ImageData) : null;

    const previewUrl = config?.previewUrl ?? '';

    function getSlides(content?: string): Slide[] {
        return buildSlidesFromDom(config, customImageData, content);
    }

    function updatePreviewNav(): void {
        const total = slides.length;
        const current = total > 0 ? currentSlideIndex + 1 : 0;
        const isMultiSlide = total > 1;

        const counter = document.querySelector('#teksttv-preview-counter');
        if (counter) counter.textContent = `${current} / ${total}`;
        const nav = document.querySelector<HTMLElement>('#teksttv-preview-nav');
        nav?.classList.toggle('is-hidden', !isMultiSlide);
        const prevBtn = document.querySelector<HTMLButtonElement>('#teksttv-preview-prev');
        const nextBtn = document.querySelector<HTMLButtonElement>('#teksttv-preview-next');
        if (prevBtn) prevBtn.disabled = currentSlideIndex <= 0;
        if (nextBtn) nextBtn.disabled = currentSlideIndex >= total - 1;

        const thumbs = document.querySelector('#teksttv-preview-thumbs');
        if (thumbs instanceof HTMLElement) {
            thumbs.classList.toggle('is-hidden', !isMultiSlide);
            if (isMultiSlide && previewUrl) {
                updatePreviewThumbnails(thumbs, slides, currentSlideIndex, previewUrl);
            } else {
                // Clearing stops the hidden thumbnail iframes; is-hidden alone keeps them alive.
                thumbs.replaceChildren();
            }
        }
    }

    const updatePreview = debounce(() => {
        const content = getTeksttvEditorHtml();
        updateTeksttvWordCount(config, hasSidebarPhoto(config, customImageData), content);

        const iframe = document.querySelector<HTMLIFrameElement>('#teksttv-preview-iframe');
        if (!(previewUrl && iframe)) return;

        slides = getSlides(content);
        if (currentSlideIndex >= slides.length) currentSlideIndex = slides.length - 1;
        if (currentSlideIndex < 0) currentSlideIndex = 0;
        updatePreviewNav();

        const container = iframe.closest('.teksttv-preview-container');
        if (slides.length === 0) {
            iframe.setAttribute('src', 'about:blank');
            container?.classList.remove('is-loading');
            container?.classList.add('is-empty');
            return;
        }

        container?.classList.remove('is-empty');
        // keyup also fires for keys that do not change content.
        const newSrc = previewSlideUrl(previewUrl, slides[currentSlideIndex]);
        if (iframe.getAttribute('src') === newSrc) return;

        container?.classList.add('is-loading');
        iframe.onload = () => container?.classList.remove('is-loading');
        iframe.setAttribute('src', newSrc);
    }, 400);

    const openExtraImages = createExtraImagesOpener(updatePreview);

    const openSidebarCustom = createSidebarCustomPicker(
        config,
        (d) => {
            customImageData = d;
        },
        updatePreview,
    );

    function activateSidebarCard(state: string): void {
        applySidebarCardState(state, updatePreview);
    }

    return {
        init(): void {
            const activeInput = document.querySelector<HTMLInputElement>('#teksttv-active');
            const fields = document.querySelector<HTMLElement>('#teksttv-fields');

            if (!(activeInput && fields)) return;

            if (activeInput.checked) {
                show(fields);
            } else {
                hide(fields);
            }

            syncDateEndResetButton(config);

            const imagesListEl = document.getElementById('teksttv-images-list');
            if (imagesListEl) {
                new Sortable(imagesListEl, {
                    ghostClass: 'teksttv-sortable-ghost',
                    dragClass: 'teksttv-sortable-drag',
                    animation: prefersReducedMotion() ? 0 : 150,
                });
            }

            updateTeksttvCharCount(config);

            const bindTinyMceEvents = (): boolean => {
                if (typeof tinymce === 'undefined') return false;

                const bindEditor = (editor: WPTinyMCEEditor): void => {
                    // keyup covers TinyMCE edits that omit input events.
                    editor.on('input change keyup SetContent', updatePreview);
                };
                const existing = tinymce.get('teksttv_content');
                if (existing) bindEditor(existing);
                tinymce.on('AddEditor', (e) => {
                    if (e.editor.id === 'teksttv_content') bindEditor(e.editor);
                });
                return true;
            };

            // Retry while WordPress exposes TinyMCE asynchronously.
            retryUntil(bindTinyMceEvents);

            // The editor is rendered with wp_skip_init (see lazyEditor.ts).
            initTeksttvEditorWhenDisplayed();

            document.addEventListener('input', (e) => {
                const t = e.target;
                if (!(t instanceof Element && t.matches('#teksttv_content'))) return;
                updatePreview();
            });

            document.querySelector('#title')?.addEventListener('input', updatePreview);

            if (config?.aiSupported && config.generateUrl) {
                if (!config.isNewPost) {
                    activeInput.addEventListener('change', () => {
                        if (!activeInput.checked) return;
                        if (teksttvHasExistingGeneratedContent()) return;

                        window.setTimeout(() => {
                            const generateBtn = document.querySelector<HTMLButtonElement>(
                                '.teksttv-ai-section .teksttv-generate-btn',
                            );
                            if (!generateBtn) return;

                            const field = generateBtn.dataset.field === 'both' ? 'both' : 'body';
                            const confirmation =
                                field === 'both'
                                    ? 'Wil je automatisch een kop en tekst genereren?'
                                    : 'Wil je automatisch tekst genereren?';
                            if (window.confirm(confirmation)) {
                                requestAiGeneration(
                                    config,
                                    generateBtn,
                                    field,
                                    hasSidebarPhoto(config, customImageData),
                                    updatePreview,
                                );
                            }
                        }, 300);
                    });
                }
            }

            window.setTimeout(updatePreview, 500);
        },

        onActiveChange(): void {
            const activeInput = document.querySelector<HTMLInputElement>('#teksttv-active');
            const fields = document.querySelector<HTMLElement>('#teksttv-fields');
            if (!(activeInput && fields)) return;
            const isChecked = activeInput.checked;
            if (isChecked) {
                slideDown(fields, 200);
            } else {
                slideUp(fields, 200);
            }
        },

        openExtraImages,

        onExtraImagesClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const tgt = e.target.closest('.teksttv-remove-image');
            if (tgt) removeImageItem(tgt, updatePreview, e.detail === 0);
        },

        activateSidebarCardDefault(): void {
            activateSidebarCard('default');
        },

        activateSidebarCardNone(): void {
            activateSidebarCard('none');
        },

        openSidebarCustom,

        onDateEndChange(): void {
            syncDateEndResetButton(config);
        },

        resetDateEnd(e: Event): void {
            e.preventDefault();
            const defaultEndDate = config?.defaultEndDate ?? '';
            const dateEnd = document.querySelector<HTMLInputElement>('#teksttv-date-end');
            const btn = document.querySelector<HTMLButtonElement>('#teksttv-date-end-reset');
            if (!(dateEnd && defaultEndDate)) return;
            dateEnd.value = defaultEndDate;
            btn?.classList.add('is-hidden');
        },

        previewPrev(): void {
            if (currentSlideIndex > 0) {
                currentSlideIndex--;
                updatePreview();
            }
        },

        previewNext(): void {
            if (currentSlideIndex < slides.length - 1) {
                currentSlideIndex++;
                updatePreview();
            }
        },

        openPreviewOverlay(): void {
            if (!previewUrl) return;
            slides = getSlides();
            if (!slides.length) return;
            mountTeksttvPreviewOverlay(slides, previewUrl, currentSlideIndex);
        },

        onPreviewThumbClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const el = e.target.closest('.teksttv-preview-thumb');
            if (!(el instanceof HTMLElement)) return;
            const idx = parseInt(el.dataset.index ?? '', 10);
            slides = getSlides();
            if (!Number.isNaN(idx) && idx >= 0 && idx < slides.length) {
                currentSlideIndex = idx;
                updatePreview();
            }
        },

        onGenerateClick(e: MouseEvent): void {
            const btn = e.currentTarget;
            if (!(btn instanceof HTMLButtonElement) || !config?.generateUrl) return;
            const field = btn.dataset.field;
            if (btn.disabled || !(field === 'title' || field === 'body' || field === 'both')) return;

            if (config.isNewPost) {
                window.alert('Sla de post eerst op voordat je AI-content kunt genereren.');
                return;
            }

            if (teksttvHasExistingGeneratedContent()) {
                if (!window.confirm('Dit overschrijft de huidige tekst. Doorgaan?')) {
                    return;
                }
            }

            requestAiGeneration(config, btn, field, hasSidebarPhoto(config, customImageData), updatePreview);
        },

        onTitleInputMeta(): void {
            updateTeksttvCharCount(config);
            updatePreview();
        },

        insertPlainSeparator(): void {
            const textarea = document.querySelector<HTMLTextAreaElement>('#teksttv_content');
            if (!textarea) return;

            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const needsLeadingBreak = start > 0 && textarea.value[start - 1] !== '\n';
            const hasTrailingBreak = textarea.value[end] === '\n';
            const separator = `${needsLeadingBreak ? '\n' : ''}---${hasTrailingBreak ? '' : '\n'}`;
            textarea.setRangeText(separator, start, end, 'end');
            if (hasTrailingBreak) {
                const caret = textarea.selectionEnd + 1;
                textarea.setSelectionRange(caret, caret);
            }
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.focus();
        },
    };
}
