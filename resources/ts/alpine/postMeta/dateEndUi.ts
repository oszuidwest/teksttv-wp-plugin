import type { TeksttvPostConfig } from '../../modules/types';

/** Show the reset button only while the end date differs from the configured default. */
export function syncDateEndResetButton(config: TeksttvPostConfig | undefined): void {
    const defaultEndDate = config?.defaultEndDate ?? '';
    const dateEnd = document.querySelector<HTMLInputElement>('#teksttv-date-end');
    const dateResetBtn = document.querySelector<HTMLButtonElement>('#teksttv-date-end-reset');
    if (!(dateEnd && dateResetBtn)) return;
    if (defaultEndDate && dateEnd.value !== defaultEndDate) {
        dateResetBtn.classList.remove('is-hidden');
    } else {
        dateResetBtn.classList.add('is-hidden');
    }
}
