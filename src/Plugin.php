<?php
/**
 * Wires every component to WordPress.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Admin\NetworkPage;
use OWN\InstagramFeed\Admin\SitePage;
use OWN\InstagramFeed\Block\Feed as FeedBlock;
use OWN\InstagramFeed\Rest\Admin as AdminRest;
use OWN\InstagramFeed\Rest\Oauth as OauthRest;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		load_plugin_textdomain( Config::TEXT_DOMAIN, false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );

		// The own-framework theme 1.3.0 carried its own copy of this engine.
		// Never run two: the plugin waits (and says so) until the theme no
		// longer has it, and only then migrates the theme's data.
		if ( self::legacy_engine_active() ) {
			add_action( 'admin_notices', array( self::class, 'legacy_notice' ) );
			add_action( 'network_admin_notices', array( self::class, 'legacy_notice' ) );
			return;
		}

		Upgrade::maybe_run();
		Cron::register();
		FeedBlock::register();
		add_action( 'rest_api_init', array( OauthRest::class, 'register' ) );
		add_action( 'rest_api_init', array( AdminRest::class, 'register' ) );
		add_action( 'wp_initialize_site', array( Lifecycle::class, 'on_new_site' ), 20 );

		if ( is_admin() ) {
			SitePage::register();
			NetworkPage::register();
			Health::register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'own-instagram', Cli::class );
		}
	}

	public static function legacy_engine_active(): bool {
		return function_exists( 'ownframework_instagram_sync' );
	}

	public static function legacy_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'OWN Instagram Feed staat nog uit: het actieve thema heeft een eigen Instagram-koppeling (own-framework 1.3.0). Werk het thema bij naar 1.6.0 of nieuwer; daarna neemt de plugin de koppeling automatisch over.', 'own-instagram-feed' )
		);
	}
}
