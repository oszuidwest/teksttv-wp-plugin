import { describe, expect, test } from 'bun:test';
import { getAiGenerationErrorMessage } from '../../resources/ts/alpine/postMeta/aiGeneration';

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
