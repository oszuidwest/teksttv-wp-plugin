import { expect, test } from './test';

test.describe('unconfigured AI administration', () => {
    test('keeps Content & AI and AI Audit unavailable without a supported text generator', async ({ request }) => {
        const [contentResponse, auditResponse] = await Promise.all([
            request.get('/wp-admin/admin.php?page=teksttv-content'),
            request.get('/wp-admin/admin.php?page=teksttv-audit'),
        ]);

        expect(contentResponse.status()).toBe(403);
        expect(auditResponse.status()).toBe(403);
    });
});

test.describe('monthly AI audit', () => {
    test('paginates the selected month and computes statistics across all its posts', async ({
        runWordPressPHP,
        runWordPressPHPFile,
    }) => {
        const seedResult = await runWordPressPHPFile('audit-stats.php');
        expect(seedResult).toContain('audit-stats-ok count=52');

        const html = await runWordPressPHP(`
            require_once ABSPATH . 'wp-admin/includes/template.php';
            $_GET['month'] = '2026-07';
            \\TekstTV\\AuditPage::render_page();
        `);

        expect(html).toContain('name="month" value="2026-07"');
        expect(html).toContain('TekstTV Audit Juli Bewerkt');
        expect(html).toContain('TekstTV Audit Juli Ongewijzigd');
        expect(html).not.toContain('TekstTV Audit Augustus Buiten Selectie');
        expect(html).not.toContain('TekstTV Audit Juli Extra 1</strong>');
        expect(html).toContain('51 items');
        expect(html).toContain('paged=2');
        expect(html).toMatch(/Berichten met AI<\/dt>\s*<dd[^>]*>51<\/dd>/);
        expect(html).toMatch(/Koppen bewerkt<\/dt>\s*<dd[^>]*>0%<\/dd>/);
        expect(html).toMatch(/Teksten bewerkt<\/dt>\s*<dd[^>]*>2%<\/dd>/);
        expect(html).toMatch(/Totaal bewerkt<\/dt>\s*<dd[^>]*>2%<\/dd>/);
        expect(html).toContain('month=2026-07');
    });
});
