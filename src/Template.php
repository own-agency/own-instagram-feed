<?php
/**
 * Template loader. A theme overrides a template by copying it to
 * <theme>/own-instagram/<name>.php (child theme first). The $args each
 * template receives are part of the public API: add keys, never remove them.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

defined( 'ABSPATH' ) || exit;

final class Template {

	public static function locate( string $name ): string {
		$name  = sanitize_file_name( $name );
		$theme = locate_template( array( 'own-instagram/' . $name ) );

		/**
		 * Filters the path of a template.
		 *
		 * @param string $path
		 * @param string $name
		 */
		return (string) apply_filters( 'own_instagram/template', $theme ?: PLUGIN_DIR . '/templates/' . $name, $name );
	}

	/** Renders a template to a string. */
	public static function render( string $name, array $args ): string {
		$path = self::locate( $name );
		if ( ! is_readable( $path ) ) {
			return '';
		}
		ob_start();
		( static function ( string $__path, array $args ): void {
			include $__path;
		} )( $path, $args );
		return (string) ob_get_clean();
	}

	/** An inline SVG icon from assets/icons (stroke uses currentColor). */
	public static function icon( string $name, string $class = '' ): string {
		$path = PLUGIN_DIR . '/assets/icons/' . sanitize_file_name( $name ) . '.svg';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$svg = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local file.
		$svg = (string) preg_replace( '/<svg\b/', '<svg aria-hidden="true" focusable="false"' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ), $svg, 1 );
		return $svg;
	}
}
