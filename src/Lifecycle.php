<?php
/**
 * Activation and deactivation, per site and network-wide.
 *
 * Activation deliberately does no setup: WordPress runs the activation hook
 * before it records a network activation, so at that moment the plugin can't
 * tell network mode from single-site mode, and the migrations would store
 * network settings per site. Upgrade::maybe_run() on the next request (any
 * site, any page) runs them with the mode known; each site runs its own steps
 * on its own next request.
 *
 * Deactivating keeps all data (connection, posts, images) so reactivating
 * picks up where it left off; only the cron events stop. uninstall.php removes
 * everything.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

defined( 'ABSPATH' ) || exit;

final class Lifecycle {

	/** @param bool $network_wide */
	public static function activate( $network_wide = false ): void {
		// See the docblock: setup runs on the next request.
	}

	/** @param bool $network_wide */
	public static function deactivate( $network_wide = false ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			Cron::unschedule();
			return;
		}
		foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $blog_id ) {
			Sites::in( (int) $blog_id, array( Cron::class, 'unschedule' ) );
		}
	}

	/** A site created after network activation gets set up right away. */
	public static function on_new_site( \WP_Site $site ): void {
		if ( ! Sites::is_network_mode() ) {
			return;
		}
		Sites::in( (int) $site->blog_id, array( Upgrade::class, 'run_site' ) );
	}
}
