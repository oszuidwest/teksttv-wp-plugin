import TomSelect from 'tom-select/base';
import removeButtonPlugin from 'tom-select/plugins/remove_button/plugin.js';

TomSelect.define('remove_button', removeButtonPlugin);

/** Initialize TomSelect on elements within a container. */
export function initTomSelectIn(container: Element | Document = document): void {
    container.querySelectorAll<HTMLSelectElement>('.teksttv-tomselect').forEach((el) => {
        if ((el as unknown as { tomselect?: unknown }).tomselect) return;
        new TomSelect(el, {
            plugins: ['remove_button'],
            placeholder: el.dataset.placeholder || 'Filter…',
            allowEmptyOption: true,
        });
    });
}
