/** Image data with optional caption/attribution */
export interface ImageData {
    url: string;
    caption?: string;
    attribution?: string;
}

/** Text slide for preview */
export interface TextSlide {
    type: 'text';
    duration: number;
    title: string;
    body: string;
    image?: ImageData;
}

/** Image slide for preview */
export interface ImageSlide {
    type: 'image';
    duration: number;
    url: string;
}

export type Slide = TextSlide | ImageSlide;

/** Config passed from PHP via wp_localize_script */
export interface TeksttvPostConfig {
    previewUrl: string;
    nonce: string;
    restNonce: string;
    imageDataUrl: string;
    defaultEndDate: string;
    fallbackImage: ImageData | '';
    customImage: ImageData | '';
    generateUrl: string;
    aiSupported: boolean;
    postId: number;
    isNewPost: boolean;
    titleCharLimit: number;
    wordLimit: number;
    wordLimitPhoto: number;
    hasAiContent: boolean;
}

/** WordPress TinyMCE editor instance (partial) */
export interface WPTinyMCEEditor {
    id: string;
    getContent(): string;
    setContent(content: string): void;
    isHidden(): boolean;
    on(event: string, callback: () => void): void;
    fire(event: string): void;
}

/** WordPress TinyMCE global (partial) */
export interface WPTinyMCE {
    get(id: string): WPTinyMCEEditor | null;
    on(event: string, callback: (e: { editor: WPTinyMCEEditor }) => void): void;
}

/** WordPress media frame (partial) */
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

/** WordPress media frame instance (partial) */
export interface WPMediaFrame {
    open(): void;
    on(event: string, callback: () => void): void;
    state(): {
        get(key: string): {
            toJSON(): WPMediaAttachment[];
            first(): { toJSON(): WPMediaAttachment };
        };
    };
}

/** Options for creating a WordPress media frame */
export interface WPMediaOptions {
    title?: string;
    button?: { text: string };
    multiple?: boolean;
    library?: { type: string };
}

/** WordPress global (partial — media library) */
interface WPGlobal {
    media(options: WPMediaOptions): WPMediaFrame;
}

/** Underscore.js subset used by wp.media */
interface WPUnderscore {
    defaults(object: Record<string, unknown>, ...sources: Record<string, unknown>[]): Record<string, unknown>;
}

declare global {
    const wp: WPGlobal;
    interface Window {
        teksttvPost?: TeksttvPostConfig;
        tinymce?: WPTinyMCE;
        TomSelect: typeof import('tom-select').default;
        /** Set by PHP inline script on the `underscore` handle. */
        wpUnderscore?: WPUnderscore;
        _: WPUnderscore;
    }

    const teksttvPost: TeksttvPostConfig | undefined;
    const tinymce: WPTinyMCE | undefined;
    const TomSelect: typeof import('tom-select').default;
}
