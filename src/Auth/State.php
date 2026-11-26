<?php
/**
 * The OAuth state.
 *
 * Without a relay it is a random string. With a relay it is
 * "<payload>.<signature>": the payload (base64url JSON {r, n, t}) carries the
 * return address, the signature is base64url(HMAC-SHA256(payload, app
 * secret)). The relay (relay/worker.js) forwards only when the signature
 * checks out and t is under 30 minutes old.
 *
 * This format is a contract with the deployed relay: keep it byte-identical,
 * or old and new plugin versions stop working against the same relay.
 *
 * Pure functions, no WordPress: tested in tests/StateTest.php.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Auth;

defined( 'ABSPATH' ) || exit;

final class State {

	public static function b64url( string $raw ): string {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	public static function b64url_decode( string $encoded ): string {
		return (string) base64_decode( strtr( $encoded, '-_', '+/' ), true );
	}

	public static function random( int $length = 32 ): string {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$out   = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
		}
		return $out;
	}

	public static function sign( string $return_url, string $secret, ?int $now = null, ?string $nonce = null ): string {
		$payload = self::b64url(
			(string) json_encode(
				array(
					'r' => $return_url,
					'n' => $nonce ?? self::random( 16 ),
					't' => $now ?? time(),
				)
			)
		);

		return $payload . '.' . self::b64url( hash_hmac( 'sha256', $payload, $secret, true ) );
	}

	/**
	 * Verifies a signed state the way the relay does. Used by the tests and by
	 * Site Health to prove the relay and this site share the same secret.
	 *
	 * @return array{r: string, n: string, t: int}|null
	 */
	public static function verify( string $state, string $secret, int $max_age, ?int $now = null ): ?array {
		$parts = explode( '.', $state );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		[ $payload, $signature ] = $parts;
		if ( ! hash_equals( self::b64url( hash_hmac( 'sha256', $payload, $secret, true ) ), $signature ) ) {
			return null;
		}
		$data = json_decode( self::b64url_decode( $payload ), true );
		$now  = $now ?? time();
		if ( ! is_array( $data ) || (int) ( $data['t'] ?? 0 ) < $now - $max_age || (int) ( $data['t'] ?? 0 ) > $now + 300 ) {
			return null;
		}
		return array(
			'r' => (string) ( $data['r'] ?? '' ),
			'n' => (string) ( $data['n'] ?? '' ),
			't' => (int) $data['t'],
		);
	}

	/**
	 * Meta's signed_request (deauthorize, data deletion): "<sig>.<payload>",
	 * the reverse order of our own state.
	 *
	 * @return array|null Payload when the signature is valid.
	 */
	public static function verify_signed_request( string $signed_request, string $secret ): ?array {
		$parts = explode( '.', $signed_request );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		[ $signature, $payload ] = $parts;
		$data = json_decode( self::b64url_decode( $payload ), true );
		if ( ! is_array( $data ) || 'HMAC-SHA256' !== strtoupper( (string) ( $data['algorithm'] ?? '' ) ) ) {
			return null;
		}
		return hash_equals( hash_hmac( 'sha256', $payload, $secret, true ), self::b64url_decode( $signature ) ) ? $data : null;
	}
}
