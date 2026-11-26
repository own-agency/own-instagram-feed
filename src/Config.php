<?php
/**
 * Names that are stored in the database or used by other code. Changing any
 * of these orphans data: add an Upgrade migration instead.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed;

defined( 'ABSPATH' ) || exit;

final class Config {

	public const TEXT_DOMAIN = 'own-instagram-feed';

	// Per site (blog options).
	public const OPTION_CONNECTION = 'own_instagram_connection'; // Token, account, status. Autoloaded: small, read on every init.
	public const OPTION_FEED       = 'own_instagram_feed';       // Last good feed. Not autoloaded.
	public const OPTION_REMOVED    = 'own_instagram_removed';    // Username + date of the last automatic removal.
	public const OPTION_DB_VERSION = 'own_instagram_db_version';

	// Network-wide (site options) when network-active, otherwise per site.
	public const OPTION_APP     = 'own_instagram_app';      // { id, secret, relay }.
	public const OPTION_INVITES = 'own_instagram_invites';  // token => { blog_id, expires }.

	// Network-only migration marker (site option).
	public const OPTION_NETWORK_DB_VERSION = 'own_instagram_network_db_version';

	public const CRON_HOOK  = 'own_instagram_sync';
	public const CAPABILITY = 'own_instagram_manage';

	public const REST_NAMESPACE = 'own-instagram/v1';
	public const UPLOAD_DIR     = 'own-instagram';
	public const ADMIN_SLUG     = 'own-instagram';
	public const BLOCK_NAME     = 'own-instagram/feed';

	public const MAX_POSTS     = 12;
	public const IMAGE_WIDTH   = 640;
	public const IMAGE_QUALITY = 82;

	public const STATE_TTL      = 30 * MINUTE_IN_SECONDS;
	public const INVITE_TTL     = 14 * DAY_IN_SECONDS;
	public const REFRESH_AFTER  = WEEK_IN_SECONDS;
	public const REMOVE_AFTER   = 30 * DAY_IN_SECONDS;
	public const HTTP_TIMEOUT   = 15;
	public const IMAGE_TIMEOUT  = 20;

	/** Error code Meta uses for an invalid, expired or revoked token. */
	public const TOKEN_ERROR = 190;
}
