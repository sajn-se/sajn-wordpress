=== sajn E-Signature ===
Contributors: sajn
Tags: e-signature, bankid, contact form 7, wpforms, gravity forms
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send a sajn document for signing when a visitor submits a form.

== Description ==

Connect a Contact Form 7, WPForms, or Gravity Forms form to a sajn template. Each time someone submits the form, sajn creates a document from the template, fills it in with the answers, and sends it for signing. The visitor can go straight to the signing page.

* Fill in the template's parties and fields with form tags, such as `{your-name}`.
* Choose the invitation channel and the signature method per party: BankID, MitID, drawn, click to sign, and more.
* A failed submission never breaks the form. The plugin's log shows what went wrong.

You need a sajn account on the Team plan or higher and an API key. WPForms connections need WPForms 1.9.8.6 or later.

== Installation ==

1. Install and activate the plugin.
2. In sajn, go to Settings → Organization → Developer → API keys and create a key.
3. In WordPress, go to sajn → Settings and paste the key.
4. Go to sajn → Connections and add a connection for your form.

== External services ==

The plugin sends form answers to sajn (https://sajn.se) to create and send the document. It calls the sajn API only when an admin opens the plugin's screens and when someone submits a connected form. sajn's terms: https://sajn.se/allmanna-villkor. Privacy policy: https://sajn.se/integritetspolicy.

== Changelog ==

= 0.1.0 =
* First version: Contact Form 7, WPForms, and Gravity Forms connections.
