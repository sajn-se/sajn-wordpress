# sajn for WordPress

A WordPress plugin that sends a sajn document for signing when a visitor submits a form. It works with Contact Form 7, WPForms (1.9.8.6 or later), and Gravity Forms.

## Install the plugin

You need a sajn organization on the Team plan or higher.

1. Download `sajn.zip` from the [latest release](https://github.com/sajn-se/sajn-wordpress/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload the zip, and activate the plugin.
3. In sajn, go to **Settings → Organization → Developer → API keys** and create a key.
4. In WordPress, go to **sajn → Settings** and paste the key.
5. Go to **sajn → Connections**, click **Add connection**, and choose a form and a sajn template. Click **Continue**.
6. Fill in the template's parties and fields with form tags, such as `{your-name}`. To send the visitor straight to signing, choose a party under **Open signing**.
7. Select **Send a document on every submission** and click **Save connection**.

**sajn → Log** lists the last 50 submissions and why any of them failed. A failed call never blocks the form: the form shows its own confirmation.

## How it works

On submit, the plugin makes one `POST /api/v1/documents` call with `templateId`, `parties` (by `templatePartyId`), `fieldValues`, and `send: true`. It sends an `Idempotency-Key` built from the connection and the answers, so sajn returns the same document when the same answers arrive again within 24 hours. To open signing, it reads the party's `signingUrl` from `GET /api/v1/documents/{id}/parties/{partyId}`.

The keys come from FORM subfields and boxes placed on a PDF in the template. Keys the lookup misses go in **Other keys**, one `key = {tag}` per line.

The plugin pins `Sajn-Version: 2026-10`. A site can point it at another sajn with `define( 'SAJN_BASE_URL', '…' )` in `wp-config.php`.

## Develop

The plugin lives in `plugin/sajn/`. The rest of the repo runs it in Docker against a local sajn (`./dev up app` in `sajn-app`).

1. Start WordPress on http://localhost:8088. The plugin folder is mounted into the container, so edits apply on reload.

	```bash
	docker compose up -d
	bin/wp core install --url=http://localhost:8088 --title="sajn WP dev" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
	bin/wp plugin install contact-form-7 wpforms-lite --activate
	bin/wp plugin activate sajn
	bin/wp option update permalink_structure '/%postname%/'
	```

2. Create the test forms and their pages.

	```bash
	docker compose run --rm -T -v "$PWD/scripts:/scripts" cli wp --user=admin eval-file /scripts/setup-forms.php
	```

3. Create an API key in your local sajn and save it to `~/.sajn-wp-dev/api-key` (mode 600). Create a template named `WP Offertförfrågan` with one party and the FORM keys `kund-namn`, `kund-telefon`, and `meddelande`.

4. Run the smoke test. It saves the key and both connections through the admin screens, submits both forms twice, and checks that each lands on the sajn signing page and that a resubmission returns the same document. Screenshots go to `screenshots/`.

	```bash
	pnpm install
	SIGNER_EMAIL=you@example.com pnpm smoke
	```

WordPress reaches sajn at `http://host.docker.internal:3000`. Set `SAJN_BASE_URL` before `docker compose up` to point it somewhere else.

Gravity Forms is paid, so the smoke test doesn't cover it.

### Check and release

Run WordPress's Plugin Check before a release. It should report no errors:

```bash
bin/wp plugin install plugin-check --activate
bin/wp plugin check sajn
```

Build the zip from `plugin/` so it unpacks to a `sajn/` folder:

```bash
(cd plugin && zip -rq ../sajn.zip sajn -x '*.DS_Store')
```

### Translations

Source strings are English. `plugin/sajn/languages/` holds the template and the Swedish translation. After you change a string, update both and compile:

```bash
bin/wp i18n make-pot wp-content/plugins/sajn wp-content/plugins/sajn/languages/sajn.pot --domain=sajn
bin/wp i18n update-po wp-content/plugins/sajn/languages/sajn.pot wp-content/plugins/sajn/languages/sajn-sv_SE.po
bin/wp i18n make-mo wp-content/plugins/sajn/languages
```
