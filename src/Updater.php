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

		// Require the built zip. The default ("prefer") falls back to GitHub's
		// source zipball when a release has no asset, which lacks build/ and
		// vendor/: the admin breaks and updates stop. 2 is
		// Vcs\Api::REQUIRE_RELEASE_ASSETS; the literal because that class
		// lives in a versioned namespace (v5p7) that changes with PUC updates.
		$checker->getVcsApi()->enableReleaseAssets( '/own-instagram-feed\.zip$/', 2 );
	}
}
