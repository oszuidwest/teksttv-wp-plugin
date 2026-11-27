import { expect, test } from 'bun:test';
import { getDefaultEndDate } from '../../resources/ts/alpine/postMeta/dateEndUi';
import type { TeksttvPostConfig } from '../../resources/ts/modules/types';

function defaultEnd(start: string, days: number): string {
    const valueAsNumber = start ? Date.parse(`${start}T00:00:00Z`) : Number.NaN;
    Object.defineProperty(globalThis, 'document', {
        configurable: true,
        value: { querySelector: () => ({ valueAsNumber }) },
    });
    return getDefaultEndDate({ defaultEndDays: days } as TeksttvPostConfig);
}

test('adds the configured days to the start date, or offers no default', () => {
    expect(defaultEnd('2099-01-01', 7)).toBe('2099-01-08');
    expect(defaultEnd('', 7)).toBe('');
    expect(defaultEnd('2099-01-01', 0)).toBe('');
    // toISOString() writes year 10000 as +010000; reset must not put that in the end field.
    expect(defaultEnd('9999-12-31', 7)).toBe('');
});
