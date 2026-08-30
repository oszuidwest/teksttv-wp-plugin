export interface ImageData {
    url: string;
    caption?: string;
    attribution?: string;
}

export interface TextSlide {
    type: 'text';
    duration: number;
    title: string;
    body: string;
    image?: ImageData;
}

export interface ImageSlide {
    type: 'image';
    duration: number;
    url: string;
}

export type Slide = TextSlide | ImageSlide;

/** PHP-provided runtime config. */
export interface TeksttvPostConfig {
    previewUrl: string;
    imageDataUrl: string;
    defaultEndDate: string;
    fallbackImage: ImageData | '';
    customImage: ImageData | '';
    generateUrl: string;
    aiSupported: boolean;
    postId: number;
    isNewPost: boolean;
    fallbackTitle: string;
    titleCharLimit: number;
    wordLimit: number;
    wordLimitPhoto: number;
    pageSeparator: boolean;
}

export interface WPTinyMCEEditor {
    id: string;
    getContent(): string;
    setContent(content: string, options?: { no_events?: boolean }): void;
    isHidden(): boolean;
    on(event: string, callback: () => void): void;
    fire(event: string): void;
}

export interface WPTinyMCE {
    get(id: string): WPTinyMCEEditor | null;
    on(event: string, callback: (e: { editor: WPTinyMCEEditor }) => void): void;
    init(settings: Record<string, unknown>): void;
}

/** WordPress' pre-init editor configuration, keyed by editor id. */
export interface WPTinyMCEPreInit {
    mceInit: Record<string, Record<string, unknown>>;
}

export interface WPMediaAttachment {
    id: number;
    url: string;
    caption: string;
    sizes?: {
        thumbnail?: { url: string };
        medium?: { url: string };
        large?: { url: string };
    };
}

export interface WPMediaFrame {
    open(): void;
    on(event: string, callback: () => void): void;
    state(): {
        get(key: string): {
            toJSON(): WPMediaAttachment[];
            first(): { toJSON(): WPMediaAttachment } | undefined;
        };
    };
}

export interface WPMediaOptions {
    title?: string;
    button?: { text: string };
    multiple?: boolean;
    library?: { type: string };
}

interface WPGlobal {
    media(options: WPMediaOptions): WPMediaFrame;
    apiFetch<T>(options: { url: string; method?: 'GET' | 'POST'; data?: Record<string, unknown> }): Promise<T>;
    data?: {
        select(store: 'core/editor'): {
            getEditedPostAttribute(attribute: 'title' | 'content'): unknown;
        } | null;
    };
}

interface WPUnderscore {
    defaults(object: Record<string, unknown>, ...sources: Record<string, unknown>[]): Record<string, unknown>;
}

declare global {
    const wp: WPGlobal;
    interface Window {
        teksttvPost?: TeksttvPostConfig;
        tinymce?: WPTinyMCE;
        tinyMCEPreInit?: WPTinyMCEPreInit;
        /** Set by PHP inline script on the `underscore` handle. */
        wpUnderscore?: WPUnderscore;
        _: WPUnderscore;
    }

    const teksttvPost: TeksttvPostConfig | undefined;
    const tinymce: WPTinyMCE | undefined;
    const tinyMCEPreInit: WPTinyMCEPreInit | undefined;
}
