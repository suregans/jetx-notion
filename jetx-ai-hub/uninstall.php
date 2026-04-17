<?php
/**
 * uninstall.php — Runs when the plugin is deleted from WP Admin.
 * Removes all options created by this plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [
	'jetx_hub_token',
	'jetx_hub_db_id',
	'jetx_hub_cache_minutes',
	'jetx_hub_schema',
	'jetx_hub_columns',
	'jetx_hub_filters',
	'jetx_hub_sorts',
	'jetx_hub_display',
	'jetx_ai_hub_stale',
];

foreach ( $options as $key ) {
	delete_option( $key );
}

delete_transient( 'jetx_ai_hub_data' );
delete_transient( 'jetx_ai_hub_lock' );
wp_clear_scheduled_hook( 'jetx_h