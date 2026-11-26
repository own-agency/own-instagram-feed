<?php
/**
 * The feed. Override: <theme>/own-instagram/feed.php.
 *
 * $args (public API: keys are only ever added):
 *   posts              array   Posts, see own_instagram_get_posts().
 *   username           string  Instagram username, without "@".
 *   layout             string  'cards' or 'grid'.
 *   count              int     3, 4, 6 or 8.
 *   rounded            bool
 *   wrapper_attributes string  Ready-made attributes for the root element.
 *   is_preview         bool    Rendering in the block editor.
 *   needs_reconnect    bool    Connection broken; posts are the last good ones.
 *   empty              bool    No posts to show (not connected, nothing synced).
 *   message            string  When empty: why (editor) or a friendly line for
 *                              visitors (site, only with own_instagram/show_empty).
 *
 * Render a card with own_instagram_template( 'card.php', [ 'post' => …, … ] ).
 *
 * @package OWN\InstagramFeed
 */

defined( 'ABSPATH' ) || exit;

$own_instagram_new_tab = __( '(opent in een nieuw venster)', 'own-instagram-feed' );
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput -- Built by get_block_wrapper_attributes(). ?>>
	<?php if ( $args['empty'] ) : ?>
		<div class="own-instagram-feed__empty" role="status">
			<p class="own-instagram-feed__empty-title"><?php esc_html_e( 'Nog geen Instagram-posts', 'own-instagram-feed' ); ?></p>
			<p class="own-instagram-feed__empty-text"><?php echo esc_html( $args['message'] ); ?></p>
		</div>
	<?php else : ?>
		<?php if ( $args['is_preview'] && $args['needs_reconnect'] ) : ?>
			<p class="own-instagram-feed__message is-warning"><?php esc_html_e( 'De Instagram-koppeling moet opnieuw worden gemaakt. Dit zijn de laatst opgehaalde posts.', 'own-instagram-feed' ); ?></p>
		<?php endif; ?>
		<ul class="own-instagram-feed__list">
			<?php foreach ( $args['posts'] as $own_instagram_post ) : ?>
				<li class="own-instagram-feed__item">
					<?php if ( 'cards' === $args['layout'] ) : ?>
						<?php
						echo own_instagram_template( // phpcs:ignore WordPress.Security.EscapeOutput -- Template output.
							'card.php',
							array(
								'post'     => $own_instagram_post,
								'username' => $args['username'],
								'rounded'  => $args['rounded'],
							)
						);
						?>
					<?php else : ?>
						<a class="own-instagram-tile" href="<?php echo esc_url( $own_instagram_post['permalink'] ); ?>" target="_blank" rel="noopener noreferrer">
							<img
								src="<?php echo esc_url( $own_instagram_post['image_url'] ); ?>"
								alt="<?php echo esc_attr( '' !== $own_instagram_post['caption'] ? mb_strimwidth( $own_instagram_post['caption'], 0, 125, '…' ) : sprintf( /* translators: %s: username */ __( 'Instagram-post van @%s', 'own-instagram-feed' ), $args['username'] ) ); ?>"
								width="<?php echo (int) $own_instagram_post['width']; ?>"
								height="<?php echo (int) $own_instagram_post['height']; ?>"
								loading="lazy"
								decoding="async"
							>
							<span class="own-instagram-tile__overlay"><?php echo \OWN\InstagramFeed\Template::icon( 'instagram', 'own-instagram-icon is-large' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Local SVG. ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $own_instagram_new_tab ); ?></span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
