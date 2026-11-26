<?php
/**
 * Pattern "Instagram-feed met titel": a heading on the left, a button to the
 * site's Instagram account on the right and the feed below. Core blocks only
 * plus the plugin's own block, without theme presets (a theme's spacing
 * scale has its own slugs), so it fits any theme; the 4rem margins are
 * ordinary block settings an editor can change.
 *
 * The button links to the connected account as it is when the pattern is
 * inserted (without a connection: instagram.com); the editor can change it.
 * The feed itself reads the connection at render time.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Block;

use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Feed\Connection;

defined( 'ABSPATH' ) || exit;

final class Pattern {

	public const CATEGORY = 'own-instagram';

	public static function register(): void {
		register_block_pattern_category(
			self::CATEGORY,
			array( 'label' => __( 'Instagram', 'own-instagram-feed' ) )
		);

		register_block_pattern(
			'own-instagram/feed-with-title',
			array(
				'title'       => __( 'Instagram-feed met titel', 'own-instagram-feed' ),
				'description' => __( 'Titel links, een knop naar het Instagram-account rechts en de nieuwste posts eronder. Zonder posts toont de feed een lege melding.', 'own-instagram-feed' ),
				'categories'  => array( self::CATEGORY ),
				'keywords'    => array( 'instagram', 'social', 'feed', 'volgen' ),
				'blockTypes'  => array( Config::BLOCK_NAME ),
				'content'     => self::content(),
			)
		);
	}

	private static function content(): string {
		$username = (string) ( Connection::get()['username'] ?? '' );
		$url      = 'https://www.instagram.com/' . ( '' !== $username ? rawurlencode( $username ) . '/' : '' );

		$heading = esc_html__( 'Volg ons op Instagram', 'own-instagram-feed' );
		$button  = esc_html__( 'Bekijk alle posts', 'own-instagram-feed' );
		$href    = esc_url( $url );

		return <<<HTML
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"4rem","bottom":"4rem"},"blockGap":"1.5rem"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="margin-top:4rem;margin-bottom:4rem">
<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group">
<!-- wp:heading -->
<h2 class="wp-block-heading">{$heading}</h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$href}" target="_blank" rel="noreferrer noopener">{$button}</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:own-instagram/feed {"layout":"cards","count":4,"rounded":true,"showEmpty":true,"align":"wide"} /-->
</div>
<!-- /wp:group -->
HTML;
	}
}
