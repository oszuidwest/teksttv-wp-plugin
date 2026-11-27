import type { TeksttvPostConfig } from '../../modules/types';

/**
 * Add the configured days to the live start date, like PostMeta::default_end_date().
 * Returns '' when there is no default: 0 days, an empty start, or a result past year 9999.
 */
export function getDefaultEndDate(config: TeksttvPostConfig | undefined): string {
    const days = config?.defaultEndDays ?? 0;
    // valueAsNumber is UTC midnight, so the browser time zone cannot shift the day.
    const start = document.querySelector<HTMLInputElement>('#teksttv-date-start')?.valueAsNumber ?? Number.NaN;
    if (days <= 0) return '';
    const end = new Date(start + days * 86_400_000);
    // An empty start is NaN, and toISOString() throws on it. Past 9999 it writes +010000, which slice() breaks.
    return end.getUTCFullYear() <= 9999 ? end.toISOString().slice(0, 10) : '';
}

/** Show reset only when there is a default end date and it differs from the field. */
export function syncDateEndResetButton(config: TeksttvPostConfig | undefined): void {
    const defaultEndDate = getDefaultEndDate(config);
    const dateEnd = document.querySelector<HTMLInputElement>('#teksttv-date-end');
    const dateResetBtn = document.querySelector<HTMLButtonElement>('#teksttv-date-end-reset');
    if (!(dateEnd && dateResetBtn)) return;
    if (defaultEndDate && dateEnd.value !== defaultEndDate) {
        dateResetBtn.classList.remove('is-hidden');
    } else {
        dateResetBtn.classList.add('is-hidden');
    }
}
