<?php
/**
 * Creates the two test forms and their pages that `scripts/smoke.mjs` submits.
 * Run with: docker compose run --rm -T -v "$PWD/scripts:/scripts" cli wp --user=admin eval-file /scripts/setup-forms.php
 *
 * @package sajn
 */

$cf7 = WPCF7_ContactForm::get_template( array( 'title' => 'Offertförfrågan (CF7)' ) );
$cf7->set_properties(
	array(
		'form'                => "<label>Namn [text* your-name autocomplete:name]</label>\n<label>E-post [email* your-email]</label>\n<label>Telefon [tel your-phone]</label>\n<label>Uppdrag [textarea your-message]</label>\n[submit \"Skicka\"]",
		// The container has no mail server.
		'additional_settings' => 'skip_mail: on',
	)
);
$cf7_id = $cf7->save();

$wpforms_id         = wpforms()->obj( 'form' )->add( 'Offertförfrågan (WPForms)' );
$data               = wpforms()->obj( 'form' )->get( $wpforms_id, array( 'content_only' => true ) );
$data['id']         = $wpforms_id;
$data['field_id']   = 5;
$data['fields']     = array(
	1 => array( 'id' => 1, 'type' => 'name', 'label' => 'Namn', 'format' => 'simple', 'required' => '1' ),
	2 => array( 'id' => 2, 'type' => 'email', 'label' => 'E-post', 'required' => '1' ),
	3 => array( 'id' => 3, 'type' => 'text', 'label' => 'Telefon' ),
	4 => array( 'id' => 4, 'type' => 'textarea', 'label' => 'Uppdrag' ),
);
$data['settings']['form_title']          = 'Offertförfrågan (WPForms)';
$data['settings']['ajax_submit']         = '1';
$data['settings']['notification_enable'] = '0';
$data['settings']['submit_text']         = 'Skicka';
wpforms()->obj( 'form' )->update( $wpforms_id, $data );

wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Offert CF7', 'post_name' => 'offert-cf7', 'post_content' => '[contact-form-7 id="' . $cf7_id . '"]' ) );
wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Offert WPForms', 'post_name' => 'offert-wpforms', 'post_content' => '[wpforms id="' . $wpforms_id . '"]' ) );

echo "Contact Form 7 form $cf7_id and WPForms form $wpforms_id are at /offert-cf7/ and /offert-wpforms/.\n";
