<?php
/**
 * The own-instagram/feed block: registration and rendering.
 *
 * Dynamic: the PHP templates render both the editor preview
 * (ServerSideRender) and the site. Without a connection or posts it renders
 * nothing on the site; the editor says why.
 *
 * Default styles (assets/css/feed.css) load only on pages with the block. A
 * theme with its own templates turns them off with
 * add_theme_support( 'own-instagram-feed-styles', false ), or the filter
 * own_instagram/load_default_styles.
 *
 * Class lists never end in a digit: block output passes through
 * wptexturize(), which turns a digit followed by a quote into a prime.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Block;

use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\Posts;
use OWN\InstagramFeed\Settings\App;
use OWN\InstagramFeed\Template;
use const OWN\InstagramFeed\PLUGIN_DIR;
use const OWN\InstagramFeed\PLUGIN_FILE;
use const OWN\InstagramFeed\VERSION;

defined( 'ABSPATH' ) || exit;

final class Feed {

	public const COUNTS = array( 3, 4, 6, 8 );

	public static function register(): void {
		self::register_assets();
		Pattern::register();
		Shortcode::register();
		register_block_type(
			PLUGIN_DIR . '/block',
			array( 'render_callback' => array( self::class, 'render_block' ) )
		);
	}

	public static function styles_enabled(): bool {
		// add_theme_support( 'own-instagram-feed-styles', false ) stores [false].
		$support = get_theme_support( 'own-instagram-feed-styles' );
		$enabled = ! ( is_array( $support ) && false === ( $support[0] ?? true ) );

		/**
		 * Filters whether the plugin's default feed styles load.
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( 'own_instagram/load_default_styles', $enabled );
	}

	private static function register_assets(): void {
		$css = PLUGIN_DIR . '/assets/css/feed.css';
		wp_register_style(
			'own-instagram-feed',
			self::styles_enabled() ? plugins_url( 'assets/css/feed.css', PLUGIN_FILE ) : false,
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : VERSION
		);

		$asset_file = PLUGIN_DIR . '/build/block-editor.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array( 'dependencies' => array(), 'version' => VERSION );
		wp_register_script(
			'own-instagram-feed-block-editor',
			plugins_url( 'build/block-editor.js', PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'own-instagram-feed-block-editor', Config::TEXT_DOMAIN, PLUGIN_DIR . '/languages' );
	}

	/**
	 * @param array     $attributes
	 * @param string    $content
	 * @param \WP_Block $block
	 */
	public static function render_block( $attributes, $content = '', $block = null ): string {
		$attributes = self::normalize( (array) $attributes );

		return self::render(
			$attributes,
			array(
				'wrapper_attributes' => get_block_wrapper_attributes(
					array( 'class' => self::wrapper_class( $attributes ) )
				),
				'is_preview'         => self::is_editor_preview(),
			)
		);
	}

	/**
	 * Renders the feed outside a block too (own_instagram_render_feed()).
	 *
	 * @param array $attributes { layout, count, rounded, showEmpty }
	 * @param array $context    { wrapper_attributes?: string, is_preview?: bool }
	 */
	public static function render( array $attributes, array $context = array() ): string {
		$attributes = self::normalize( $attributes );
		$is_preview = (bool) ( $context['is_preview'] ?? false );
		$wrapper    = (string) ( $context['wrapper_attributes'] ?? 'class="' . esc_attr( self::wrapper_class( $attributes ) ) . '"' );
		$posts      = Posts::public_posts( $attributes['count'] );

		$message = '';
		if ( ! App::is_configured() ) {
			$message = __( 'De Instagram-koppeling is voor deze website nog niet ingesteld.', 'own-instagram-feed' );
		} elseif ( ! Connection::is_connected() ) {
			$message = __( 'Instagram is nog niet gekoppeld. Koppel het account via Instellingen → Instagram.', 'own-instagram-feed' );
		} elseif ( ! $posts ) {
			$message = __( 'Er zijn nog geen Instagram-posts opgehaald. Ze verschijnen hier na de eerstvolgende synchronisatie.', 'own-instagram-feed' );
		}
		$empty = '' !== $message;

		if ( $empty && ! $is_preview ) {
			/**
			 * Filters whether the site shows an empty state instead of
			 * nothing when there are no posts (yet). Useful when a heading or
			 * button sits above the feed. The template gets empty = true and
			 * a visitor-friendly message.
			 *
			 * @param bool $show The block's "Lege melding tonen" setting.
			 */
			if ( ! apply_filters( 'own_instagram/show_empty', $attributes['showEmpty'] ) ) {
				return '';
			}
			$message = __( 'Binnenkort zie je hier onze nieuwste posts.', 'own-instagram-feed' );
		}

		$args = array(
			'posts'              => $posts,
			'username'           => (string) ( Posts::get()['username'] ?: ( Connection::get()['username'] ?? '' ) ),
			'layout'             => $attributes['layout'],
			'count'              => $attributes['count'],
			'rounded'            => $attributes['rounded'],
			'wrapper_attributes' => $wrapper,
			'is_preview'         => $is_preview,
			'needs_reconnect'    => Connection::needs_reconnect(),
			'empty'              => $empty,
			'message'            => $message,
		);

		/**
		 * Filters the arguments handed to templates/feed.php.
		 *
		 * @param array $args
		 * @param array $attributes
		 */
		$args = (array) apply_filters( 'own_instagram/feed_args', $args, $attributes );

		if ( self::styles_enabled() ) {
			wp_enqueue_style( 'own-instagram-feed' );
		}

		return Template::render( 'feed.php', $args );
	}

	/** @return array{layout: string, count: int, rounded: bool, showEmpty: bool} */
	public static function normalize( array $attributes ): array {
		$count = (int) ( $attributes['count'] ?? 4 );
		return array(
			'layout'    => 'grid' === ( $attributes['layout'] ?? 'cards' ) ? 'grid' : 'cards',
			'count'     => in_array( $count, self::COUNTS, true ) ? $count : 4,
			'rounded'   => false !== ( $attributes['rounded'] ?? true ),
			'showEmpty' => ! empty( $attributes['showEmpty'] ),
		);
	}

	public static function wrapper_class( array $attributes ): string {
		return sprintf(
			'own-instagram-feed own-instagram-feed--count-%d own-instagram-feed--%s%s',
			$attributes['count'],
			$attributes['layout'],
			$attributes['rounded'] ? ' is-rounded' : ''
		);
	}

	/** ServerSideRender requests come in through REST with context=edit. */
	private static function is_editor_preview(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST
			&& 'edit' === ( $_GET['context'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- Read-only render context.
	}
}
