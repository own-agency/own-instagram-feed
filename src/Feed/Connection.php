<?php
/**
 * The current site's connection: token, account and health.
 *
 * Record (Config::OPTION_CONNECTION, autoloaded):
 *   token, expires, user_id, username, connected_at, refreshed_at,
 *   needs_reconnect, error, error_at, notified, broken_since
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Feed;

use OWN\InstagramFeed\Api\Client;
use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Cron;
use OWN\InstagramFeed\Notifier;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Connection {

	public static function get(): array {
		$value = get_option( Config::OPTION_CONNECTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function save( array $connection ): void {
		update_option( Config::OPTION_CONNECTION, $connection, true );
	}

	public static function is_connected(): bool {
		return '' !== (string) ( self::get()['token'] ?? '' );
	}

	public static function needs_reconnect(): bool {
		$connection = self::get();
		if ( empty( $connection['token'] ) ) {
			return false;
		}
		return ! empty( $connection['needs_reconnect'] )
			|| ( ! empty( $connection['expires'] ) && (int) $connection['expires'] < time() );
	}

	/** Unix time at which the posts will be removed, or 0. */
	public static function remove_at(): int {
		$since = (int) ( self::get()['broken_since'] ?? 0 );
		return $since ? $since + Config::REMOVE_AFTER : 0;
	}

	public static function connect( string $token, int $expires_in, string $user_id, string $username ): void {
		if ( (string) ( self::get()['user_id'] ?? '' ) !== $user_id ) {
			// Another account: the previous account's posts must not linger.
			Posts::clear();
		}

		self::save(
			array(
				'token'           => $token,
				'expires'         => time() + $expires_in,
				'user_id'         => $user_id,
				'username'        => $username,
				'connected_at'    => time(),
				'refreshed_at'    => time(),
				'needs_reconnect' => false,
				'error'           => '',
				'error_at'        => 0,
				'notified'        => false,
				'broken_since'    => 0,
			)
		);
		delete_option( Config::OPTION_REMOVED );
		Cron::schedule();

		/**
		 * Fires after a site connected an Instagram account.
		 *
		 * @param string $username
		 */
		do_action( 'own_instagram/connected', $username );
	}

	public static function disconnect(): void {
		$username = (string) ( self::get()['username'] ?? '' );

		delete_option( Config::OPTION_CONNECTION );
		Posts::clear();
		Cron::unschedule();

		/**
		 * Fires after a site's Instagram connection was removed.
		 *
		 * @param string $username
		 */
		do_action( 'own_instagram/disconnected', $username );
	}

	public static function mark_refreshed( string $token, int $expires_in ): void {
		$connection                 = self::get();
		$connection['token']        = $token;
		$connection['expires']      = time() + $expires_in;
		$connection['refreshed_at'] = time();
		self::save( $connection );
	}

	public static function mark_healthy(): void {
		$connection                    = self::get();
		$connection['error']           = '';
		$connection['error_at']        = 0;
		$connection['needs_reconnect'] = false;
		$connection['notified']        = false;
		$connection['broken_since']    = 0;
		self::save( $connection );
	}

	/**
	 * Records an error. A token error (190: expired, revoked, password
	 * changed) marks the connection broken, starts the 30-day clock once and
	 * sends one alert: nobody looks at a settings page daily, and a feed that
	 * silently stops is noticed only weeks later.
	 */
	public static function fail( WP_Error $error ): void {
		$connection = self::get();
		if ( ! $connection ) {
			return;
		}

		$connection['error']    = $error->get_error_message();
		$connection['error_at'] = time();

		if ( Client::is_token_error( $error ) ) {
			$first = empty( $connection['needs_reconnect'] );

			$connection['needs_reconnect'] = true;
			if ( empty( $connection['broken_since'] ) ) {
				$connection['broken_since'] = time();
			}
			if ( empty( $connection['notified'] ) ) {
				Notifier::connection_broken( $connection, $error );
				$connection['notified'] = true;
			}

			if ( $first ) {
				/**
				 * Fires once when a site's connection stops working.
				 *
				 * @param string   $username
				 * @param WP_Error $error
				 */
				do_action( 'own_instagram/connection_broken', (string) ( $connection['username'] ?? '' ), $error );
			}
		}

		self::save( $connection );
	}
}
