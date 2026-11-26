<?php
/**
 * Removes all plugin data on every site: connection, feed, images, cron,
 * capability, pending logins; plus the network-wide app settings and connect
 * links. Deactivating alone keeps everything.
 *
 * @package OWN\InstagramFeed
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$own_instagram_cleanup = static function (): void {
	foreach ( array( 'own_instagram_connection', 'own_instagram_feed', 'own_instagram_removed', 'own_instagram_db_version', 'own_instagram_app', 'own_instagram_invites' ) as $name ) {
		delete_option( $name );
	}
	wp_clear_scheduled_hook( 'own_instagram_sync' );

	foreach ( wp_roles()->role_objects as $role ) {
		$role->remove_cap( 'own_instagram_manage' );
	}

	$uploads = wp_upload_dir( null, false );
	$dir     = $uploads['basedir'] . '/own-instagram';
	foreach ( glob( $dir . '/*.jpg' ) ?: array() as $file ) {
		wp_delete_file( $file );
	}
	if ( is_dir( $dir ) ) {
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- Leave a folder someone added files to.
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $own_instagram_blog ) {
		switch_to_blog( (int) $own_instagram_blog );
		$own_instagram_cleanup();
		restore_current_blog();
	}
	foreach ( array( 'own_instagram_app', 'own_instagram_invites', 'own_instagram_network_db_version' ) as $own_instagram_name ) {
		delete_site_option( $own_instagram_name );
	}
} else {
	$own_instagram_cleanup();
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_site\\_transient\\_%own\\_instagram\\_state\\_%' OR option_name LIKE '\\_transient\\_%own\\_instagram\\_state\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( is_multisite() ) {
	$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '\\_site\\_transient\\_%own\\_instagram\\_state\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
