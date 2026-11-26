<?php
/**
 * One site's status in one shape, for the admin apps, the CLI and Site Health.
 * Part of the REST contract (own-instagram/v1): add keys, never remove or
 * rename them.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Auth\Invites;
use OWN\InstagramFeed\Feed\Cleanup;
use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\Posts;
use OWN\InstagramFeed\Settings\App;

defined( 'ABSPATH' ) || exit;

final class Status {

	/**
	 * Status of the current site (call inside Sites::in() for another).
	 *
	 * @return array<string, mixed>
	 */
	public static function site(): array {
		$blog_id    = get_current_blog_id();
		$connection = Connection::get();
		$feed       = Posts::get();
		$connected  = ! empty( $connection['token'] );
		$invite     = Invites::for_site( $blog_id );
		$removed    = Cleanup::last_removal();

		if ( ! $connected ) {
			$state = $removed ? 'removed' : 'disconnected';
		} elseif ( Connection::needs_reconnect() ) {
			$state = 'reconnect';
		} elseif ( '' !== (string) ( $connection['error'] ?? '' ) ) {
			$state = 'error';
		} else {
			$state = 'connected';
		}

		return array(
			'blogId'        => $blog_id,
			'siteName'      => (string) get_bloginfo( 'name' ),
			'siteUrl'       => home_url( '/' ),
			'adminUrl'      => Admin\SitePage::url(),
			'appConfigured' => App::is_configured(),
			'state'         => $state,
			'connected'     => $connected,
			'username'      => (string) ( $connection['username'] ?? '' ),
			'postCount'     => count( $feed['posts'] ),
			'syncedAt'      => $feed['synced_at'],
			'expiresAt'     => (int) ( $connection['expires'] ?? 0 ),
			'error'         => (string) ( $connection['error'] ?? '' ),
			'removeAt'      => Connection::remove_at(),
			'removed'       => $removed,
			'invite'        => $invite,
			'cronNext'      => (int) wp_next_scheduled( Config::CRON_HOOK ),
			'connectError'  => (string) get_site_transient( self::connect_error_key( $blog_id ) ),
		);
	}

	/**
	 * Why the last login for a site failed, in Meta's words, for 10 minutes.
	 * Kept here rather than in the return URL, so nobody can put their own
	 * text on the admin page with a crafted link.
	 */
	public static function remember_connect_error( int $blog_id, string $message ): void {
		set_site_transient( self::connect_error_key( $blog_id ), mb_substr( $message, 0, 300 ), 10 * MINUTE_IN_SECONDS );
	}

	public static function forget_connect_error( int $blog_id ): void {
		delete_site_transient( self::connect_error_key( $blog_id ) );
	}

	private static function connect_error_key( int $blog_id ): string {
		return 'own_instagram_connect_error_' . $blog_id;
	}
}
