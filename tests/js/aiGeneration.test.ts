import { afterEach, describe, expect, test } from 'bun:test';
import { getAiGenerationErrorMessage, requestAiGeneration } from '../../resources/ts/alpine/postMeta/aiGeneration';
import type { TeksttvPostConfig } from '../../resources/ts/modules/types';

const originalDocument = globalThis.document;
const originalWindow = globalThis.window;
const originalWp = (globalThis as unknown as { wp: typeof wp }).wp;

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.window = originalWindow;
    Object.defineProperty(globalThis, 'wp', { configurable: true, value: originalWp });
});

test('successful generation does not add an AI badge', async () => {
    let insertedElement = false;
    const statusEl = {
        classList: { add() {}, remove() {} },
        insertAdjacentElement() {
            insertedElement = true;
        },
        textContent: '',
    };
    globalThis.document = {
        querySelector: (selector: string) => (selector === '#teksttv-generate-status' ? statusEl : null),
    } as unknown as Document;
    globalThis.window = {
        clearInterval() {},
        setInterval: () => 1,
    } as unknown as Window & typeof globalThis;
    Object.defineProperty(globalThis, 'wp', {
        configurable: true,
        value: { apiFetch: () => Promise.resolve({}) },
    });

    requestAiGeneration(
        { generateUrl: '/generate', postId: 42 } as TeksttvPostConfig,
        { disabled: false, innerHTML: 'Genereren' } as HTMLButtonElement,
        'body',
        false,
    );
    await Promise.resolve();
    await Promise.resolve();

    expect(insertedElement).toBe(false);
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
