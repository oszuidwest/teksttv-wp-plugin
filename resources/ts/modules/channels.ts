/** Channels settings page: add/remove channel rows. */
export function initChannelsPage(): void {
    const $table = jQuery('#teksttv-channels');
    if (!$table.length) return;

    jQuery('#teksttv-add-channel').on('click', () => {
        const index = $table.find('tbody tr').length;
        const row =
            '<tr class="teksttv-channel-row">' +
            `<td><input type="text" name="teksttv_channels[${index}][slug]" value="" class="regular-text" pattern="[a-z0-9\\-]+" required placeholder="bijv. tv1" /></td>` +
            `<td><input type="text" name="teksttv_channels[${index}][label]" value="" class="regular-text" required placeholder="bijv. TV 1" /></td>` +
            '<td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-channel"><span class="dashicons dashicons-trash"></span></button></td>' +
            '</tr>';
        $table.find('tbody').append(row);
    });

    $table.on('click', '.teksttv-remove-channel', function () {
        jQuery(this).closest('tr').remove();
        reindexChannels();
    });

    function reindexChannels(): void {
        $table.find('tbody tr').each(function (i) {
            jQuery(this)
                .find('input')
                .each(function () {
                    const name = jQuery(this).attr('name');
                    if (name) {
                        jQuery(this).attr('name', name.replace(/\[\d+\]/, `[${i}]`));
                    }
                });
        });
    }
}
