import { describe, expect, test } from 'bun:test';
import { getAiGenerationErrorMessage, getAutomaticAiGeneration } from '../../resources/ts/alpine/postMeta/aiGeneration';

describe('getAutomaticAiGeneration', () => {
    test('includes the title when the combined action exposes it', () => {
        expect(getAutomaticAiGeneration('both')).toEqual({
            field: 'both',
            confirmation: 'Wil je automatisch een kop en tekst genereren?',
        });
    });

    test('falls back to body-only generation without a custom title action', () => {
        expect(getAutomaticAiGeneration('body')).toEqual({
            field: 'body',
            confirmation: 'Wil je automatisch tekst genereren?',
        });
    });
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
