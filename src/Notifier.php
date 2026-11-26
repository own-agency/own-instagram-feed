<?php
/**
 * The one e-mail the plugin sends: when a connection stops working.
 *
 * Recipient: OWN_INSTAGRAM_ALERT_EMAIL, else the network admin address
 * (multisite) or the site admin address. Filter: own_instagram/alert_email.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Admin\SitePage;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Notifier {

	public static function recipient(): string {
		$to = defined( 'OWN_INSTAGRAM_ALERT_EMAIL' )
			? (string) OWN_INSTAGRAM_ALERT_EMAIL
			: ( is_multisite() ? (string) get_site_option( 'admin_email' ) : (string) get_option( 'admin_email' ) );

		/**
		 * Filters who gets the "connection broken" e-mail. Empty = none.
		 *
		 * @param string $to
		 */
		return (string) apply_filters( 'own_instagram/alert_email', $to );
	}

	public static function connection_broken( array $connection, WP_Error $error ): void {
		$to = self::recipient();
		if ( '' === $to ) {
			return;
		}

		$site = get_bloginfo( 'name' ) ?: home_url( '/' );

		wp_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( 'Instagram-koppeling verbroken: %s', 'own-instagram-feed' ), $site ),
			sprintf(
				/* translators: 1: site name, 2: Instagram username, 3: error message, 4: admin URL */
				__( "De Instagram-koppeling van %1\$s (@%2\$s) werkt niet meer:\n\n%3\$s\n\nDe website toont de laatst opgehaalde posts tot het account opnieuw is gekoppeld. Gebeurt dat niet binnen 30 dagen, dan verwijdert de website de posts en afbeeldingen zelf.\n\nOpnieuw koppelen:\n%4\$s", 'own-instagram-feed' ),
				$site,
				(string) ( $connection['username'] ?? '?' ),
				$error->get_error_message(),
				SitePage::url()
			)
		);
	}
}
