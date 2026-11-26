<?php
/**
 * Who may do what.
 *
 *   Developer  The Meta app settings and the overview of all sites. Super
 *              admin on a multisite, manage_options on a single site; narrowed
 *              to the logins/e-mails in OWN_INSTAGRAM_DEVELOPERS when that
 *              constant is defined (needed as soon as a client is admin too).
 *              Filter: own_instagram/is_developer.
 *   Manager    The site's own connection (Settings → Instagram):
 *              Config::CAPABILITY, granted once to roles with
 *              edit_others_posts (Upgrade), then managed per role.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

namespace OWN\InstagramFeed\Settings;

use OWN\InstagramFeed\Config;

defined( 'ABSPATH' ) || exit;

final class Access {

	public static function is_developer( ?\WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();
		if ( ! $user->exists() ) {
			return false;
		}

		$allowed = is_multisite() ? is_super_admin( $user->ID ) : user_can( $user, 'manage_options' );

		if ( $allowed && defined( 'OWN_INSTAGRAM_DEVELOPERS' ) ) {
			$list    = array_map( 'strtolower', (array) OWN_INSTAGRAM_DEVELOPERS );
			$allowed = in_array( strtolower( $user->user_login ), $list, true )
				|| in_array( strtolower( $user->user_email ), $list, true );
		}

		/**
		 * Filters whether a user may manage the Meta app and see all sites.
		 *
		 * @param bool     $allowed
		 * @param \WP_User $user
		 */
		return (bool) apply_filters( 'own_instagram/is_developer', $allowed, $user );
	}

	public static function can_manage(): bool {
		return current_user_can( Config::CAPABILITY ) || self::is_developer();
	}

	/** Grants the capability to every role that can edit others' posts. */
	public static function grant_default_roles(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			if ( $role->has_cap( 'edit_others_posts' ) ) {
				$role->add_cap( Config::CAPABILITY );
			}
		}
	}

	public static function remove_from_roles(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( Config::CAPABILITY );
		}
	}
}
