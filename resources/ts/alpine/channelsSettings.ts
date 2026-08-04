import { markFormDirty } from '../modules/dirtyForms';
import { cloneTemplate, reindexNames, siblingFocusTarget } from '../modules/dom';

/** Settings tab: repeatable channel rows. */
export function createChannelsSettingsPage() {
    let channelsTbody: HTMLTableSectionElement | null = null;
    let apiBase = '';

    function reindexChannels(): void {
        if (!channelsTbody) return;
        reindexNames(channelsTbody, 'tr', /(teksttv_channels)\[\d+\]/);
    }

    function updateEndpoint(row: HTMLTableRowElement, slug: string): void {
        const button = row.querySelector<HTMLButtonElement>('.teksttv-copy-endpoint');
        const label = button?.querySelector<HTMLElement>('.teksttv-copy-endpoint-label');
        if (!button || !label) return;

        let endpoint = '';
        if (apiBase && slug) {
            const url = new URL(apiBase, window.location.href);
            url.searchParams.set('channel', slug);
            endpoint = url.toString();
        }
        // Skip the no-op keystrokes: the label is an aria-live region, so an
        // unconditional write would re-announce it on every input event.
        if (button.dataset.endpoint === endpoint) return;

        button.dataset.endpoint = endpoint;
        button.disabled = !endpoint;
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
            const row = cloneTemplate('tmpl-teksttv-channel-row');
            if (!row) return;
            channelsTbody.append(row);
            reindexChannels();
            markFormDirty(channelsTbody);
            row.querySelector<HTMLInputElement>('input[name$="[slug]"]')?.focus();
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
