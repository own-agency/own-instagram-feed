<?php
/**
 * Refreshes the token when due and fetches the latest posts, for the current
 * site. Runs hourly from cron, on connect and on "Nu verversen"; never while
 * a page renders.
 *
 * On any failure the previous feed stays up and the error is recorded.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Feed;

use OWN\InstagramFeed\Api\Client;
use OWN\InstagramFeed\Config;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Sync {

	public static function run(): ?WP_Error {
		$connection = Connection::get();
		if ( empty( $connection['token'] ) ) {
			return new WP_Error( 'own_instagram', __( 'Deze site is niet aan Instagram gekoppeld.', 'own-instagram-feed' ) );
		}

		if ( Cleanup::due( $connection ) ) {
			Cleanup::run();
			return new WP_Error( 'own_instagram', __( 'De koppeling was 30 dagen ongeldig. De posts en afbeeldingen zijn verwijderd.', 'own-instagram-feed' ) );
		}

		if ( ! empty( $connection['expires'] ) && (int) $connection['expires'] < time() ) {
			$error = new WP_Error( 'own_instagram', __( 'Het token is verlopen. Koppel het account opnieuw.', 'own-instagram-feed' ), array( 'code' => Config::TOKEN_ERROR ) );
			Connection::fail( $error );
			return $error;
		}

		if ( self::refresh_due( (int) ( $connection['refreshed_at'] ?? 0 ) ) ) {
			$refreshed = Client::refresh_token( (string) $connection['token'] );
			if ( is_wp_error( $refreshed ) ) {
				Connection::fail( $refreshed );
				if ( Client::is_token_error( $refreshed ) ) {
					return $refreshed;
				}
				// Temporary: the current token is still valid, carry on.
			} elseif ( ! empty( $refreshed['access_token'] ) ) {
				Connection::mark_refreshed( (string) $refreshed['access_token'], (int) ( $refreshed['expires_in'] ?? 60 * DAY_IN_SECONDS ) );
			}
			$connection = Connection::get();
		}

		$items = Client::media( (string) $connection['token'], Config::MAX_POSTS );
		if ( is_wp_error( $items ) ) {
			Connection::fail( $items );
			return $items;
		}

		$posts = self::store_items( array_slice( $items, 0, Config::MAX_POSTS ) );

		if ( ! $posts && $items ) {
			$error = new WP_Error( 'own_instagram', __( 'De afbeeldingen konden niet worden opgeslagen in de uploads-map.', 'own-instagram-feed' ) );
			Connection::fail( $error );
			return $error;
		}

		$username = (string) ( $connection['username'] ?? '' );
		Posts::save( $username, $posts );
		ImageStore::prune( array_column( $posts, 'file' ) );
		Connection::mark_healthy();

		/**
		 * Fires after a successful sync of the current site.
		 *
		 * @param int    $count    Number of posts stored.
		 * @param string $username
		 */
		do_action( 'own_instagram/synced', count( $posts ), $username );

		return null;
	}

	/** The token may be refreshed after 24h; weekly leaves ~50 days of margin. */
	public static function refresh_due( int $refreshed_at, ?int $now = null ): bool {
		return ( $now ?? time() ) - $refreshed_at > Config::REFRESH_AFTER;
	}

	/**
	 * Media item → stored post. Of a video only the cover image: the feed
	 * plays nothing. Pure apart from ImageStore, see source_url().
	 */
	public static function source_url( array $item ): string {
		return 'VIDEO' === ( $item['media_type'] ?? '' )
			? (string) ( $item['thumbnail_url'] ?? '' )
			: (string) ( $item['media_url'] ?? '' );
	}

	private static function store_items( array $items ): array {
		$posts = array();
		foreach ( $items as $item ) {
			$source = self::source_url( (array) $item );
			if ( '' === $source || empty( $item['id'] ) ) {
				continue;
			}
			$image = ImageStore::store( (string) $item['id'], $source );
			if ( is_wp_error( $image ) ) {
				continue;
			}
			$posts[] = array(
				'id'        => (string) $item['id'],
				'permalink' => (string) ( $item['permalink'] ?? '' ),
				'caption'   => (string) ( $item['caption'] ?? '' ),
				'type'      => (string) ( $item['media_type'] ?? 'IMAGE' ),
				'timestamp' => (string) ( $item['timestamp'] ?? '' ),
				'file'      => $image['file'],
				'width'     => $image['width'],
				'height'    => $image['height'],
			);
		}
		return $posts;
	}
}
