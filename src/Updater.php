<?php
/**
 * Updates from the public GitHub repository's releases, through the normal
 * Dashboard → Updates screen. The release zip (built by
 * .github/workflows/release.yml) is attached to each release as an asset;
 * Plugin Update Checker installs that asset, not the source tree.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

final class Updater {

	public const REPOSITORY = 'https://github.com/own-agency/own-instagram-feed/';

	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}
		$checker = PucFactory::buildUpdateChecker( self::REPOSITORY, PLUGIN_FILE, 'own-instagram-feed' );
		$checker->getVcsApi()->enableReleaseAssets( '/own-instagram-feed\.zip$/' );
	}
}
