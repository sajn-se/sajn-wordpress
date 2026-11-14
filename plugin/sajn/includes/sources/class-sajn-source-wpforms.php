<?php
/**
 * WPForms (Lite and Pro). To open signing, the plugin swaps the form's confirmation for a
 * redirect, and WPForms sends it after every other completion callback ran.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Source_WPForms extends Sajn_Source {

	/** @var string */
	private $redirect = '';

	public function id() {
		return 'wpforms';
	}

	public function label() {
		return 'WPForms';
	}

	public function is_available() {
		return function_exists( 'wpforms' );
	}

	public function boot() {
		// Last, so the entry, the notifications, and other add-ons run first.
		add_action( 'wpforms_process_complete', array( $this, 'on_submit' ), 999, 4 );
		add_filter( 'wpforms_process_entry_confirmation_redirect_confirmations', array( $this, 'redirect_confirmation' ) );
	}

	public function forms() {
		$forms = array();
		$posts = wpforms()->obj( 'form' )->get( '', array( 'orderby' => 'title' ) );
		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			$forms[ $post->ID ] = $post->post_title;
		}
		return $forms;
	}

	public function fields( $form_id ) {
		$form = wpforms()->obj( 'form' )->get( $form_id, array( 'content_only' => true ) );

		$fields = array();
		foreach ( isset( $form['fields'] ) ? $form['fields'] : array() as $field ) {
			if ( in_array( $field['type'], array( 'divider', 'html', 'pagebreak', 'content', 'captcha' ), true ) ) {
				continue;
			}
			$fields[ 'field_' . $field['id'] ] = isset( $field['label'] ) && '' !== $field['label'] ? $field['label'] : $field['type'];
		}
		return $fields;
	}

	public function on_submit( $fields, $entry, $form_data, $entry_id ) {
		$data = array();
		foreach ( $fields as $id => $field ) {
			$data[ 'field_' . $id ] = isset( $field['value'] ) ? (string) $field['value'] : '';
		}

		$this->redirect = Sajn_Connections::handle_submission( $this->id(), $form_data['id'], $data );
	}

	public function redirect_confirmation( $confirmations ) {
		if ( '' === $this->redirect ) {
			return $confirmations;
		}
		return array(
			1 => array(
				'type'     => 'redirect',
				'redirect' => $this->redirect,
			),
		);
	}
}
