<?php
/**
 * Public PHP API for themes and other plugins. These signatures are a
 * contract: they only ever get optional parameters added. Anything under
 * OWN\InstagramFeed\* is internal and may change between versions.
 *
 * Always guard calls with function_exists(), so a theme keeps working while
 * the plugin is deactivated.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'own_instagram_get_posts' ) ) {
	/**
	 * The current site's posts, newest first.
	 *
	 * @param array $args { limit?: int } 0 = all stored (max 12).
	 * @return array<int, array{id: string, permalink: string, caption: string,
	 *         type: string, timestamp: string, image_url: string, width: int,
	 *         height: int, username: string}>
	 */
	function own_instagram_get_posts( array $args = array() ): array {
		return \OWN\InstagramFeed\Feed\Posts::public_posts( (int) ( $args['limit'] ?? 0 ) );
	}
}

if ( ! function_exists( 'own_instagram_get_account' ) ) {
	/**
	 * The connected account of the current site.
	 *
	 * @return array{username: string, needs_reconnect: bool, synced_at: int}|null
	 */
	function own_instagram_get_account(): ?array {
		if ( ! \OWN\InstagramFeed\Feed\Connection::is_connected() ) {
			return null;
		}
		return array(
			'username'        => (string) ( \OWN\InstagramFeed\Feed\Connection::get()['username'] ?? '' ),
			'needs_reconnect' => \OWN\InstagramFeed\Feed\Connection::needs_reconnect(),
			'synced_at'       => \OWN\InstagramFeed\Feed\Posts::get()['synced_at'],
		);
	}
}

if ( ! function_exists( 'own_instagram_is_connected' ) ) {
	function own_instagram_is_connected(): bool {
		return \OWN\InstagramFeed\Feed\Connection::is_connected();
	}
}

if ( ! function_exists( 'own_instagram_render_feed' ) ) {
	/**
	 * The feed's HTML, as the block renders it. For theme blocks or templates
	 * that place the feed themselves.
	 *
	 * @param array $attributes { layout?: 'cards'|'grid', count?: 3|4|6|8, rounded?: bool, showEmpty?: bool }
	 * @param array $context    { wrapper_attributes?: string, is_preview?: bool }
	 */
	function own_instagram_render_feed( array $attributes = array(), array $context = array() ): string {
		return \OWN\InstagramFeed\Block\Feed::render( $attributes, $context );
	}
}

if ( ! function_exists( 'own_instagram_feed' ) ) {
	/**
	 * Prints the feed: for templates of older (non-block) themes. Same output
	 * as the block and the [own_instagram_feed] shortcode.
	 *
	 *   <?php if ( function_exists( 'own_instagram_feed' ) ) own_instagram_feed( [ 'count' => 4 ] ); ?>
	 *
	 * @param array $args {
	 *     @type string $layout  'cards' (default) or 'grid'.
	 *     @type int    $count   3, 4 (default), 6 or 8.
	 *     @type bool   $rounded Default true.
	 *     @type bool   $empty   Show the empty state when there are no posts. Default false.
	 *     @type string $class   Extra CSS classes on the wrapper.
	 * }
	 */
	function own_instagram_feed( array $args = array() ): void {
		echo \OWN\InstagramFeed\Block\Shortcode::html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in the template.
	}
}

if ( ! function_exists( 'own_instagram_template' ) ) {
	/**
	 * Renders a plugin template (theme overrides first), e.g. a card from an
	 * overridden feed.php.
	 */
	function own_instagram_template( string $name, array $args = array() ): string {
		return \OWN\InstagramFeed\Template::render( $name, $args );
	}
}
