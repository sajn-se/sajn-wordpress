<?php
/**
 * Client for the sajn public API (v1).
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Api {

	const API_VERSION    = '2026-10';
	const SETTINGS_KEY   = 'sajn_settings';
	const CACHE_SECONDS  = 60;
	const MAX_LIST_PAGES = 10;

	public static function base_url() {
		$url = defined( 'SAJN_BASE_URL' ) && SAJN_BASE_URL ? SAJN_BASE_URL : 'https://app.sajn.se';
		return untrailingslashit( $url );
	}

	public static function api_key() {
		$settings = get_option( self::SETTINGS_KEY, array() );
		return isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}

	public static function has_api_key() {
		return '' !== self::api_key();
	}

	/**
	 * @return array|WP_Error Decoded JSON body, or the API error.
	 */
	public static function request( $method, $path, $body = null, $idempotency_key = '' ) {
		$api_key = self::api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'sajn_no_api_key', __( 'No sajn API key is saved.', 'sajn' ) );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Sajn-Version'  => self::API_VERSION,
				'Accept'        => 'application/json',
				'User-Agent'    => 'sajn-wordpress/' . SAJN_VERSION,
			),
		);

		if ( '' !== $idempotency_key ) {
			$args['headers']['Idempotency-Key'] = $idempotency_key;
		}

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::base_url() . '/api/v1' . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 ) {
			return self::to_error( $status, is_array( $decoded ) ? $decoded : array() );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Reads that the admin screens repeat on every page load are cached briefly per API key.
	 *
	 * @return array|WP_Error
	 */
	public static function cached_get( $path ) {
		$cache_key = 'sajn_' . md5( self::api_key() . '|' . self::base_url() . '|' . $path );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = self::request( 'GET', $path );
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_SECONDS );
		}
		return $result;
	}

	/**
	 * @return array|WP_Error Every item of a cursor-paginated list.
	 */
	public static function list_all( $path ) {
		$items  = array();
		$cursor = null;

		for ( $page = 0; $page < self::MAX_LIST_PAGES; $page++ ) {
			$result = self::cached_get( $cursor ? add_query_arg( 'cursor', rawurlencode( $cursor ), $path ) : $path );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$items  = array_merge( $items, isset( $result['data'] ) ? $result['data'] : array() );
			$cursor = isset( $result['nextCursor'] ) ? $result['nextCursor'] : null;
			if ( empty( $result['hasMore'] ) || ! $cursor ) {
				break;
			}
		}

		return $items;
	}

	public static function me() {
		return self::request( 'GET', '/me' );
	}

	public static function templates() {
		return self::list_all( '/templates' );
	}

	public static function template_parties( $template_id ) {
		return self::list_all( '/templates/' . rawurlencode( $template_id ) . '/parties' );
	}

	public static function signature_methods() {
		return self::list_all( '/helpers/signature-methods' );
	}

	/**
	 * The keys the sender fills in: FORM subfields and boxes placed on a PDF that have a key and no party.
	 *
	 * @return array|WP_Error List of [ 'key' => string, 'label' => string ].
	 */
	public static function template_keys( $template_id ) {
		$content = self::list_all( '/templates/' . rawurlencode( $template_id ) . '/content' );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$keys = array();
		foreach ( $content as $block ) {
			$meta = isset( $block['fieldMeta'] ) ? $block['fieldMeta'] : array();

			$candidates = array();
			if ( isset( $meta['fields'] ) && is_array( $meta['fields'] ) ) {
				foreach ( $meta['fields'] as $field ) {
					$candidates[] = array(
						'key'     => isset( $field['key'] ) ? $field['key'] : '',
						'label'   => isset( $field['fieldMeta']['label'] ) ? $field['fieldMeta']['label'] : '',
						'partyId' => isset( $field['fieldMeta']['partyId'] ) ? $field['fieldMeta']['partyId'] : null,
					);
				}
			}
			if ( isset( $meta['placedFields'] ) && is_array( $meta['placedFields'] ) ) {
				foreach ( $meta['placedFields'] as $placed ) {
					$candidates[] = array(
						'key'     => isset( $placed['key'] ) ? $placed['key'] : '',
						'label'   => isset( $placed['label'] ) ? $placed['label'] : '',
						'partyId' => isset( $placed['partyId'] ) ? $placed['partyId'] : null,
					);
				}
			}

			foreach ( $candidates as $candidate ) {
				if ( '' === $candidate['key'] || ! empty( $candidate['partyId'] ) || isset( $keys[ $candidate['key'] ] ) ) {
					continue;
				}
				$keys[ $candidate['key'] ] = array(
					'key'   => $candidate['key'],
					'label' => '' !== $candidate['label'] ? $candidate['label'] : $candidate['key'],
				);
			}
		}

		return array_values( $keys );
	}

	public static function create_document( array $body, $idempotency_key ) {
		return self::request( 'POST', '/documents', $body, $idempotency_key );
	}

	public static function party( $document_id, $party_id ) {
		return self::request( 'GET', '/documents/' . rawurlencode( $document_id ) . '/parties/' . rawurlencode( $party_id ) );
	}

	private static function to_error( $status, array $body ) {
		$message = isset( $body['message'] ) ? $body['message'] : sprintf(
			/* translators: %d: HTTP status code */
			__( 'sajn returned HTTP %d.', 'sajn' ),
			$status
		);

		if ( ! empty( $body['issues'] ) && is_array( $body['issues'] ) ) {
			$details = array();
			foreach ( $body['issues'] as $issue ) {
				if ( isset( $issue['message'] ) && false !== strpos( $message, $issue['message'] ) ) {
					continue;
				}
				$details[] =trim( ( ! empty( $issue['path'] ) ? $issue['path'] . ': ' : '' ) . ( isset( $issue['message'] ) ? $issue['message'] : '' ) );
			}
			$message .= ' ' . implode( '; ', array_filter( $details ) );
		}

		if ( ! empty( $body['requestId'] ) ) {
			$message .= ' (' . $body['requestId'] . ')';
		}

		return new WP_Error( isset( $body['code'] ) ? $body['code'] : 'sajn_http_' . $status, $message, array( 'status' => $status ) );
	}
}
