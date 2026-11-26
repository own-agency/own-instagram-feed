<?php
/**
 * The public OAuth routes:
 *
 *   GET own-instagram/v1/oauth/connect?invite=…   a connect link → Instagram
 *   GET own-instagram/v1/oauth/callback?code&state  Instagram (or the relay) → here
 *
 * Public on purpose: Instagram's redirect carries no WordPress cookie nonce.
 * The single-use state (callback) and the secret link (connect) are the lock.
 * Both answer with a redirect or a small HTML page, never JSON.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Rest;

use OWN\InstagramFeed\Auth\Invites;
use OWN\InstagramFeed\Auth\OAuth as Auth;
use OWN\InstagramFeed\Config;
use OWN\InstagramFeed\Feed\Connection;
use OWN\InstagramFeed\Feed\Sync;
use OWN\InstagramFeed\Settings\App;
use OWN\InstagramFeed\Sites;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Oauth {

	public static function register(): void {
		register_rest_route(
			Config::REST_NAMESPACE,
			'/oauth/connect',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'connect' ),
			)
		);
		register_rest_route(
			Config::REST_NAMESPACE,
			'/oauth/callback',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'callback' ),
			)
		);
	}

	public static function connect( WP_REST_Request $request ): void {
		$token  = (string) $request->get_param( 'invite' );
		$invite = Invites::find( $token );

		if ( ! $invite ) {
			self::page( __( 'Link verlopen', 'own-instagram-feed' ), __( 'Deze koppellink is verlopen of al gebruikt. Vraag een nieuwe link aan bij de beheerder van de website.', 'own-instagram-feed' ) );
		}
		if ( ! App::is_configured() ) {
			self::page( __( 'Nog niet beschikbaar', 'own-instagram-feed' ), __( 'De Instagram-koppeling is nog niet ingesteld. Neem contact op met de beheerder van de website.', 'own-instagram-feed' ) );
		}

		wp_redirect( Auth::authorize_url( $invite['blog_id'], Auth::RETURN_INVITE, '', $token ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- Instagram's own login page.
		exit();
	}

	public static function callback( WP_REST_Request $request ): void {
		$pending = Auth::consume( (string) $request->get_param( 'state' ) );
		if ( ! $pending ) {
			self::page( __( 'Koppelen mislukt', 'own-instagram-feed' ), __( 'Deze koppelpoging is verlopen. Begin opnieuw.', 'own-instagram-feed' ) );
		}

		$blog_id   = (int) $pending['blog_id'];
		$site_name = '';
		$error     = null;

		if ( $request->get_param( 'error' ) ) {
			$error = new WP_Error( 'own_instagram', __( 'Er is geen toestemming gegeven op Instagram.', 'own-instagram-feed' ) );
		} elseif ( ! Sites::exists( $blog_id ) ) {
			$error = new WP_Error( 'own_instagram', __( 'De website bestaat niet meer.', 'own-instagram-feed' ) );
		} else {
			$code = (string) $request->get_param( 'code' );
			[ $error, $site_name ] = Sites::in(
				$blog_id,
				static function () use ( $code, $pending ) {
					$error = Auth::connect( $code, (string) $pending['redirect_uri'] );
					if ( ! $error ) {
						// A failing first sync (an image that won't save) is
						// not a failed connection: the token is in, the error
						// shows in the status and cron retries hourly.
						Sync::run();
					}
					return array( $error, (string) get_bloginfo( 'name' ) );
				}
			);
			if ( ! $error ) {
				Invites::revoke( $blog_id );
			}
		}

		if ( Auth::RETURN_ADMIN === $pending['return'] && '' !== $pending['return_url'] ) {
			$args = $error
				? array(
					'own_instagram'         => 'error',
					'own_instagram_message' => rawurlencode( mb_substr( $error->get_error_message(), 0, 200 ) ),
				)
				: array( 'own_instagram' => 'connected' );
			// The URL was built by this plugin when the login started; on a
			// subdomain network it is another host, so not wp_safe_redirect.
			wp_redirect( add_query_arg( $args, $pending['return_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit();
		}

		if ( $error ) {
			self::page( __( 'Koppelen mislukt', 'own-instagram-feed' ), $error->get_error_message() . ' ' . __( 'Probeer het opnieuw via dezelfde link, of neem contact op met de beheerder van de website.', 'own-instagram-feed' ) );
		}

		self::page(
			__( 'Instagram is gekoppeld', 'own-instagram-feed' ),
			/* translators: %s: site name */
			sprintf( __( 'Je Instagram-account is gekoppeld aan %s. De nieuwste posts verschijnen nu op de website. Je kunt dit venster sluiten.', 'own-instagram-feed' ), $site_name )
		);
	}

	/** A bare HTML page from a REST route. Ends the request. */
	private static function page( string $title, string $text ): void {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		nocache_headers();
		printf(
			'<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>%1$s</title>'
				. '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;font:16px/1.5 system-ui,sans-serif;color:#1d2327;background:#f6f7f7}main{max-width:440px;padding:32px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}h1{margin:0 0 8px;font-size:20px}p{margin:0;color:#50575e}</style>'
				. '</head><body><main><h1>%1$s</h1><p>%2$s</p></main></body></html>',
			esc_html( $title ),
			esc_html( $text )
		);
		exit();
	}
}
