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
}

/** WordPress TinyMCE editor instance (partial) */
export interface WPTinyMCEEditor {
    id: string;
    getContent(): string;
    isHidden(): boolean;
    on(event: string, callback: () => void): void;
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

/** WordPress global (partial — media library) */
interface WPGlobal {
    media(options: any): any;
}

declare global {
    const wp: WPGlobal;
    interface Window {
        teksttvPost?: TeksttvPostConfig;
        tinymce?: WPTinyMCE;
        TomSelect: typeof import('tom-select').default;
    }

    const teksttvPost: TeksttvPostConfig | undefined;
    const tinymce: WPTinyMCE | undefined;
    const TomSelect: typeof import('tom-select').default;
}
