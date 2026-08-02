import { describe, expect, test } from 'bun:test';
import { AI_SOURCE_BADGE_HTML, getAiGenerationErrorMessage } from '../../resources/ts/alpine/postMeta/aiGeneration';

test('the post-generation badge describes saved provenance', () => {
    expect(AI_SOURCE_BADGE_HTML).toContain('AI-bron opgeslagen');
    expect(AI_SOURCE_BADGE_HTML).not.toContain('AI gegenereerd');
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
