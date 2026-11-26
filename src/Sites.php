<?php
/**
 * Multisite awareness in one place: whether settings are network-wide, which
 * sites run the plugin, and running code in another site's context.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

defined( 'ABSPATH' ) || exit;

final class Sites {

	/**
	 * True when the plugin is network-activated: then the Meta app settings
	 * and connect links are network-wide (site options) and the network admin
	 * has an overview of every site. Activated per site on a multisite, every
	 * site keeps its own app settings, like a single site.
	 */
	public static function is_network_mode(): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active_for_network( plugin_basename( PLUGIN_FILE ) );
	}

	/** Network-wide option in network mode, otherwise the current site's. */
	public static function get_shared_option( string $name, $default = null ) {
		return self::is_network_mode() ? get_site_option( $name, $default ) : get_option( $name, $default );
	}

	public static function update_shared_option( string $name, $value ): void {
		self::is_network_mode() ? update_site_option( $name, $value ) : update_option( $name, $value, false );
	}

	public static function delete_shared_option( string $name ): void {
		self::is_network_mode() ? delete_site_option( $name ) : delete_option( $name );
	}

	/**
	 * Sites the plugin runs on: every site in network mode, otherwise only the
	 * current one.
	 *
	 * @return int[]
	 */
	public static function ids(): array {
		if ( ! self::is_network_mode() ) {
			return array( get_current_blog_id() );
		}
		return array_map(
			'intval',
			get_sites(
				array(
					'number'   => 0,
					'deleted'  => 0,
					'archived' => 0,
					'orderby'  => 'id',
					'order'    => 'ASC',
					'fields'   => 'ids',
				)
			)
		);
	}

	/**
	 * Runs $callback in the context of $blog_id and returns its result.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public static function in( int $blog_id, callable $callback ) {
		$switch = is_multisite() && get_current_blog_id() !== $blog_id;
		if ( $switch ) {
			switch_to_blog( $blog_id );
		}
		try {
			return $callback();
		} finally {
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	public static function exists( int $blog_id ): bool {
		return ! is_multisite() ? get_current_blog_id() === $blog_id : (bool) get_site( $blog_id );
	}
}
