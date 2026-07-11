import Sortable from 'sortablejs';
import { fadeOutRemove, hide, show, slideDown, slideUp } from '../modules/dom';
import type { ImageData, Slide, TeksttvPostConfig, WPTinyMCEEditor } from '../modules/types';
import { encodeSlideData } from '../modules/utils';
import { requestAiGeneration, teksttvHasExistingGeneratedContent } from './postMeta/aiGeneration';
import { buildSlidesFromDom } from './postMeta/buildSlides';
import { updateTeksttvCharCount, updateTeksttvWordCount } from './postMeta/counts';
import { syncDateEndResetButton } from './postMeta/dateEndUi';
import { createExtraImagesOpener } from './postMeta/extraImagesPicker';
import { mountTeksttvPreviewOverlay } from './postMeta/previewOverlay';
import { updatePreviewThumbnails } from './postMeta/previewThumbnails';
import { applySidebarCardState } from './postMeta/sidebarCard';
import { createSidebarCustomPicker } from './postMeta/sidebarCustomPicker';

export function createPostMetaPage() {
    const config: TeksttvPostConfig | undefined = typeof teksttvPost !== 'undefined' ? teksttvPost : undefined;

    let debounceTimer: ReturnType<typeof setTimeout>;
    let currentSlideIndex = 0;
    let slides: Slide[] = [];
    let customImageData: ImageData | null = config?.customImage ? (config.customImage as ImageData) : null;
    let iframeLoadHandler: (() => void) | undefined;

    const previewUrl = config?.previewUrl ?? '';
    const openExtraImages = createExtraImagesOpener();

    function getSlides(): Slide[] {
        return buildSlidesFromDom(config, customImageData);
    }

    function updatePreviewNav(): void {
        const total = slides.length;
        const current = total > 0 ? currentSlideIndex + 1 : 0;

        const counter = document.querySelector('#teksttv-preview-counter');
        if (counter) counter.textContent = `${current} / ${total}`;
        const prevBtn = document.querySelector<HTMLButtonElement>('#teksttv-preview-prev');
        const nextBtn = document.querySelector<HTMLButtonElement>('#teksttv-preview-next');
        if (prevBtn) prevBtn.disabled = currentSlideIndex <= 0;
        if (nextBtn) nextBtn.disabled = currentSlideIndex >= total - 1;

        const thumbs = document.querySelector('#teksttv-preview-thumbs');
        if (thumbs instanceof HTMLElement && previewUrl) {
            updatePreviewThumbnails(thumbs, slides, currentSlideIndex, previewUrl);
        }
    }

    function updatePreview(): void {
        const iframe = document.querySelector<HTMLIFrameElement>('#teksttv-preview-iframe');
        if (!(previewUrl && iframe)) return;

        clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            slides = getSlides();
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
            container?.classList.add('is-loading');
            if (iframeLoadHandler) {
                iframe.removeEventListener('load', iframeLoadHandler);
            }
            iframeLoadHandler = () => container?.classList.remove('is-loading');
            iframe.addEventListener('load', iframeLoadHandler, { once: true });
            iframe.setAttribute(
                'src',
                `${previewUrl}?data=${encodeURIComponent(encodeSlideData(slides[currentSlideIndex]))}`,
            );
        }, 400);
    }

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
            const status = document.querySelector('#teksttv-toggle-status');

            if (!(activeInput && fields && status)) return;

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
                    animation: 150,
                });
            }

            updateTeksttvCharCount(config);

            if (typeof tinymce !== 'undefined') {
                const bindEditor = (editor: WPTinyMCEEditor): void => {
                    editor.on('input change keyup', () => {
                        updatePreview();
                        updateTeksttvWordCount(config);
                    });
                    editor.on('SetContent', () => {
                        updatePreview();
                        updateTeksttvWordCount(config);
                    });
                };
                const existing = tinymce.get('teksttv_content');
                if (existing) bindEditor(existing);
                tinymce.on('AddEditor', (e) => {
                    if (e.editor.id === 'teksttv_content') bindEditor(e.editor);
                });
            }

            document.addEventListener('input', (e) => {
                const t = e.target;
                if (!(t instanceof Element && t.matches('#teksttv_content'))) return;
                updatePreview();
                updateTeksttvWordCount(config);
            });

            document.querySelector('#title')?.addEventListener('input', updatePreview);

            if (config?.aiSupported && config.generateUrl) {
                if (!config.isNewPost) {
                    activeInput.addEventListener('change', () => {
                        if (!activeInput.checked) return;
                        if (teksttvHasExistingGeneratedContent()) return;

                        window.setTimeout(() => {
                            if (window.confirm('Wil je automatisch een kop en tekst genereren?')) {
                                const bothBtn = document.querySelector<HTMLButtonElement>(
                                    '.teksttv-generate-btn[data-field="both"]',
                                );
                                if (bothBtn) requestAiGeneration(config, bothBtn, 'both', updatePreview);
                            }
                        }, 300);
                    });
                }
            }

            window.setTimeout(() => {
                updatePreview();
                updateTeksttvWordCount(config);
            }, 500);
        },

        onActiveChange(): void {
            const activeInput = document.querySelector<HTMLInputElement>('#teksttv-active');
            const fields = document.querySelector<HTMLElement>('#teksttv-fields');
            const status = document.querySelector('#teksttv-toggle-status');
            if (!(activeInput && fields && status)) return;
            const isChecked = activeInput.checked;
            if (isChecked) {
                slideDown(fields, 200);
                status.textContent = 'Actief';
                status.classList.add('is-active');
            } else {
                slideUp(fields, 200);
                status.textContent = 'Inactief';
                status.classList.remove('is-active');
            }
        },

        openExtraImages,

        onExtraImagesClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const tgt = e.target.closest('.teksttv-remove-image');
            const item = tgt?.closest('.teksttv-image-item');
            if (item instanceof HTMLElement) {
                fadeOutRemove(item, 150);
            }
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
            if (!field || btn.disabled) return;

            if (config.isNewPost) {
                window.alert('Sla de post eerst op voordat je AI-content kunt genereren.');
                return;
            }

            if (teksttvHasExistingGeneratedContent()) {
                if (!window.confirm('Dit overschrijft de huidige tekst. Doorgaan?')) {
                    return;
                }
            }

            requestAiGeneration(config, btn, field, updatePreview);
        },

        onTitleInputMeta(): void {
            updateTeksttvCharCount(config);
            updatePreview();
        },
    };
}
