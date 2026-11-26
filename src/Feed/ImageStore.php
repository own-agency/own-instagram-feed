<?php
/**
 * Local copies of the images, in uploads/own-instagram/<media-id>.jpg of the
 * current site.
 *
 * Local because Instagram's CDN URLs are signed and expire within days, and
 * so visitors load nothing from Meta. Outside the media library on purpose:
 * editors shouldn't pick these, and they're removed once they drop out of the
 * feed. A post never changes its image, so an existing file is reused and
 * each sync only downloads new posts.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Feed;

use OWN\InstagramFeed\Config;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ImageStore {

	/** @return array{path: string, url: string} */
	public static function dir(): array {
		$uploads = wp_upload_dir( null, false );
		return array(
			'path' => $uploads['basedir'] . '/' . Config::UPLOAD_DIR,
			'url'  => set_url_scheme( $uploads['baseurl'] . '/' . Config::UPLOAD_DIR ),
		);
	}

	/** @return array{file: string, width: int, height: int}|WP_Error */
	public static function store( string $media_id, string $url ) {
		$media_id = (string) preg_replace( '/\D/', '', $media_id );
		if ( '' === $media_id ) {
			return new WP_Error( 'own_instagram', 'Ongeldig media-ID.' );
		}

		$dir  = self::dir()['path'];
		$file = $media_id . '.jpg';
		$dest = $dir . '/' . $file;

		if ( file_exists( $dest ) ) {
			$size = wp_getimagesize( $dest );
			return array(
				'file'   => $file,
				'width'  => (int) ( $size[0] ?? 0 ),
				'height' => (int) ( $size[1] ?? 0 ),
			);
		}

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'own_instagram', __( 'De uploads-map is niet beschrijfbaar.', 'own-instagram-feed' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( $url, Config::IMAGE_TIMEOUT );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $tmp );
			return $editor;
		}

		$editor->resize( Config::IMAGE_WIDTH, null, false );
		$editor->set_quality( Config::IMAGE_QUALITY );
		$saved = $editor->save( $dest, 'image/jpeg' );
		wp_delete_file( $tmp );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'file'   => $file,
			'width'  => (int) $saved['width'],
			'height' => (int) $saved['height'],
		);
	}

	/** Removes every image not in $keep (file names). */
	public static function prune( array $keep ): void {
		foreach ( glob( self::dir()['path'] . '/*.jpg' ) ?: array() as $path ) {
			if ( ! in_array( basename( $path ), $keep, true ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/** Removes the folder itself (uninstall). */
	public static function remove_dir(): void {
		self::prune( array() );
		$path = self::dir()['path'];
		if ( is_dir( $path ) ) {
			@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- Not empty means someone put files there; leave them.
		}
	}
}
