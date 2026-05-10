import type { TeksttvPostConfig } from '../../modules/types';

/** Toon/verberg “standaard einddatum”-knop bij handmatige wijziging. */
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
