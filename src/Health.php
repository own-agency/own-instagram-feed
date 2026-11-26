<?php
/**
 * Tools → Site Health: the things that make a feed stop without anyone
 * noticing.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\ImageStore;
use OWN\InstagramFeed\Settings\App;

defined( 'ABSPATH' ) || exit;

final class Health {

	public static function register(): void {
		add_filter( 'site_status_tests', array( self::class, 'tests' ) );
	}

	public static function tests( array $tests ): array {
		$tests['direct']['own_instagram'] = array(
			'label' => __( 'Instagram-feed', 'own-instagram-feed' ),
			'test'  => array( self::class, 'test' ),
		);
		return $tests;
	}

	public static function test(): array {
		$problems = array();

		if ( ! App::is_configured() ) {
			$problems[] = __( 'De Meta-app (Instagram app ID en secret) is niet ingesteld.', 'own-instagram-feed' );
		}
		if ( Connection::is_connected() ) {
			if ( Connection::needs_reconnect() ) {
				$problems[] = __( 'De Instagram-koppeling werkt niet meer en moet opnieuw worden gemaakt.', 'own-instagram-feed' );
			}
			if ( ! wp_next_scheduled( Config::CRON_HOOK ) ) {
				$problems[] = __( 'De uurlijkse synchronisatie staat niet ingepland.', 'own-instagram-feed' );
			}
			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && is_multisite() ) {
				$problems[] = __( 'WP-Cron staat uit. Controleer dat de servercron geplande taken van élke website draait, niet alleen van de hoofdsite.', 'own-instagram-feed' );
			}
		}
		$dir = ImageStore::dir()['path'];
		if ( ! wp_is_writable( is_dir( $dir ) ? $dir : dirname( $dir ) ) ) {
			$problems[] = __( 'De uploads-map is niet beschrijfbaar; afbeeldingen kunnen niet worden opgeslagen.', 'own-instagram-feed' );
		}
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) ) ) {
			$problems[] = __( 'De server kan geen JPEG-afbeeldingen verkleinen (GD of Imagick ontbreekt).', 'own-instagram-feed' );
		}

		$ok = ! $problems;

		return array(
			'label'       => $ok
				? __( 'De Instagram-feed werkt', 'own-instagram-feed' )
				: __( 'De Instagram-feed heeft aandacht nodig', 'own-instagram-feed' ),
			'status'      => $ok ? 'good' : 'recommended',
			'badge'       => array(
				'label' => __( 'Instagram', 'own-instagram-feed' ),
				'color' => $ok ? 'blue' : 'orange',
			),
			'description' => $ok
				? '<p>' . esc_html__( 'Koppeling, synchronisatie en opslag zijn in orde.', 'own-instagram-feed' ) . '</p>'
				: '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $problems ) ) . '</li></ul>',
			'actions'     => sprintf( '<a href="%s">%s</a>', esc_url( Admin\SitePage::url() ), esc_html__( 'Naar Instellingen → Instagram', 'own-instagram-feed' ) ),
			'test'        => 'own_instagram',
		);
	}
}
