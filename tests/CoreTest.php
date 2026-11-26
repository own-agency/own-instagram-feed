<?php
/**
 * Standalone tests for the pure core: php tests/CoreTest.php
 *
 * Guards the contracts that must not break between versions: the relay state
 * format, Meta's signed_request, Meta's two error shapes, and the refresh /
 * cleanup timing.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

require __DIR__ . '/bootstrap.php';

use OWN\InstagramFeed\Api\Client;
use OWN\InstagramFeed\Auth\State;
use OWN\InstagramFeed\Feed\Cleanup;
use OWN\InstagramFeed\Feed\Sync;
use OWN\InstagramFeed\Upgrade;

$secret = 'test-secret';
$now    = 1_800_000_000;

// --- Relay state: fixed vector. If this changes, the deployed relay breaks.
$state = State::sign( 'https://school.test/wp-json/own-instagram/v1/oauth/callback', $secret, $now, 'abcdefghijklmnop' );
check(
	'state format is byte-stable',
	'eyJyIjoiaHR0cHM6XC9cL3NjaG9vbC50ZXN0XC93cC1qc29uXC9vd24taW5zdGFncmFtXC92MVwvb2F1dGhcL2NhbGxiYWNrIiwibiI6ImFiY2RlZmdoaWprbG1ub3AiLCJ0IjoxODAwMDAwMDAwfQ.uVFp8totHTDiL4bgWmGvNbIYXDzeP29_5Qcv5SDICuY' === $state
);
$verified = State::verify( $state, $secret, 1800, $now + 60 );
check( 'state verifies and returns the return address', 'https://school.test/wp-json/own-instagram/v1/oauth/callback' === ( $verified['r'] ?? '' ) );
check( 'tampered state is rejected', null === State::verify( $state . 'x', $secret, 1800, $now ) );
check( 'wrong secret is rejected', null === State::verify( $state, 'other', 1800, $now ) );
check( 'expired state is rejected', null === State::verify( $state, $secret, 1800, $now + 1801 ) );
check( 'state from the future is rejected', null === State::verify( $state, $secret, 1800, $now - 301 ) );
check( 'random state has no dot', false === strpos( State::random( 32 ), '.' ) && 32 === strlen( State::random( 32 ) ) );

// --- Meta signed_request (deauthorize / data deletion).
$payload = State::b64url( (string) json_encode( array( 'algorithm' => 'HMAC-SHA256', 'user_id' => '178', 'issued_at' => $now ) ) );
$signed  = State::b64url( hash_hmac( 'sha256', $payload, $secret, true ) ) . '.' . $payload;
check( 'signed_request verifies', '178' === ( State::verify_signed_request( $signed, $secret )['user_id'] ?? '' ) );
check( 'signed_request with wrong signature fails', null === State::verify_signed_request( 'x' . $signed, $secret ) );
$plain = State::b64url( (string) json_encode( array( 'algorithm' => 'NONE', 'user_id' => '1' ) ) );
check( 'signed_request without HMAC-SHA256 fails', null === State::verify_signed_request( State::b64url( hash_hmac( 'sha256', $plain, $secret, true ) ) . '.' . $plain, $secret ) );

// --- Meta's two error shapes.
$graph = Client::parse( 400, '{"error":{"message":"Session has expired","type":"OAuthException","code":190}}' );
check( 'Graph error → WP_Error with code', $graph instanceof WP_Error && 190 === $graph->get_error_data()['code'] );
check( 'Graph 190 is a token error', Client::is_token_error( $graph ) );
$basic = Client::parse( 400, '{"error_type":"OAuthException","code":400,"error_message":"Invalid platform app"}' );
check( 'api.instagram.com error → WP_Error', $basic instanceof WP_Error && 'Invalid platform app' === $basic->get_error_message() );
check( 'a 400 is not a token error', ! Client::is_token_error( $basic ) );
check( 'non-JSON 500 → WP_Error', Client::parse( 500, '<html>' ) instanceof WP_Error );
check( 'valid body passes through', array( 'data' => array() ) === Client::parse( 200, '{"data":[]}' ) );

// --- Refresh and cleanup timing.
check( 'no refresh within a week', ! Sync::refresh_due( $now - 6 * 86400, $now ) );
check( 'refresh after a week', Sync::refresh_due( $now - 8 * 86400, $now ) );
check( 'no cleanup when healthy', ! Cleanup::due( array( 'broken_since' => 0 ), $now ) );
check( 'no cleanup within 30 days', ! Cleanup::due( array( 'broken_since' => $now - 29 * 86400 ), $now ) );
check( 'cleanup after 30 days', Cleanup::due( array( 'broken_since' => $now - 31 * 86400 ), $now ) );

// --- Which image a post uses.
check( 'video uses its cover', 'c.jpg' === Sync::source_url( array( 'media_type' => 'VIDEO', 'media_url' => 'v.mp4', 'thumbnail_url' => 'c.jpg' ) ) );
check( 'image uses media_url', 'i.jpg' === Sync::source_url( array( 'media_type' => 'IMAGE', 'media_url' => 'i.jpg' ) ) );
check( 'album uses media_url', 'a.jpg' === Sync::source_url( array( 'media_type' => 'CAROUSEL_ALBUM', 'media_url' => 'a.jpg' ) ) );

// --- Converting the theme's old acf/instagram-feed block.
$content = "<!-- wp:heading -->\n<h2>Hi</h2>\n<!-- /wp:heading -->\n"
	. '<!-- wp:acf/instagram-feed {"name":"acf/instagram-feed","mode":"preview","layout":"grid","count":6,"rounded":false,"align":"wide"} /-->'
	. "\n<!-- wp:acf/instagram-feed {\"name\":\"acf/instagram-feed\",\"mode\":\"preview\"} /-->";
$out = Upgrade::convert_theme_blocks( $content );
check( 'old blocks converted, settings kept', str_contains( $out, '<!-- wp:own-instagram/feed {"layout":"grid","count":6,"rounded":false,"showEmpty":true,"align":"wide"} /-->' ) );
check( 'old block without settings gets defaults', str_contains( $out, '<!-- wp:own-instagram/feed {"layout":"cards","count":4,"rounded":true,"showEmpty":true} /-->' ) );
check( 'no old block left, other blocks untouched', ! str_contains( $out, 'acf/instagram-feed' ) && str_starts_with( $out, "<!-- wp:heading -->\n<h2>Hi</h2>" ) );
check( 'content without old blocks is unchanged', 'x' === Upgrade::convert_theme_blocks( 'x' ) );

done();
