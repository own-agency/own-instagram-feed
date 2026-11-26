<?php
/**
 * The only class that talks to Meta ("Instagram API with Instagram Login",
 * scope instagram_business_basic).
 *
 * Graph calls are unversioned on purpose: Meta then serves its oldest
 * supported version, which is retired later than any version pinned here.
 *
 * Errors come back in two shapes and are normalised to one WP_Error with the
 * Meta code in its data: Graph ({"error": {...}}) and api.instagram.com
 * ({"error_type", "code", "error_message"}).
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Api;

use OWN\InstagramFeed\Config;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Client {

	public const AUTHORIZE_URL = 'https://www.instagram.com/oauth/authorize';
	public const TOKEN_URL     = 'https://api.instagram.com/oauth/access_token';
	public const GRAPH_URL     = 'https://graph.instagram.com';
	public const SCOPE         = 'instagram_business_basic';

	/** @return array|WP_Error { access_token, user_id } */
	public static function exchange_code( string $app_id, string $secret, string $redirect_uri, string $code ) {
		$body = self::request(
			'POST',
			self::TOKEN_URL,
			array(
				'client_id'     => $app_id,
				'client_secret' => $secret,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $redirect_uri,
				'code'          => $code,
			)
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		// Returned both flat and wrapped in "data".
		$body = isset( $body['data'][0] ) && is_array( $body['data'][0] ) ? $body['data'][0] : $body;

		return empty( $body['access_token'] )
			? new WP_Error( 'own_instagram', __( 'Instagram gaf geen toegangstoken terug.', 'own-instagram-feed' ) )
			: $body;
	}

	/** @return array|WP_Error { access_token, expires_in } */
	public static function long_lived_token( string $secret, string $short_token ) {
		return self::request(
			'GET',
			self::GRAPH_URL . '/access_token',
			array(
				'grant_type'    => 'ig_exchange_token',
				'client_secret' => $secret,
				'access_token'  => $short_token,
			)
		);
	}

	/** @return array|WP_Error { access_token, expires_in } */
	public static function refresh_token( string $token ) {
		return self::request(
			'GET',
			self::GRAPH_URL . '/refresh_access_token',
			array(
				'grant_type'   => 'ig_refresh_token',
				'access_token' => $token,
			)
		);
	}

	/** @return array|WP_Error { user_id, username } */
	public static function me( string $token ) {
		return self::request(
			'GET',
			self::GRAPH_URL . '/me',
			array(
				'fields'       => 'user_id,username',
				'access_token' => $token,
			)
		);
	}

	/** @return array|WP_Error List of media items. */
	public static function media( string $token, int $limit ) {
		$body = self::request(
			'GET',
			self::GRAPH_URL . '/me/media',
			array(
				'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
				'limit'        => $limit,
				'access_token' => $token,
			)
		);
		return is_wp_error( $body ) ? $body : array_values( (array) ( $body['data'] ?? array() ) );
	}

	public static function is_token_error( WP_Error $error ): bool {
		$data = (array) $error->get_error_data();
		return Config::TOKEN_ERROR === (int) ( $data['code'] ?? 0 );
	}

	/**
	 * @return array|WP_Error Decoded JSON.
	 */
	public static function request( string $method, string $url, array $params ) {
		$args = array(
			'timeout'    => Config::HTTP_TIMEOUT,
			'user-agent' => 'OWN Instagram Feed/' . \OWN\InstagramFeed\VERSION . '; WordPress',
		);

		$response = 'POST' === $method
			? wp_remote_post( $url, $args + array( 'body' => $params ) )
			: wp_remote_get( $url . '?' . http_build_query( $params ), $args );

		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message */
			return new WP_Error( 'own_instagram', sprintf( __( 'Instagram is niet bereikbaar: %s', 'own-instagram-feed' ), $response->get_error_message() ) );
		}

		return self::parse( (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Pure, so it can be tested without WordPress' HTTP layer.
	 *
	 * @return array|WP_Error
	 */
	public static function parse( int $status, string $raw ) {
		$body = json_decode( $raw, true );

		if ( is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ) {
			return new WP_Error(
				'own_instagram',
				(string) ( $body['error']['message'] ?? __( 'Onbekende fout van Instagram.', 'own-instagram-feed' ) ),
				array( 'code' => (int) ( $body['error']['code'] ?? 0 ) )
			);
		}
		if ( is_array( $body ) && isset( $body['error_message'] ) ) {
			return new WP_Error( 'own_instagram', (string) $body['error_message'], array( 'code' => (int) ( $body['code'] ?? 0 ) ) );
		}
		if ( $status >= 400 || ! is_array( $body ) ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'own_instagram', sprintf( __( 'Onverwacht antwoord van Instagram (HTTP %d).', 'own-instagram-feed' ), $status ) );
		}

		return $body;
	}
}
