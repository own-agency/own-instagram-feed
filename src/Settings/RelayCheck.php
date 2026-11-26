<?php
/**
 * Asks the relay whether this installation's callback is on its
 * ALLOWED_HOSTS, so a missing entry shows on the Meta-app card and in Site
 * Health while setting up, not when a school tries to connect.
 *
 * The question is signed like the OAuth state (HMAC with the app secret), so
 * only installations can ask. The answer is cached for five minutes and
 * forgotten when the app settings are saved.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Settings;

use OWN\InstagramFeed\Auth\State;
use OWN\InstagramFeed\Sites;

defined( 'ABSPATH' ) || exit;

final class RelayCheck {

	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * @return array{state: string, host: string}|null null without a relay or
	 *         app. state: allowed | blocked | unknown (relay unreachable or an
	 *         older relay without the check).
	 */
	public static function status(): ?array {
		if ( '' === App::relay_url() || ! App::is_configured() ) {
			return null;
		}

		$callback = App::callback_url( get_current_blog_id() );
		$key      = self::cache_key( $callback );
		$cached   = Sites::is_network_mode() ? get_site_transient( $key ) : get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$status = self::ask( $callback );

		Sites::is_network_mode()
			? set_site_transient( $key, $status, self::CACHE_TTL )
			: set_transient( $key, $status, self::CACHE_TTL );

		return $status;
	}

	/** Call after saving the app settings: the next status() asks again. */
	public static function forget(): void {
		$key = self::cache_key( App::callback_url( get_current_blog_id() ) );
		Sites::is_network_mode() ? delete_site_transient( $key ) : delete_transient( $key );
	}

	/** @return array{state: string, host: string} */
	private static function ask( string $callback ): array {
		$host = (string) wp_parse_url( $callback, PHP_URL_HOST );
		$url  = add_query_arg(
			array(
				'check' => rawurlencode( $callback ),
				'sig'   => State::b64url( hash_hmac( 'sha256', $callback, App::secret(), true ) ),
			),
			App::relay_url()
		);

		$response = wp_remote_get( $url, array( 'timeout' => 5 ) );
		$body     = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! array_key_exists( 'allowed', $body ) ) {
			return array(
				'state' => 'unknown',
				'host'  => $host,
			);
		}

		return array(
			'state' => $body['allowed'] ? 'allowed' : 'blocked',
			'host'  => $host,
		);
	}

	/** Keyed on the relay and callback, so changing either asks again. */
	private static function cache_key( string $callback ): string {
		return 'own_instagram_relay_check_' . md5( App::relay_url() . '|' . $callback );
	}
}
