<?php
/**
 * The last good feed of the current site.
 *
 * Stored (Config::OPTION_FEED): { synced_at, username, posts: [ { id,
 * permalink, caption, type, timestamp, file, width, height } ] }. `file` is
 * relative to the uploads folder, so a domain change doesn't break images.
 *
 * public_posts() is the stable read model behind own_instagram_get_posts():
 * it adds image_url and never exposes storage details.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Feed;

use OWN\InstagramFeed\Config;

defined( 'ABSPATH' ) || exit;

final class Posts {

	/** @return array{synced_at: int, username: string, posts: array<int, array>} */
	public static function get(): array {
		$feed = get_option( Config::OPTION_FEED, array() );
		$feed = is_array( $feed ) ? $feed : array();

		return array(
			'synced_at' => (int) ( $feed['synced_at'] ?? 0 ),
			'username'  => (string) ( $feed['username'] ?? '' ),
			'posts'     => array_values( (array) ( $feed['posts'] ?? array() ) ),
		);
	}

	public static function save( string $username, array $posts ): void {
		update_option(
			Config::OPTION_FEED,
			array(
				'synced_at' => time(),
				'username'  => $username,
				'posts'     => array_values( $posts ),
			),
			false
		);
	}

	public static function clear(): void {
		delete_option( Config::OPTION_FEED );
		ImageStore::prune( array() );
	}

	/**
	 * @return array<int, array{id: string, permalink: string, caption: string,
	 *         type: string, timestamp: string, image_url: string, width: int,
	 *         height: int, username: string}>
	 */
	public static function public_posts( int $limit = 0 ): array {
		$feed  = self::get();
		$base  = ImageStore::dir()['url'];
		$posts = array();

		foreach ( $feed['posts'] as $post ) {
			if ( empty( $post['file'] ) ) {
				continue;
			}
			$item = array(
				'id'        => (string) ( $post['id'] ?? '' ),
				'permalink' => (string) ( $post['permalink'] ?? '' ),
				'caption'   => trim( (string) preg_replace( '/\s+/', ' ', (string) ( $post['caption'] ?? '' ) ) ),
				'type'      => (string) ( $post['type'] ?? 'IMAGE' ),
				'timestamp' => (string) ( $post['timestamp'] ?? '' ),
				'image_url' => $base . '/' . $post['file'],
				'width'     => (int) ( $post['width'] ?? 0 ),
				'height'    => (int) ( $post['height'] ?? 0 ),
				'username'  => $feed['username'],
			);

			/**
			 * Filters one post as handed to templates and own_instagram_get_posts().
			 *
			 * @param array $item
			 */
			$posts[] = apply_filters( 'own_instagram/post', $item );
		}

		return $limit > 0 ? array_slice( $posts, 0, $limit ) : $posts;
	}
}
