<?php
/**
 * Connect links: let whoever manages the Instagram account log in, without
 * sharing passwords or a WordPress account.
 *
 * The link is the only lock (anyone holding it can attach any Instagram
 * account to that site), hence single-use, one per site, 14 days.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Auth;

use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Sites;

defined( 'ABSPATH' ) || exit;

final class Invites {

	/** @return array<string, array{blog_id: int, expires: int}> Valid ones only. */
	public static function all(): array {
		$invites = (array) Sites::get_shared_option( Config::OPTION_INVITES, array() );

		return array_filter(
			$invites,
			static fn( $invite ) => is_array( $invite ) && (int) ( $invite['expires'] ?? 0 ) > time()
		);
	}

	private static function save( array $invites ): void {
		Sites::update_shared_option( Config::OPTION_INVITES, $invites );
	}

	/** Creates a new link for the site; an older one for it stops working. */
	public static function create( int $blog_id ): string {
		$invites           = self::without( $blog_id );
		$token             = State::random( 40 );
		$invites[ $token ] = array(
			'blog_id' => $blog_id,
			'expires' => time() + Config::INVITE_TTL,
		);
		self::save( $invites );

		return $token;
	}

	public static function revoke( int $blog_id ): void {
		self::save( self::without( $blog_id ) );
	}

	/** @return array{blog_id: int, expires: int}|null */
	public static function find( string $token ): ?array {
		if ( '' === $token ) {
			return null;
		}
		$invite = self::all()[ $token ] ?? null;
		return $invite ? array(
			'blog_id' => (int) $invite['blog_id'],
			'expires' => (int) $invite['expires'],
		) : null;
	}

	/** @return array{url: string, expires: int}|null The site's valid link. */
	public static function for_site( int $blog_id ): ?array {
		foreach ( self::all() as $token => $invite ) {
			if ( (int) $invite['blog_id'] === $blog_id ) {
				return array(
					'url'     => add_query_arg( 'invite', $token, self::connect_url( $blog_id ) ),
					'expires' => (int) $invite['expires'],
				);
			}
		}
		return null;
	}

	/**
	 * In network mode the invite store is network-wide, so the link can live
	 * on the main site; otherwise on the site itself.
	 */
	private static function connect_url( int $blog_id ): string {
		$host = Sites::is_network_mode() ? get_main_site_id() : $blog_id;
		return get_rest_url( $host, Config::REST_NAMESPACE . '/oauth/connect' );
	}

	/** @return array<string, array{blog_id: int, expires: int}> */
	private static function without( int $blog_id ): array {
		return array_filter( self::all(), static fn( $invite ) => (int) $invite['blog_id'] !== $blog_id );
	}
}
