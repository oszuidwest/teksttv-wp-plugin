import { markFormDirty } from '../modules/dirtyForms';
import { cloneTemplate, reindexNames, siblingFocusTarget } from '../modules/dom';
import { removeElementWithUndo } from '../modules/undo';

/** Settings tab: repeatable channel rows. */
export function createChannelsSettingsPage() {
    let channelsTbody: HTMLTableSectionElement | null = null;
    let apiBase = '';
    const copyResetTimers = new WeakMap<HTMLButtonElement, number>();

    // Only called after a user-driven add/remove, so marking dirty here is safe.
    function reindexChannels(): void {
        if (!channelsTbody) return;
        reindexNames(channelsTbody, 'tr', /(teksttv_channels)\[\d+\]/);
        markFormDirty(channelsTbody);
    }

    function updateEndpoint(row: HTMLTableRowElement, slug: string): void {
        const button = row.querySelector<HTMLButtonElement>('.teksttv-copy-endpoint');
        const label = button?.querySelector<HTMLElement>('.teksttv-copy-endpoint-label');
        if (!button || !label) return;

        let endpoint = '';
        const slugInput = row.querySelector<HTMLInputElement>('input[name$="[slug]"]');
        if (apiBase && slug && slugInput?.checkValidity()) {
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
            // Requires a secure context; wp-admin runs on HTTPS (or localhost).
            await navigator.clipboard.writeText(endpoint);
            label.textContent = 'Gekopieerd!';
        } catch {
            label.textContent = 'Kopiëren mislukt';
        }

        window.clearTimeout(copyResetTimers.get(button));
        const timer = window.setTimeout(() => {
            if (button.isConnected) label.textContent = 'Link kopiëren';
            copyResetTimers.delete(button);
        }, 1500);
        copyResetTimers.set(button, timer);
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
            removeElementWithUndo(row, {
                message: 'Kanaal verwijderd.',
                focusAfterRemove: focusTarget,
                focusAfterRestore: (restored) => restored.querySelector('input[name$="[slug]"]'),
                focusUndo: e.detail === 0,
                onRemove: reindexChannels,
                onRestore: reindexChannels,
            });
        },
    };
}
