<?php
/**
 * Gravity Forms. The document goes out while Gravity Forms builds the confirmation, after the
 * entry is saved, so opening signing can replace the confirmation with a redirect.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Source_Gravity_Forms extends Sajn_Source {

	const SKIPPED_TYPES = array( 'html', 'section', 'page', 'captcha' );

	public function id() {
		return 'gravityforms';
	}

	public function label() {
		return 'Gravity Forms';
	}

	public function is_available() {
		return class_exists( 'GFAPI' );
	}

	public function boot() {
		add_filter( 'gform_confirmation', array( $this, 'on_submit' ), 10, 3 );
	}

	public function forms() {
		$forms = array();
		foreach ( GFAPI::get_forms() as $form ) {
			$forms[ $form['id'] ] = $form['title'];
		}
		return $forms;
	}

	public function fields( $form_id ) {
		$form = GFAPI::get_form( $form_id );

		$fields = array();
		foreach ( $form ? $form['fields'] : array() as $field ) {
			if ( in_array( $field->type, self::SKIPPED_TYPES, true ) ) {
				continue;
			}
			$fields[ 'field_' . $field->id ] = '' !== $field->label ? $field->label : $field->type;
		}
		return $fields;
	}

	public function on_submit( $confirmation, $form, $entry ) {
		// An aborted submission has no saved entry, and spam must never reach a signer.
		if ( empty( $entry['id'] ) || 'spam' === rgar( $entry, 'status' ) ) {
			return $confirmation;
		}

		$data = array();
		foreach ( $form['fields'] as $field ) {
			if ( ! in_array( $field->type, self::SKIPPED_TYPES, true ) ) {
				// Joins multi-input fields, such as first and last name, into one value.
				$data[ 'field_' . $field->id ] = (string) $field->get_value_export( $entry );
			}
		}

		$url = Sajn_Connections::handle_submission( $this->id(), $form['id'], $data );
		return '' !== $url ? array( 'redirect' => $url ) : $confirmation;
	}
}
