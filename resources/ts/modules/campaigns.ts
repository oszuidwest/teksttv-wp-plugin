/** Campaigns page: group management (add/remove rows). */
export function initCampaignsPage(): void {
    const $ = jQuery;
    const $table = $('#teksttv-groups');
    if (!$table.length) return;

    $('#teksttv-add-group').on('click', () => {
        const row =
            '<tr class="teksttv-group-row">' +
            '<td><input type="text" name="teksttv_campaign_groups[]" value="" class="regular-text" required placeholder="Bijv. Campagne" /></td>' +
            '<td class="teksttv-channel-actions"><button type="button" class="button-link teksttv-remove-group"><span class="dashicons dashicons-trash"></span></button></td>' +
            '</tr>';
        $table.find('tbody').append(row);
    });

    $table.on('click', '.teksttv-remove-group', function () {
        $(this).closest('tr').remove();
    });
}
