// Drives the local WordPress end to end: saves the API key, sets up a connection for each form
// through the admin screens, submits both forms, and checks that each lands on the sajn signing page.
// Needs `docker compose up -d`, the test forms from `scripts/setup-forms.php`, and sajn on SAJN_APP_URL.
import { mkdirSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';

import { chromium } from 'playwright';

const WP_URL = process.env.WP_URL ?? 'http://localhost:8088';
const SAJN_APP_URL = process.env.SAJN_APP_URL ?? 'http://localhost:3000';
const API_KEY = process.env.SAJN_API_KEY ?? readFileSync(`${homedir()}/.sajn-wp-dev/api-key`, 'utf8').trim();
const TEMPLATE_NAME = process.env.SAJN_TEMPLATE ?? 'WP Offertförfrågan';
const SIGNER_EMAIL = process.env.SIGNER_EMAIL ?? 'andreas@sajn.se';
const SHOTS = new URL('../screenshots/', import.meta.url).pathname;

mkdirSync(SHOTS, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 1900 } });
const shot = (name) => page.screenshot({ path: `${SHOTS}${name}.png` });

await page.goto(`${WP_URL}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin');
await page.click('#wp-submit');
await page.waitForURL(/wp-admin/);

await page.goto(`${WP_URL}/wp-admin/admin.php?page=sajn-settings`);
await page.fill('#sajn-api-key', API_KEY);
await page.click('#submit');
await page.locator('.notice-success.inline').waitFor();
await shot('01-settings');

const connect = async ({ name, form, tags }) => {
    await page.goto(`${WP_URL}/wp-admin/admin.php?page=sajn`);
    const existing = page.getByRole('link', { name, exact: true });
    if (await existing.count()) {
        await existing.first().click();
    } else {
        await page.goto(`${WP_URL}/wp-admin/admin.php?page=sajn&action=edit`);
        await page.fill('#sajn-name', name);
        await page.selectOption('#sajn-form', { label: form });
        await page.selectOption('#sajn-template', { label: TEMPLATE_NAME });
        await page.click('#submit');
    }

    await page.locator('select[name$="[delivery]"]').waitFor();
    await page.fill('input[name$="[name]"]', tags.name);
    await page.fill('input[name$="[email]"]', tags.email);
    await page.fill('input[name$="[phone]"]', tags.phone);
    await page.selectOption('select[name$="[delivery]"]', 'EMAIL');
    await page.selectOption('select[name$="[signature_method]"]', 'CLICK_TO_SIGN');
    await page.fill('#sajn-document-name', `Uppdragsavtal ${tags.name}`);
    await page.fill('input[name="values[kund-namn]"]', tags.name);
    await page.fill('input[name="values[kund-telefon]"]', tags.phone);
    await page.fill('input[name="values[meddelande]"]', tags.message);
    await page.selectOption('#sajn-redirect', { index: 1 });
    await page.check('input[name="active"]');
    await page.click('#submit');
    await page.locator('.notice-success').waitFor();
};

await connect({
    name: 'CF7 offert',
    form: 'Offertförfrågan (CF7)',
    tags: { name: '{your-name}', email: '{your-email}', phone: '{your-phone}', message: '{your-message}' },
});
await shot('02-connection-cf7');

await connect({
    name: 'WPForms offert',
    form: 'Offertförfrågan (WPForms)',
    tags: { name: '{field_1}', email: '{field_2}', phone: '{field_3}', message: '{field_4}' },
});
await shot('03-connection-wpforms');

const expectSigningPage = async (label) => {
    await page.waitForURL(`${SAJN_APP_URL}/sign/**`, { timeout: 60_000 });
    await page.waitForLoadState('networkidle');
    await shot(label);
    console.log(`${label}: ${page.url().replace(/token=.*/, 'token=…')}`);
    return new URL(page.url()).pathname;
};

// A unique run id keeps this run's answers apart from earlier runs, which sajn would replay.
const RUN = Date.now().toString(36);

const submitCf7 = async () => {
    await page.goto(`${WP_URL}/offert-cf7/`);
    await page.fill('input[name="your-name"]', 'Cecilia Formsdotter');
    await page.fill('input[name="your-email"]', SIGNER_EMAIL);
    await page.fill('input[name="your-phone"]', '+46701234567');
    await page.fill('textarea[name="your-message"]', `Fönsterputs, 12 fönster (${RUN})`);
    await page.click('.wpcf7-submit');
};

await submitCf7();
const firstDocument = await expectSigningPage('04-signing-from-cf7');

await submitCf7();
const resubmittedDocument = await expectSigningPage('04b-signing-from-cf7-resubmitted');
if (resubmittedDocument !== firstDocument) {
    throw new Error(`Resubmitting the same answers created a second document: ${firstDocument} and ${resubmittedDocument}`);
}
console.log('resubmission: same document');

await page.goto(`${WP_URL}/offert-wpforms/`);
await page.fill('input[name="wpforms[fields][1]"]', 'Wilma Wpformsson');
await page.fill('input[name="wpforms[fields][2]"]', SIGNER_EMAIL);
await page.fill('input[name="wpforms[fields][3]"]', '+46707654321');
await page.fill('textarea[name="wpforms[fields][4]"]', `Trädgårdsskötsel, maj till september (${RUN})`);
await page.click('.wpforms-submit');
await expectSigningPage('05-signing-from-wpforms');

await page.goto(`${WP_URL}/wp-admin/admin.php?page=sajn-log`);
await shot('06-log');

await browser.close();
