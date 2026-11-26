<?php
/**
 * The hourly sync, per site, only while connected.
 *
 * WP-Cron runs per site and only on traffic to that site. With
 * DISABLE_WP_CRON the server cron must run due events on every site, not just
 * the main site (see README).
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\Sync;

defined( 'ABSPATH' ) || exit;

final class Cron {

	public static function register(): void {
		add_action( Config::CRON_HOOK, array( self::class, 'run' ) );
		// Self-heal: put the event back when it went missing (database copied
		// between environments, cron option reset). Both checks read only
		// autoloaded options.
		add_action( 'init', array( self::class, 'heal' ), 20 );
	}

	public static function run(): void {
		Sync::run();
	}

	public static function heal(): void {
		if ( Connection::is_connected() && ! wp_next_scheduled( Config::CRON_HOOK ) ) {
			self::schedule();
		}
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( Config::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', Config::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( Config::CRON_HOOK );
	}
}
