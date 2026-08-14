import { describe, expect, test } from 'bun:test';
import { getAiGenerationErrorMessage } from '../../resources/ts/alpine/postMeta/aiGeneration';
import { getCurrentPostEditorState } from '../../resources/ts/alpine/postMeta/editorContent';

function setGlobal(name: string, value: unknown): void {
    Object.defineProperty(globalThis, name, { configurable: true, value });
}

test('reads unsaved Gutenberg title and content from the core editor store', () => {
    setGlobal('wp', {
        data: {
            select: () => ({
                getEditedPostAttribute: (attribute: string) =>
                    attribute === 'title' ? 'Onopgeslagen titel' : '<p>Onopgeslagen inhoud</p>',
            }),
        },
    });
    setGlobal('document', { querySelector: () => null });

    expect(getCurrentPostEditorState()).toEqual({
        title: 'Onopgeslagen titel',
        content: '<p>Onopgeslagen inhoud</p>',
    });
});

test('falls back to the visible Classic Editor TinyMCE instance', () => {
    setGlobal('wp', { data: { select: () => null } });
    setGlobal('document', {
        querySelector: (selector: string) =>
            selector === '#title' ? { value: 'Classic titel' } : { value: 'Text-modus inhoud' },
    });
    setGlobal('tinymce', {
        get: () => ({ isHidden: () => false, getContent: () => '<p>TinyMCE inhoud</p>' }),
    });

    expect(getCurrentPostEditorState()).toEqual({
        title: 'Classic titel',
        content: '<p>TinyMCE inhoud</p>',
    });
});

test('falls back to the Classic Editor when Gutenberg state is incomplete', () => {
    setGlobal('wp', {
        data: {
            select: () => ({
                getEditedPostAttribute: (attribute: string) => (attribute === 'title' ? 'Gutenberg titel' : undefined),
            }),
        },
    });
    setGlobal('document', {
        querySelector: (selector: string) =>
            selector === '#title' ? { value: 'Classic titel' } : { value: 'Classic inhoud' },
    });
    setGlobal('tinymce', { get: () => null });

    expect(getCurrentPostEditorState()).toEqual({
        title: 'Classic titel',
        content: 'Classic inhoud',
    });
});

test('blocks generation when no authoritative editor is available', () => {
    setGlobal('wp', { data: { select: () => null } });
    setGlobal('document', { querySelector: () => null });
    setGlobal('tinymce', { get: () => null });

    expect(getCurrentPostEditorState()).toBeNull();
});

describe('getAiGenerationErrorMessage', () => {
    test('shows REST messages and hides API Fetch network messages', () => {
        expect(getAiGenerationErrorMessage({ message: 'Genereren mislukt.', data: { status: 500 } })).toBe(
            'Genereren mislukt.',
        );
        expect(getAiGenerationErrorMessage({ message: 'Could not get a valid response from the server.' })).toBe(
            'Er ging iets mis bij het genereren.',
        );
    });
});
