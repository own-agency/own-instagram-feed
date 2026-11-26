<?php
/**
 * Plugin Name:       OWN Instagram Feed
 * Plugin URI:        https://github.com/own-agency/own-instagram-feed
 * Description:       Toont de nieuwste posts van een Instagram-account als blok. Elke site koppelt zijn eigen account via de Meta-app van OWN; posts en afbeeldingen worden lokaal bewaard.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Tested up to:      7.1
 * Update URI:        https://github.com/own-agency/own-instagram-feed
 * Author:            OWN
 * Author URI:        https://ownagency.nl
 * License:           GPL-2.0-or-later
 * Text Domain:       own-instagram-feed
 * Domain Path:       /languages
 * Network:           false
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

defined( 'ABSPATH' ) || exit;

const VERSION     = '1.1.0';
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = PLUGIN_DIR . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

require PLUGIN_DIR . '/functions.php';

// Updates from the public GitHub releases (the release zip ships vendor/).
if ( is_readable( PLUGIN_DIR . '/vendor/autoload.php' ) ) {
	require PLUGIN_DIR . '/vendor/autoload.php';
	Updater::register();
}

register_activation_hook( __FILE__, array( Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Lifecycle::class, 'deactivate' ) );

// init priority 0, not plugins_loaded: the theme's functions.php runs in
// between, and both the legacy-engine guard and the theme's own_instagram/*
// filters (admin parent, styles) must be visible before anything registers.
add_action( 'init', array( Plugin::class, 'boot' ), 0 );
