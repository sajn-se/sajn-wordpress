<?php
/**
 * Contact Form 7. The document goes out only after CF7 finished the submission, so an aborted
 * or failed one sends nothing. CF7 submits over its REST API, so the signing URL travels back in
 * the feedback response and `assets/sajn-cf7.js` opens it.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Source_CF7 extends Sajn_Source {

	/** @var string */
	private $redirect = '';

	public function id() {
		return 'cf7';
	}

	public function label() {
		return 'Contact Form 7';
	}

	public function is_available() {
		return class_exists( 'WPCF7_ContactForm' );
	}

	public function boot() {
		add_action( 'wpcf7_mail_sent', array( $this, 'on_submit' ) );
		add_filter( 'wpcf7_feedback_response', array( $this, 'add_redirect' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function forms() {
		$forms = array();
		foreach ( WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) ) as $form ) {
			$forms[ $form->id() ] = $form->title();
		}
		return $forms;
	}

	public function fields( $form_id ) {
		$form = WPCF7_ContactForm::get_instance( $form_id );
		if ( ! $form ) {
			return array();
		}

		$fields = array();
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( '' !== $tag->name ) {
				$fields[ $tag->name ] = $tag->basetype;
			}
		}
		return $fields;
	}

	/**
	 * @param WPCF7_ContactForm $form
	 */
	public function on_submit( $form ) {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$data = array();
		foreach ( $submission->get_posted_data() as $name => $value ) {
			$data[ $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		$this->redirect = Sajn_Connections::handle_submission( $this->id(), $form->id(), $data );
	}

	public function add_redirect( $response ) {
		if ( '' !== $this->redirect ) {
			$response['sajn_redirect'] = $this->redirect;
		}
		return $response;
	}

	public function enqueue() {
		wp_enqueue_script( 'sajn-cf7', plugins_url( 'assets/sajn-cf7.js', SAJN_PLUGIN_FILE ), array(), SAJN_VERSION, true );
	}
}
