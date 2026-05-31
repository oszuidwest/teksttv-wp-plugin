/** Settings tab: repeatable channel rows. */
export function createChannelsSettingsPage() {
    let channelsTbody: HTMLTableSectionElement | null = null;

    function reindexChannels(): void {
        if (!channelsTbody) return;
        channelsTbody.querySelectorAll('tr').forEach((tr, i) => {
            tr.querySelectorAll('input').forEach((input) => {
                const name = input.getAttribute('name');
                if (name) input.setAttribute('name', name.replace(/\[\d+\]/, `[${i}]`));
            });
        });
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
                '<td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-channel"><span class="dashicons dashicons-trash"></span></button></td>' +
                '</tr>';
            channelsTbody.insertAdjacentHTML('beforeend', row);
        },

        channelsClick(e: MouseEvent): void {
            if (!(e.target instanceof Element)) return;
            const tgt = e.target.closest('.teksttv-remove-channel');
            if (!(tgt instanceof HTMLElement) || !channelsTbody?.contains(tgt)) return;
            tgt.closest('tr')?.remove();
            reindexChannels();
        },
    };
}
