import { slideDown, slideUp } from '../../modules/dom';

/** Show/hide scheduling fields from block checkbox state. */
export function applySchedulingToggle(el: HTMLInputElement): void {
    const toggle = el.closest('.teksttv-block-scheduling-toggle');
    const sibling = toggle?.nextElementSibling;
    const scheduling =
        sibling instanceof HTMLElement && sibling.matches('.teksttv-block-fields--scheduling') ? sibling : null;
    if (!scheduling) return;
    if (el.checked) {
        slideDown(scheduling, 150);
    } else {
        slideUp(scheduling, 150);
        scheduling.querySelectorAll<HTMLInputElement>('input[type="date"]').forEach((inp) => {
            inp.value = '';
        });
        scheduling.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach((inp) => {
            inp.checked = true;
        });
    }
}
