<?php
/**
 * One post as a card. Override: <theme>/own-instagram/card.php.
 *
 * $args (public API: keys are only ever added):
 *   post      array   { id, permalink, caption, type (IMAGE|VIDEO|CAROUSEL_ALBUM),
 *                       timestamp, image_url, width, height, username }
 *   username  string  Instagram username, without "@".
 *   rounded   bool
 *
 * @package OWN\InstagramFeed
 */

use OWN\InstagramFeed\Template;

defined( 'ABSPATH' ) || exit;

$own_instagram_post  = $args['post'];
$own_instagram_types = array(
	'VIDEO'          => array( 'play', __( 'Video', 'own-instagram-feed' ) ),
	'CAROUSEL_ALBUM' => array( 'carousel', __( 'Album', 'own-instagram-feed' ) ),
	'IMAGE'          => array( 'camera', __( 'Foto', 'own-instagram-feed' ) ),
);
$own_instagram_type  = $own_instagram_types[ $own_instagram_post['type'] ] ?? $own_instagram_types['IMAGE'];
?>
<a class="own-instagram-card" href="<?php echo esc_url( $own_instagram_post['permalink'] ); ?>" target="_blank" rel="noopener noreferrer">
	<span class="own-instagram-card__header">
		<span class="own-instagram-card__user">@<?php echo esc_html( $args['username'] ); ?></span>
		<?php echo Template::icon( $own_instagram_type[0], 'own-instagram-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Local SVG. ?>
		<span class="screen-reader-text"><?php echo esc_html( $own_instagram_type[1] ); ?></span>
	</span>
	<span class="own-instagram-card__media">
		<?php // Empty alt when there is a caption: the caption below is already the link's text. ?>
		<img
			src="<?php echo esc_url( $own_instagram_post['image_url'] ); ?>"
			alt="<?php echo esc_attr( '' !== $own_instagram_post['caption'] ? '' : sprintf( /* translators: %s: username */ __( 'Instagram-post van @%s', 'own-instagram-feed' ), $args['username'] ) ); ?>"
			width="<?php echo (int) $own_instagram_post['width']; ?>"
			height="<?php echo (int) $own_instagram_post['height']; ?>"
			loading="lazy"
			decoding="async"
		>
		<?php if ( 'VIDEO' === $own_instagram_post['type'] ) : ?>
			<span class="own-instagram-card__play"><?php echo Template::icon( 'play', 'own-instagram-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Local SVG. ?></span>
		<?php endif; ?>
	</span>
	<?php if ( '' !== $own_instagram_post['caption'] ) : ?>
		<span class="own-instagram-card__caption"><span><?php echo esc_html( $own_instagram_post['caption'] ); ?></span></span>
	<?php endif; ?>
	<span class="screen-reader-text"><?php esc_html_e( '(opent in een nieuw venster)', 'own-instagram-feed' ); ?></span>
</a>
