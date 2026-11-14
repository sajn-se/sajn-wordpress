<?php
/**
 * Connections: a form, a sajn template, and how the submission fills in the document.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Connections {

	const OPTION = 'sajn_connections';

	const DELIVERIES = array(
		'EMAIL'     => array( 'EMAIL' ),
		'SMS'       => array( 'SMS' ),
		'EMAIL_SMS' => array( 'EMAIL', 'SMS' ),
		'NONE'      => array(),
	);

	public static function all() {
		$connections = get_option( self::OPTION, array() );
		return is_array( $connections ) ? $connections : array();
	}

	public static function get( $id ) {
		$connections = self::all();
		return isset( $connections[ $id ] ) ? $connections[ $id ] : null;
	}

	public static function save( array $connection ) {
		$connections                      = self::all();
		$connections[ $connection['id'] ] = $connection;
		update_option( self::OPTION, $connections, false );
	}

	public static function delete( $id ) {
		$connections = self::all();
		unset( $connections[ $id ] );
		update_option( self::OPTION, $connections, false );
	}

	public static function defaults() {
		return array(
			'id'             => 'c_' . wp_generate_password( 12, false ),
			'name'           => '',
			'active'         => false,
			'source'         => '',
			'form_id'        => 0,
			'template_id'    => '',
			'template_name'  => '',
			'document_name'  => '',
			'parties'        => array(),
			'values'         => array(),
			'extra_values'   => '',
			'redirect_party' => '',
		);
	}

	/**
	 * Runs every active connection for a submitted form.
	 *
	 * @param array<string, string> $data Tag name => submitted value.
	 * @return string The signing URL to send the visitor to, or an empty string.
	 */
	public static function handle_submission( $source_id, $form_id, array $data ) {
		$redirect = '';

		foreach ( self::all() as $connection ) {
			if ( empty( $connection['active'] ) || $connection['source'] !== $source_id || (int) $connection['form_id'] !== (int) $form_id || empty( $connection['template_id'] ) ) {
				continue;
			}

			$url = self::run( $connection, $data );
			if ( '' === $redirect && '' !== $url ) {
				$redirect = $url;
			}
		}

		return $redirect;
	}

	/**
	 * The request body for `POST /api/v1/documents`.
	 */
	public static function build_document( array $connection, array $data ) {
		$parties = array();
		foreach ( $connection['parties'] as $template_party_id => $party ) {
			$name  = self::resolve( $party['name'], $data );
			$email = self::resolve( $party['email'], $data );
			$phone = self::resolve( $party['phone'], $data );
			if ( '' === $name && '' === $email && '' === $phone ) {
				continue;
			}

			$entry = array(
				'templatePartyId' => $template_party_id,
				'ref'             => $template_party_id,
				'name'            => '' !== $name ? $name : ( '' !== $email ? $email : $phone ),
			);
			if ( '' !== $email ) {
				$entry['email'] = $email;
			}
			if ( '' !== $phone ) {
				$entry['phone'] = $phone;
			}
			if ( array_key_exists( $party['delivery'], self::DELIVERIES ) ) {
				$entry['delivery'] = self::DELIVERIES[ $party['delivery'] ];
			}
			if ( ! empty( $party['signature_method'] ) ) {
				$entry['signatureMethod'] = $party['signature_method'];
			}
			$parties[] = $entry;
		}

		$values = array();
		foreach ( array_replace( $connection['values'], self::parse_extra_values( $connection['extra_values'] ) ) as $key => $pattern ) {
			$value = self::resolve( $pattern, $data );
			if ( '' !== $value ) {
				$values[] = array(
					'key'   => (string) $key,
					'value' => $value,
				);
			}
		}

		$name = self::resolve( $connection['document_name'], $data );

		$document = array(
			'name'       => '' !== $name ? $name : $connection['template_name'],
			'templateId' => $connection['template_id'],
			'send'       => true,
		);
		if ( $parties ) {
			$document['parties'] = $parties;
		}
		if ( $values ) {
			$document['fieldValues'] = $values;
		}
		return $document;
	}

	/**
	 * Replaces each `{tag}` with the submitted value. A tag the form doesn't have becomes empty.
	 */
	public static function resolve( $pattern, array $data ) {
		$resolved = preg_replace_callback(
			'/\{([^{}\s]+)\}/',
			static function ( $match ) use ( $data ) {
				return isset( $data[ $match[1] ] ) ? (string) $data[ $match[1] ] : '';
			},
			(string) $pattern
		);
		return trim( $resolved );
	}

	/**
	 * One `key = pattern` per line, for keys the template lookup doesn't list, such as AcroForm fields.
	 *
	 * @return array<string, string>
	 */
	public static function parse_extra_values( $text ) {
		$values = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$parts = explode( '=', $line, 2 );
			if ( 2 === count( $parts ) && '' !== trim( $parts[0] ) ) {
				$values[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}
		return $values;
	}

	private static function run( array $connection, array $data ) {
		$body = self::build_document( $connection, $data );

		// sajn replays the first response for 24 hours, so a resubmitted or retried form gets the same document.
		$idempotency_key = 'wp-' . hash( 'sha256', home_url() . '|' . $connection['id'] . '|' . wp_json_encode( $body ) );

		$document = Sajn_Api::create_document( $body, $idempotency_key );
		if ( is_wp_error( $document ) ) {
			Sajn_Log::add( $connection['name'], false, $document->get_error_message() );
			return '';
		}

		$document_id = isset( $document['id'] ) ? $document['id'] : '';
		$url         = '';

		if ( '' !== $connection['redirect_party'] ) {
			$url = self::signing_url( $document, $connection['redirect_party'] );
			if ( is_wp_error( $url ) ) {
				Sajn_Log::add( $connection['name'], false, $url->get_error_message(), $document_id );
				return '';
			}
		}

		Sajn_Log::add( $connection['name'], true, __( 'Document sent for signing.', 'sajn' ), $document_id );
		return $url;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function signing_url( array $document, $template_party_id ) {
		foreach ( isset( $document['parties'] ) ? $document['parties'] : array() as $party ) {
			if ( isset( $party['ref'] ) && $party['ref'] === $template_party_id ) {
				$details = Sajn_Api::party( $document['id'], $party['id'] );
				if ( is_wp_error( $details ) ) {
					return $details;
				}
				return isset( $details['signingUrl'] ) ? (string) $details['signingUrl'] : '';
			}
		}

		return new WP_Error( 'sajn_redirect_party_missing', __( 'The document was sent, but the party to open signing for has no name, email, or phone in this submission.', 'sajn' ) );
	}
}
