<?php
/**
 * Removes everything of an account whose token has been invalid for 30 days.
 *
 * A revoked account (deauthorize or a data deletion request at Meta) always
 * ends up here, so deleting its data doesn't depend on anyone reading an
 * e-mail. A school that only changed its password has 30 days to reconnect
 * and keeps its last posts until then.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Feed;

use OWN\InstagramFeed\Config;

defined( 'ABSPATH' ) || exit;

final class Cleanup {

	public static function due( array $connection, ?int $now = null ): bool {
		$since = (int) ( $connection['broken_since'] ?? 0 );
		return $since > 0 && $since < ( $now ?? time() ) - Config::REMOVE_AFTER;
	}

	public static function run(): void {
		$username = (string) ( Connection::get()['username'] ?? '' );
		Connection::disconnect();
		update_option(
			Config::OPTION_REMOVED,
			array(
				'username' => $username,
				'at'       => time(),
			),
			false
		);
	}

	/** @return array{username: string, at: int}|null */
	public static function last_removal(): ?array {
		$removed = get_option( Config::OPTION_REMOVED );
		return is_array( $removed ) && ! empty( $removed['at'] )
			? array(
				'username' => (string) ( $removed['username'] ?? '' ),
				'at'       => (int) $removed['at'],
			)
			: null;
	}
}
