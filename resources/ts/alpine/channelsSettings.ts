import { markFormDirty } from '../modules/dirtyForms';
import { reindexNames, siblingFocusTarget } from '../modules/dom';

/** Settings tab: repeatable channel rows. */
export function createChannelsSettingsPage() {
    let channelsTbody: HTMLTableSectionElement | null = null;
    let apiBase = '';

    function reindexChannels(): void {
        if (!channelsTbody) return;
        reindexNames(channelsTbody, 'tr', /(teksttv_channels)\[\d+\]/);
        channelsTbody.querySelectorAll<HTMLTableRowElement>('.teksttv-channel-row').forEach((row, index) => {
            (['slug', 'label'] as const).forEach((field) => {
                const input = row.querySelector<HTMLInputElement>(`input[name$="[${field}]"]`);
                const label = input?.previousElementSibling;
                if (!input || !(label instanceof HTMLLabelElement)) return;
                const id = `teksttv-channel-${index}-${field}`;
                input.id = id;
                label.htmlFor = id;
            });
        });
    }

    function updateEndpoint(row: HTMLTableRowElement, slug: string): void {
        const button = row.querySelector<HTMLButtonElement>('.teksttv-copy-endpoint');
        const label = button?.querySelector<HTMLElement>('.teksttv-copy-endpoint-label');
        if (!button || !label) return;

        if (!apiBase || !slug) {
            button.dataset.endpoint = '';
            button.disabled = true;
            label.textContent = 'Link kopiëren';
            return;
        }

        const endpoint = new URL(apiBase, window.location.href);
        endpoint.searchParams.set('channel', slug);
        button.dataset.endpoint = endpoint.toString();
        button.disabled = false;
        label.textContent = 'Link kopiëren';
    }

    async function copyEndpoint(button: HTMLButtonElement): Promise<void> {
        const endpoint = button.dataset.endpoint;
        const label = button.querySelector<HTMLElement>('.teksttv-copy-endpoint-label');
        if (!endpoint || !label) return;

        try {
            let copied = false;
            if (navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(endpoint);
                    copied = true;
                } catch {
                    // Fall through to the legacy command for older WP admin
                    // contexts and browsers that deny Clipboard API access.
                }
            }
            if (!copied) {
                const field = document.createElement('textarea');
                field.value = endpoint;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.append(field);
                field.select();
                copied = document.execCommand('copy');
                field.remove();
                if (!copied) throw new Error('Copy command failed');
            }
            label.textContent = 'Gekopieerd!';
        } catch {
            label.textContent = 'Kopiëren mislukt';
        }

        window.setTimeout(() => {
            if (button.isConnected) label.textContent = 'Link kopiëren';
        }, 1500);
    }

    return {
        init(): void {
            channelsTbody = document.querySelector('#teksttv-channels tbody');
            apiBase = document.querySelector<HTMLTableElement>('#teksttv-channels')?.dataset.apiBase ?? '';
        },

        addChannelRow(): void {
            if (!channelsTbody) return;
            const index = channelsTbody.querySelectorAll('tr').length;
            const row =
                '<tr class="teksttv-channel-row">' +
                `<td><label class="teksttv-mobile-field-label" for="teksttv-channel-${index}-slug">Slug</label><input type="text" id="teksttv-channel-${index}-slug" name="teksttv_channels[${index}][slug]" value="" class="regular-text" pattern="[a-z0-9\\-]+" required placeholder="bijv. tv1" autocomplete="off" spellcheck="false" /></td>` +
                `<td><label class="teksttv-mobile-field-label" for="teksttv-channel-${index}-label">Naam</label><input type="text" id="teksttv-channel-${index}-label" name="teksttv_channels[${index}][label]" value="" class="regular-text" required placeholder="bijv. TV 1" autocomplete="off" /></td>` +
                '<td class="teksttv-channel-endpoint"><button type="button" class="button teksttv-copy-endpoint" data-endpoint="" disabled><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><span class="teksttv-copy-endpoint-label" aria-live="polite">Link kopiëren</span></button></td>' +
                '<td class="teksttv-table-actions"><button type="button" class="button-link button-link-delete teksttv-remove-channel" aria-label="Kanaal verwijderen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>' +
                '</tr>';
            channelsTbody.insertAdjacentHTML('beforeend', row);
            markFormDirty(channelsTbody);
            channelsTbody
                .querySelector<HTMLInputElement>(':scope > .teksttv-channel-row:last-of-type input[name$="[slug]"]')
                ?.focus();
        },

        channelsInput(e: Event): void {
            if (!(e.target instanceof HTMLInputElement) || !e.target.name.endsWith('[slug]')) return;
            const row = e.target.closest<HTMLTableRowElement>('.teksttv-channel-row');
            if (!row) return;
            updateEndpoint(row, e.target.value);
        },

        channelsClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const copyButton = e.target.closest<HTMLButtonElement>('.teksttv-copy-endpoint');
            if (copyButton && channelsTbody?.contains(copyButton)) {
                void copyEndpoint(copyButton);
                return;
            }

            const tgt = e.target.closest('.teksttv-remove-channel');
            if (!(tgt instanceof HTMLElement) || !channelsTbody?.contains(tgt)) return;
            const row = tgt.closest('tr');
            if (!row) return;
            const focusTarget = siblingFocusTarget(
                row,
                'input[name$="[slug]"]',
                document.querySelector<HTMLElement>('#teksttv-add-channel'),
            );
            row.remove();
            reindexChannels();
            markFormDirty(channelsTbody);
            focusTarget?.focus();
        },
    };
}
