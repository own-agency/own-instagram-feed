<?php
/**
 * Settings → Instagram: the site's own connection page, for whoever has
 * Config::CAPABILITY. Outside network mode, developers also get the "Meta-app"
 * tab here (in network mode that lives in Network Admin → Instagram).
 *
 * A theme can move the page: filter own_instagram/admin_parent returns the
 * parent menu slug (default options-general.php).
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Admin;

use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Settings\Access;
use OWN\InstagramFeed\Sites;

defined( 'ABSPATH' ) || exit;

final class SitePage {

	public static function register(): void {
		// Late: a theme may move the page under an options page that is
		// registered at the default priority (SCF uses 99).
		add_action( 'admin_menu', array( self::class, 'menu' ), 150 );
	}

	public static function url( ?int $blog_id = null ): string {
		return $blog_id && is_multisite()
			? get_admin_url( $blog_id, 'admin.php?page=' . Config::ADMIN_SLUG )
			: admin_url( 'admin.php?page=' . Config::ADMIN_SLUG );
	}

	public static function menu(): void {
		/**
		 * Filters the parent menu of Settings → Instagram.
		 *
		 * @param string $parent Parent slug, e.g. 'options-general.php'.
		 */
		$parent = (string) apply_filters( 'own_instagram/admin_parent', 'options-general.php' );

		$hook = add_submenu_page(
			$parent,
			__( 'Instagram', 'own-instagram-feed' ),
			__( 'Instagram', 'own-instagram-feed' ),
			Config::CAPABILITY,
			Config::ADMIN_SLUG,
			array( self::class, 'render' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( self::class, 'enqueue' ) );
		}
	}

	public static function enqueue(): void {
		Assets::enqueue_app(
			'admin-site',
			array(
				'pageUrl'     => self::url(),
				'isDeveloper' => Access::is_developer(),
				'networkMode' => Sites::is_network_mode(),
				'networkUrl'  => Sites::is_network_mode() ? NetworkPage::url() : '',
			)
		);
	}

	public static function render(): void {
		echo '<div class="wrap own-instagram"><div id="own-instagram-site"></div></div>';
	}
}
