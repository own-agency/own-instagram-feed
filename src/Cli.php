<?php
/**
 * WP-CLI: wp own-instagram <status|sync|link|disconnect>. Works on the site
 * given with --url (multisite) like any wp command.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Auth\Invites;
use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\Sync;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class Cli {

	/**
	 * Shows the site's Instagram status.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 */
	public function status( array $args, array $assoc ): void {
		$status = Status::site();
		if ( 'json' === ( $assoc['format'] ?? 'table' ) ) {
			WP_CLI::line( (string) wp_json_encode( $status, JSON_PRETTY_PRINT ) );
			return;
		}
		$rows = array();
		foreach ( array( 'siteName', 'state', 'username', 'postCount', 'syncedAt', 'expiresAt', 'removeAt', 'cronNext', 'error' ) as $key ) {
			$value  = $status[ $key ];
			$rows[] = array(
				'field' => $key,
				'value' => in_array( $key, array( 'syncedAt', 'expiresAt', 'removeAt', 'cronNext' ), true ) && $value ? wp_date( 'Y-m-d H:i', (int) $value ) : (string) $value,
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}

	/** Refreshes the token when due and fetches the latest posts now. */
	public function sync(): void {
		$error = Sync::run();
		$error ? WP_CLI::error( $error->get_error_message() ) : WP_CLI::success( sprintf( '%d posts opgehaald.', Status::site()['postCount'] ) );
	}

	/** Creates a single-use connect link for this site and prints it. */
	public function link(): void {
		Invites::create( get_current_blog_id() );
		$invite = Invites::for_site( get_current_blog_id() );
		WP_CLI::line( (string) ( $invite['url'] ?? '' ) );
	}

	/**
	 * Disconnects Instagram and removes the stored posts and images.
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 */
	public function disconnect( array $args, array $assoc ): void {
		WP_CLI::confirm( 'Instagram ontkoppelen en de posts en afbeeldingen verwijderen?', $assoc );
		Connection::disconnect();
		Invites::revoke( get_current_blog_id() );
		WP_CLI::success( 'Ontkoppeld.' );
	}
}
