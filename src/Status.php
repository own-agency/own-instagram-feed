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
		);
	}
}
