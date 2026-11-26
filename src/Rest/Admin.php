<?php
/**
 * REST routes for the admin apps (cookie auth + wp_rest nonce, as apiFetch
 * sends them).
 *
 *   GET  /site                          current site's status       manage
 *   POST /site/(connect|invite|sync|disconnect)                     manage
 *   GET  /app    POST /app              Meta app + relay settings    developer
 *   GET  /sites                         every site (network mode)    developer
 *   POST /sites/{id}/(connect|invite|sync|disconnect)               developer
 *
 * "connect" answers { url } to send the browser to; Instagram comes back to
 * returnUrl (the admin page that asked).
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Rest;

use OWN\InstagramFeed\Auth\Invites;
use OWN\InstagramFeed\Auth\OAuth;
use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\Sync;
use OWN\InstagramFeed\Settings\Access;
use OWN\InstagramFeed\Settings\App;
use OWN\InstagramFeed\Settings\RelayCheck;
use OWN\InstagramFeed\Sites;
use OWN\InstagramFeed\Status;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Admin {

	private const ACTIONS = '(?P<action>connect|invite|sync|disconnect)';

	public static function register(): void {
		$manage    = static fn() => Access::can_manage();
		$developer = static fn() => Access::is_developer();

		register_rest_route(
			Config::REST_NAMESPACE,
			'/site',
			array(
				'methods'             => 'GET',
				'permission_callback' => $manage,
				'callback'            => static fn() => rest_ensure_response( Status::site() ),
			)
		);
		register_rest_route(
			Config::REST_NAMESPACE,
			'/site/' . self::ACTIONS,
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => static fn( WP_REST_Request $r ) => self::action( get_current_blog_id(), (string) $r['action'], (string) $r->get_param( 'returnUrl' ) ),
				'args'                => array( 'returnUrl' => array( 'type' => 'string' ) ),
			)
		);
		register_rest_route(
			Config::REST_NAMESPACE,
			'/app',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $developer,
					'callback'            => static fn() => rest_ensure_response( App::for_admin() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $developer,
					'callback'            => array( self::class, 'save_app' ),
					'args'                => array(
						'id'          => array( 'type' => 'string' ),
						'secret'      => array( 'type' => 'string' ),
						'relay'       => array( 'type' => 'string' ),
						'clearSecret' => array( 'type' => 'boolean' ),
					),
				),
			)
		);
		register_rest_route(
			Config::REST_NAMESPACE,
			'/sites',
			array(
				'methods'             => 'GET',
				'permission_callback' => $developer,
				'callback'            => static fn() => rest_ensure_response( self::sites() ),
			)
		);
		register_rest_route(
			Config::REST_NAMESPACE,
			'/sites/(?P<id>\d+)/' . self::ACTIONS,
			array(
				'methods'             => 'POST',
				'permission_callback' => $developer,
				'callback'            => static fn( WP_REST_Request $r ) => self::action( (int) $r['id'], (string) $r['action'], (string) $r->get_param( 'returnUrl' ) ),
				'args'                => array( 'returnUrl' => array( 'type' => 'string' ) ),
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	private static function action( int $blog_id, string $action, string $return_url ) {
		if ( ! Sites::exists( $blog_id ) || ! in_array( $blog_id, Sites::ids(), true ) ) {
			return new WP_Error( 'own_instagram_site', __( 'Onbekende website.', 'own-instagram-feed' ), array( 'status' => 404 ) );
		}
		if ( in_array( $action, array( 'connect', 'invite' ), true ) && ! App::is_configured() ) {
			return new WP_Error( 'own_instagram_app', __( 'De Meta-app is nog niet ingesteld.', 'own-instagram-feed' ), array( 'status' => 400 ) );
		}

		if ( 'connect' === $action ) {
			return rest_ensure_response(
				array( 'url' => OAuth::authorize_url( $blog_id, OAuth::RETURN_ADMIN, self::safe_return_url( $return_url ) ) )
			);
		}
		if ( 'invite' === $action ) {
			Invites::create( $blog_id );
			return rest_ensure_response( Sites::in( $blog_id, array( Status::class, 'site' ) ) );
		}

		$error = Sites::in(
			$blog_id,
			static function () use ( $action ) {
				if ( 'disconnect' === $action ) {
					Connection::disconnect();
					return null;
				}
				return Sync::run();
			}
		);
		if ( 'disconnect' === $action ) {
			Invites::revoke( $blog_id );
		}
		if ( $error ) {
			return new WP_Error( 'own_instagram_sync', $error->get_error_message(), array( 'status' => 502 ) );
		}

		return rest_ensure_response( Sites::in( $blog_id, array( Status::class, 'site' ) ) );
	}

	/** @return WP_REST_Response */
	public static function save_app( WP_REST_Request $request ) {
		$values = array();
		foreach ( array( 'id', 'secret', 'relay' ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$values[ $key ] = (string) $request->get_param( $key );
			}
		}
		App::save( $values, (bool) $request->get_param( 'clearSecret' ) );
		RelayCheck::forget();

		return rest_ensure_response( App::for_admin() );
	}

	/** @return array<int, array<string, mixed>> */
	private static function sites(): array {
		$out = array();
		foreach ( Sites::ids() as $blog_id ) {
			$out[] = Sites::in( $blog_id, array( Status::class, 'site' ) );
		}
		return $out;
	}

	/**
	 * Only our own admin pages, on a site of this install, may be a return
	 * address: anything else falls back to the site's Instagram page.
	 */
	private static function safe_return_url( string $url ): string {
		$host  = wp_parse_url( $url, PHP_URL_HOST );
		$hosts = array( wp_parse_url( network_home_url(), PHP_URL_HOST ), wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( is_multisite() ) {
			foreach ( Sites::ids() as $blog_id ) {
				$hosts[] = wp_parse_url( get_home_url( $blog_id ), PHP_URL_HOST );
			}
		}

		$is_admin_page = false !== strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/wp-admin/' );

		return ( $host && $is_admin_page && in_array( $host, $hosts, true ) )
			? remove_query_arg( array( 'own_instagram', 'own_instagram_error', 'own_instagram_message' ), $url )
			: \OWN\InstagramFeed\Admin\SitePage::url();
	}
}
