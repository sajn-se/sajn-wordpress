# sajn for WordPress

A WordPress plugin that sends a sajn document for signing when a visitor submits a form. It works with Contact Form 7 and WPForms. The plugin lives in `plugin/sajn/`. Everything else in this repo is the local test setup.

## How the plugin works

An admin saves an API key under **sajn → Settings** and adds a connection under **sajn → Connections**. A connection names a form, a sajn template, and how the form fills in the template:

- Each template party gets a name, an email, and a phone, written as form tags such as `{your-name}`. A party left empty keeps the details saved in the template.
- Each sender-filled key in the template gets a value. The keys come from FORM subfields and boxes placed on a PDF. Keys the lookup misses go in **Other keys**, one `key = {tag}` per line.
- **Open signing** sends the visitor straight to the signing page of one party.

On submit, the plugin makes one `POST /api/v1/documents` call with `templateId`, `parties` (by `templatePartyId`), `fieldValues`, and `send: true`. To open signing, it reads the party's `signingUrl` from `GET /api/v1/documents/{id}/parties/{partyId}`. A failed call never blocks the form: the form shows its own confirmation and **sajn → Log** records the error with the API request ID.

The plugin pins `Sajn-Version: 2026-10`. It needs a sajn organization on the Team plan or higher, because the public API starts there.

## Run it locally

You need Docker and the local sajn stack from `sajn-app` (`./dev up app`).

1. Start WordPress on http://localhost:8088. The plugin folder is mounted into the container, so edits apply on reload.

	```bash
	docker compose up -d
	bin/wp core install --url=http://localhost:8088 --title="sajn WP dev" --admin_user=admin --admin_password=admin --admin_email=andreas@sajn.se --skip-email
	bin/wp plugin install contact-form-7 wpforms-lite --activate
	bin/wp plugin activate sajn
	bin/wp option update permalink_structure '/%postname%/'
	```

2. Create the test forms and their pages.

	```bash
	docker compose run --rm -T -v "$PWD/scripts:/scripts" cli wp --user=admin eval-file /scripts/setup-forms.php
	```

3. Create an API key in your local sajn and save it to `~/.sajn-wp-dev/api-key` (mode 600). Create a template named `WP Offertförfrågan` with one party and the FORM keys `kund-namn`, `kund-telefon`, and `meddelande`.

4. Run the smoke test. It saves the key and both connections through the admin screens, submits both forms, and checks that each one lands on the sajn signing page. Screenshots go to `screenshots/`.

	```bash
	pnpm install
	pnpm smoke
	```

WordPress reaches sajn at `http://host.docker.internal:3000`. To point it somewhere else, set `SAJN_BASE_URL` before `docker compose up`. In production the plugin defaults to `https://app.sajn.se`, and a site can override it with `define( 'SAJN_BASE_URL', '…' )` in `wp-config.php`.

## Translations

Source strings are English. `plugin/sajn/languages/` holds the template and the Swedish translation. After you change a string, regenerate the template and the compiled file:

```bash
bin/wp i18n make-pot wp-content/plugins/sajn wp-content/plugins/sajn/languages/sajn.pot --domain=sajn
bin/wp i18n make-mo wp-content/plugins/sajn/languages
```
