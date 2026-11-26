<?php
/**
 * Network Admin → Instagram (network mode only, developers only): the Meta app
 * settings and an overview of every site with its actions.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Admin;

use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Settings\Access;
use OWN\InstagramFeed\Sites;

defined( 'ABSPATH' ) || exit;

final class NetworkPage {

	public static function register(): void {
		add_action( 'network_admin_menu', array( self::class, 'menu' ) );
	}

	public static function url(): string {
		return network_admin_url( 'admin.php?page=' . Config::ADMIN_SLUG );
	}

	public static function menu(): void {
		if ( ! Sites::is_network_mode() || ! Access::is_developer() ) {
			return;
		}

		$hook = add_menu_page(
			__( 'Instagram', 'own-instagram-feed' ),
			__( 'Instagram', 'own-instagram-feed' ),
			'manage_network_options',
			Config::ADMIN_SLUG,
			array( self::class, 'render' ),
			'dashicons-instagram',
			98
		);

		add_action( 'load-' . $hook, array( self::class, 'enqueue' ) );
	}

	public static function enqueue(): void {
		Assets::enqueue_app(
			'admin-network',
			array(
				'pageUrl'     => self::url(),
				'isDeveloper' => true,
				'networkMode' => true,
			)
		);
	}

	public static function render(): void {
		echo '<div class="wrap own-instagram"><div id="own-instagram-network"></div></div>';
	}
}
