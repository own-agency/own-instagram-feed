<?php
/**
 * Loads a built React app (build/<name>.js) with the dependencies
 * wp-scripts recorded in build/<name>.asset.php, so React and
 * @wordpress/components come from WordPress itself, never bundled.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Admin;

use OWN\InstagramFeed\Config;
use const OWN\InstagramFeed\PLUGIN_DIR;
use const OWN\InstagramFeed\PLUGIN_FILE;

defined( 'ABSPATH' ) || exit;

final class Assets {

	/**
	 * @param string $name Entry name in package.json (admin-site, …).
	 * @param array  $data Boot data, exposed as window.ownInstagram.
	 */
	public static function enqueue_app( string $name, array $data ): void {
		$asset_file = PLUGIN_DIR . '/build/' . $name . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>OWN Instagram Feed: de build ontbreekt. Draai <code>npm run build</code> of installeer de release-zip.</p></div>';
				}
			);
			return;
		}

		$asset  = require $asset_file;
		$handle = 'own-instagram-' . $name;

		wp_enqueue_script(
			$handle,
			plugins_url( 'build/' . $name . '.js', PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( $handle, Config::TEXT_DOMAIN, PLUGIN_DIR . '/languages' );
		wp_add_inline_script( $handle, 'window.ownInstagram = ' . wp_json_encode( $data ) . ';', 'before' );

		wp_enqueue_style( 'wp-components' );
		$css = PLUGIN_DIR . '/assets/css/admin.css';
		wp_enqueue_style( 'own-instagram-admin', plugins_url( 'assets/css/admin.css', PLUGIN_FILE ), array( 'wp-components' ), (string) filemtime( $css ) );
	}
}
