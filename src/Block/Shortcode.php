<?php
/**
 * The feed outside the block editor, for older themes, the classic editor and
 * page builders:
 *
 *   [own_instagram_feed layout="cards" count="4" rounded="yes" empty="yes" class=""]
 *   <?php if ( function_exists( 'own_instagram_feed' ) ) own_instagram_feed( [ 'count' => 4 ] ); ?>
 *
 * Same template, same CSS, same output as the block. Attributes (all
 * optional):
 *
 *   layout  cards (default) | grid
 *   count   3 | 4 (default) | 6 | 8
 *   rounded yes (default) | no
 *   empty   no (default) | yes   show the empty state when there are no posts
 *   class   extra CSS classes on the wrapper
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Block;

defined( 'ABSPATH' ) || exit;

final class Shortcode {

	public const TAG = 'own_instagram_feed';

	public static function register(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes ('' when none are given).
	 */
	public static function render( $atts = array() ): string {
		return self::html( (array) $atts );
	}

	/**
	 * Shortcode-style arguments (strings, "yes"/"no") or PHP arguments
	 * (bools, ints) → the feed's HTML.
	 */
	public static function html( array $args ): string {
		$attributes = self::attributes( $args );
		$class      = trim( Feed::wrapper_class( $attributes ) . ' ' . self::classes( (string) ( $args['class'] ?? '' ) ) );

		return Feed::render(
			$attributes,
			array( 'wrapper_attributes' => 'class="' . esc_attr( $class ) . '"' )
		);
	}

	/**
	 * Pure: arguments in, normalized block attributes out. Accepts both the
	 * shortcode names (empty) and the block names (showEmpty, show_empty).
	 *
	 * @return array{layout: string, count: int, rounded: bool, showEmpty: bool}
	 */
	public static function attributes( array $args ): array {
		$args = array_change_key_case( $args, CASE_LOWER );

		return Feed::normalize(
			array(
				'layout'    => (string) ( $args['layout'] ?? 'cards' ),
				'count'     => (int) ( $args['count'] ?? 4 ),
				'rounded'   => self::bool( $args['rounded'] ?? true ),
				'showEmpty' => self::bool( $args['empty'] ?? $args['showempty'] ?? $args['show_empty'] ?? false ),
			)
		);
	}

	/** "yes", "true", "1", "on" and true are yes; everything else is no. */
	public static function bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on', 'ja' ), true );
	}

	/** Only valid class names, so a shortcode can't inject attributes. */
	private static function classes( string $classes ): string {
		return implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $classes ) ?: array() ) ) );
	}
}
