<?php
/**
 * Numbered, run-once data migrations: per site and once per network.
 *
 * Rules that keep updates safe:
 *   - Never edit or renumber a migration that has shipped; add a new one.
 *   - A migration must be safe to run on a site that never had the old data.
 *   - Raise SITE_VERSION / NETWORK_VERSION with each new step.
 *
 * Runs on init for the current site (cheap: one autoloaded option compare),
 * on activation, and for new sites in network mode.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Settings\Access;

defined( 'ABSPATH' ) || exit;

final class Upgrade {

	public const SITE_VERSION    = 3;
	public const NETWORK_VERSION = 1;

	public static function maybe_run(): void {
		if ( (int) get_option( Config::OPTION_DB_VERSION, 0 ) < self::SITE_VERSION ) {
			self::run_site();
		}
		if ( Sites::is_network_mode() && (int) get_site_option( Config::OPTION_NETWORK_DB_VERSION, 0 ) < self::NETWORK_VERSION ) {
			self::run_network();
		}
	}

	public static function run_site(): void {
		$from = (int) get_option( Config::OPTION_DB_VERSION, 0 );

		if ( $from < 1 ) {
			self::site_1_capability();
		}
		if ( $from < 2 ) {
			self::site_2_from_theme();
		}
		if ( $from < 3 ) {
			self::site_3_convert_theme_blocks();
		}

		update_option( Config::OPTION_DB_VERSION, self::SITE_VERSION, true );
		Cron::heal();
	}

	public static function run_network(): void {
		$from = (int) get_site_option( Config::OPTION_NETWORK_DB_VERSION, 0 );

		if ( $from < 1 ) {
			self::network_1_from_theme();
		}

		update_site_option( Config::OPTION_NETWORK_DB_VERSION, self::NETWORK_VERSION );
	}

	/* --- Site migrations ------------------------------------------------- */

	/** 1: the capability for Settings → Instagram, for editors and admins. */
	private static function site_1_capability(): void {
		Access::grant_default_roles();
	}

	/**
	 * 2: take over a connection made with the own-framework theme (1.3.0),
	 * which stored the same data under ownframework_* names. Moves it, so the
	 * site keeps its account without reconnecting; the images already sit in
	 * the same uploads/own-instagram folder. Outside network mode the theme's
	 * app settings were per site too, so they come along here.
	 */
	private static function site_2_from_theme(): void {
		$map = array(
			'ownframework_instagram'         => Config::OPTION_CONNECTION,
			'ownframework_instagram_posts'   => Config::OPTION_FEED,
			'ownframework_instagram_removed' => Config::OPTION_REMOVED,
		);
		foreach ( $map as $old => $new ) {
			$value = get_option( $old, null );
			if ( null !== $value && null === get_option( $new, null ) ) {
				update_option( $new, $value, Config::OPTION_CONNECTION === $new );
			}
			delete_option( $old );
		}
		// The theme's own hourly event; its callback is gone with the theme code.
		wp_clear_scheduled_hook( 'ownframework_instagram_sync' );

		if ( ! Sites::is_network_mode() ) {
			self::move_app_settings( 'get_option', 'delete_option' );
		}
	}

	/**
	 * 3: the own-framework theme (1.3.0–1.5.0) had its own block,
	 * acf/instagram-feed. Its later versions no longer ship it, so every
	 * placed copy becomes this plugin's block with the same settings. The
	 * theme always showed an empty state on the site, so showEmpty is on.
	 *
	 * Covers every post type that stores blocks (pages, posts, templates,
	 * template parts, synced patterns). Only the block comment changes: the
	 * old block was self-closing (dynamic), so there is no inner HTML.
	 */
	private static function site_3_convert_theme_blocks(): void {
		global $wpdb;

		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%<!-- wp:acf/instagram-feed %'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $ids as $id ) {
			$content   = (string) get_post_field( 'post_content', (int) $id, 'raw' );
			$converted = self::convert_theme_blocks( $content );
			if ( $converted !== $content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $converted ), array( 'ID' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( (int) $id );
			}
		}
	}

	/** Pure: one post_content in, converted out. Tested in tests/CoreTest.php. */
	public static function convert_theme_blocks( string $content ): string {
		return (string) preg_replace_callback(
			'#<!-- wp:acf/instagram-feed (\{.*?\}) /-->#s',
			static function ( array $match ): string {
				$old = json_decode( $match[1], true );
				$old = is_array( $old ) ? $old : array();
				$new = array(
					'layout'    => 'grid' === ( $old['layout'] ?? 'cards' ) ? 'grid' : 'cards',
					'count'     => (int) ( $old['count'] ?? 4 ),
					'rounded'   => false !== ( $old['rounded'] ?? true ),
					'showEmpty' => true,
				);
				foreach ( array( 'align', 'anchor', 'className', 'style' ) as $key ) {
					if ( isset( $old[ $key ] ) ) {
						$new[ $key ] = $old[ $key ];
					}
				}
				// WordPress' own serializer escapes what can't sit in an HTML
				// comment (--, <, >); plain JSON is the fallback for the tests.
				$json = function_exists( 'serialize_block_attributes' )
					? serialize_block_attributes( $new )
					: (string) wp_json_encode( $new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				return '<!-- wp:' . Config::BLOCK_NAME . ' ' . $json . ' /-->';
			},
			$content
		);
	}

	/* --- Network migrations ---------------------------------------------- */

	/** 1: the theme's network-wide app settings and connect links. */
	private static function network_1_from_theme(): void {
		self::move_app_settings( 'get_site_option', 'delete_site_option' );
	}

	/**
	 * @param callable $get    get_option or get_site_option.
	 * @param callable $delete delete_option or delete_site_option.
	 */
	private static function move_app_settings( callable $get, callable $delete ): void {
		$app   = $get( 'ownframework_instagram_app', null );
		$relay = $get( 'ownframework_instagram_relay_url', null );

		if ( ( is_array( $app ) || null !== $relay ) && null === Sites::get_shared_option( Config::OPTION_APP, null ) ) {
			Sites::update_shared_option(
				Config::OPTION_APP,
				array(
					'id'     => (string) ( $app['id'] ?? '' ),
					'secret' => (string) ( $app['secret'] ?? '' ),
					'relay'  => (string) ( $relay ?? '' ),
				)
			);
		}

		$invites = $get( 'ownframework_instagram_invites', null );
		if ( is_array( $invites ) && null === Sites::get_shared_option( Config::OPTION_INVITES, null ) ) {
			Sites::update_shared_option( Config::OPTION_INVITES, $invites );
		}

		foreach ( array( 'ownframework_instagram_app', 'ownframework_instagram_relay_url', 'ownframework_instagram_invites' ) as $old ) {
			$delete( $old );
		}
	}
}
