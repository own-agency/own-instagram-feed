<?php
/**
 * The Meta app (Instagram app ID + secret) and the optional relay.
 *
 * Stored network-wide in network mode, otherwise per site (Sites). Each value
 * falls back to a wp-config.php constant, so an environment can carry its own
 * values without them in the database:
 *
 *   OWN_INSTAGRAM_APP_ID, OWN_INSTAGRAM_APP_SECRET, OWN_INSTAGRAM_RELAY_URL
 *
 * The secret never leaves PHP: API responses only say whether it is set and
 * its last four characters.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Settings;

use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Sites;

defined( 'ABSPATH' ) || exit;

final class App {

	private const CONSTANTS = array(
		'id'     => 'OWN_INSTAGRAM_APP_ID',
		'secret' => 'OWN_INSTAGRAM_APP_SECRET',
		'relay'  => 'OWN_INSTAGRAM_RELAY_URL',
	);

	/** @return array{id: string, secret: string, relay: string} Stored values only. */
	public static function stored(): array {
		$value = Sites::get_shared_option( Config::OPTION_APP, array() );
		$value = is_array( $value ) ? $value : array();

		return array(
			'id'     => trim( (string) ( $value['id'] ?? '' ) ),
			'secret' => trim( (string) ( $value['secret'] ?? '' ) ),
			'relay'  => trim( (string) ( $value['relay'] ?? '' ) ),
		);
	}

	private static function constant( string $key ): string {
		$name = self::CONSTANTS[ $key ];
		return defined( $name ) ? trim( (string) constant( $name ) ) : '';
	}

	public static function id(): string {
		return self::stored()['id'] ?: self::constant( 'id' );
	}

	public static function secret(): string {
		return self::stored()['secret'] ?: self::constant( 'secret' );
	}

	public static function relay_url(): string {
		return esc_url_raw( self::stored()['relay'] ?: self::constant( 'relay' ) );
	}

	public static function is_configured(): bool {
		return '' !== self::id() && '' !== self::secret();
	}

	/**
	 * Saves what was sent. A missing key is left alone; an empty secret means
	 * "unchanged" unless $clear_secret is set.
	 *
	 * @param array{id?: string, secret?: string, relay?: string} $values
	 */
	public static function save( array $values, bool $clear_secret = false ): void {
		$app = self::stored();

		if ( array_key_exists( 'id', $values ) ) {
			$app['id'] = preg_replace( '/\D/', '', (string) $values['id'] );
		}
		if ( array_key_exists( 'secret', $values ) && '' !== trim( (string) $values['secret'] ) ) {
			$app['secret'] = trim( sanitize_text_field( (string) $values['secret'] ) );
		}
		if ( $clear_secret ) {
			$app['secret'] = '';
		}
		if ( array_key_exists( 'relay', $values ) ) {
			$app['relay'] = esc_url_raw( trim( (string) $values['relay'] ), array( 'https', 'http' ) );
		}

		Sites::update_shared_option( Config::OPTION_APP, $app );
	}

	/**
	 * Where Instagram sends the browser back to for $blog_id. In network mode
	 * always the main site's route, relay or not: one host per installation,
	 * so the Meta app (without relay) or the relay's ALLOWED_HOSTS (with) needs
	 * one entry for the whole network, however many schools and mapped domains
	 * it has. The pending login is a site transient, so the main site finishes
	 * it for any site. Otherwise (single site, per-site activation) the site's
	 * own route.
	 */
	public static function callback_url( int $blog_id ): string {
		$host_blog = Sites::is_network_mode() ? get_main_site_id() : $blog_id;
		return get_rest_url( $host_blog, Config::REST_NAMESPACE . '/oauth/callback' );
	}

	/** The redirect_uri as Meta sees it: the relay, or the callback itself. */
	public static function redirect_uri( int $blog_id ): string {
		return self::relay_url() ?: self::callback_url( $blog_id );
	}

	/**
	 * For the admin UI. Never includes the secret itself.
	 *
	 * @return array<string, mixed>
	 */
	public static function for_admin(): array {
		$stored = self::stored();

		return array(
			'id'               => $stored['id'],
			'relay'            => $stored['relay'],
			'secretSet'        => '' !== $stored['secret'],
			// Only for a real-length secret: on a short one, four characters
			// would give away most of it.
			'secretHint'       => strlen( $stored['secret'] ) >= 16 ? substr( $stored['secret'], -4 ) : '',
			'constants'        => array(
				'id'     => '' !== self::constant( 'id' ),
				'secret' => '' !== self::constant( 'secret' ),
				'relay'  => '' !== self::constant( 'relay' ),
			),
			'configured'       => self::is_configured(),
			'redirectUri'      => self::redirect_uri( get_current_blog_id() ),
			'usesRelay'        => '' !== self::relay_url(),
			'networkMode'      => Sites::is_network_mode(),
		);
	}
}
