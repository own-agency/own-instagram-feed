<?php
/**
 * Starting a connection and finishing it in the callback.
 *
 * The pending login is a site transient keyed on md5(state) (a signed state
 * is too long for an option name), network-wide because the callback may run
 * on another site than the one that started it. It stores everything the
 * callback needs, including the exact redirect_uri: Meta requires the code
 * exchange to repeat it byte for byte.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Auth;

use OWN\InstagramFeed\Api\Client;
use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Settings\App;
use OWN\InstagramFeed\Sites;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class OAuth {

	public const RETURN_ADMIN  = 'admin';
	public const RETURN_INVITE = 'invite';

	/**
	 * @param string $return     self::RETURN_ADMIN (back to $return_url) or
	 *                           self::RETURN_INVITE (thank-you page).
	 * @param string $return_url Admin page to go back to (RETURN_ADMIN).
	 */
	public static function authorize_url( int $blog_id, string $return, string $return_url = '', string $invite = '' ): string {
		$redirect_uri = App::redirect_uri( $blog_id );
		$state        = '' !== App::relay_url()
			? State::sign( App::callback_url( $blog_id ), App::secret() )
			: State::random( 32 );

		set_site_transient(
			self::key( $state ),
			array(
				'blog_id'      => $blog_id,
				'return'       => $return,
				'return_url'   => $return_url,
				'invite'       => $invite,
				'redirect_uri' => $redirect_uri,
			),
			Config::STATE_TTL
		);

		return Client::AUTHORIZE_URL . '?' . http_build_query(
			array(
				'client_id'     => App::id(),
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => Client::SCOPE,
				'state'         => $state,
			)
		);
	}

	/**
	 * Takes the pending login for $state, once.
	 *
	 * @return array{blog_id: int, return: string, return_url: string, invite: string, redirect_uri: string}|null
	 */
	public static function consume( string $state ): ?array {
		$key     = self::key( $state );
		$pending = get_site_transient( $key );
		delete_site_transient( $key );

		return is_array( $pending ) ? $pending : null;
	}

	/**
	 * Exchanges the code for a long-lived token and connects the site.
	 * Runs in the context of the site being connected.
	 */
	public static function connect( string $code, string $redirect_uri ): ?WP_Error {
		// Instagram appends "#_" to the code in some flows.
		$code = (string) preg_replace( '/#_$/', '', $code );

		$short = Client::exchange_code( App::id(), App::secret(), $redirect_uri, $code );
		if ( is_wp_error( $short ) ) {
			return $short;
		}

		$long = Client::long_lived_token( App::secret(), (string) $short['access_token'] );
		if ( is_wp_error( $long ) ) {
			return $long;
		}

		$me = Client::me( (string) $long['access_token'] );
		if ( is_wp_error( $me ) ) {
			return $me;
		}

		Connection::connect(
			(string) $long['access_token'],
			(int) ( $long['expires_in'] ?? 60 * DAY_IN_SECONDS ),
			(string) ( $me['user_id'] ?? '' ),
			(string) ( $me['username'] ?? '' )
		);

		return null;
	}

	public static function blog_exists( int $blog_id ): bool {
		return Sites::exists( $blog_id );
	}

	private static function key( string $state ): string {
		return 'own_instagram_state_' . md5( $state );
	}
}
