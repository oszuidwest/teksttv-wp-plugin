import { reindexNames, siblingFocusTarget } from '../modules/dom';

/** Settings tab: repeatable channel rows. */
export function createChannelsSettingsPage() {
    let channelsTbody: HTMLTableSectionElement | null = null;

    function reindexChannels(): void {
        if (!channelsTbody) return;
        reindexNames(channelsTbody, 'tr', /(teksttv_channels)\[\d+\]/);
    }

    return {
        init(): void {
            channelsTbody = document.querySelector('#teksttv-channels tbody');
        },

        addChannelRow(): void {
            if (!channelsTbody) return;
            const index = channelsTbody.querySelectorAll('tr').length;
            const row =
                '<tr class="teksttv-channel-row">' +
                `<td><input type="text" name="teksttv_channels[${index}][slug]" value="" class="regular-text" pattern="[a-z0-9\\-]+" required placeholder="bijv. tv1" /></td>` +
                `<td><input type="text" name="teksttv_channels[${index}][label]" value="" class="regular-text" required placeholder="bijv. TV 1" /></td>` +
                '<td class="teksttv-table-actions"><button type="button" class="button-link teksttv-remove-channel" aria-label="Kanaal verwijderen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>' +
                '</tr>';
            channelsTbody.insertAdjacentHTML('beforeend', row);
            channelsTbody
                .querySelector<HTMLInputElement>(':scope > .teksttv-channel-row:last-of-type input[name$="[slug]"]')
                ?.focus();
        },

        channelsClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
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
            focusTarget?.focus();
        },
    };
}
