<?php
/**
 * uninstall.php — Runs when the plugin is deleted from WP Admin.
 * Removes all wp_options and transients created by this plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [
	// Connection / cache.
	'jetx_hub_token',
	'jetx_hub_db_id',
	'jetx_hub_cache_minutes',
	// v3 schema (now replaced by detected_schema).
	'jetx_hub_schema',
	// v4.0 auto-detection.
	'jetx_hub_detected_schema',
	'jetx_hub_active_fields',
	// v4.0 settings (branding, performance).
	'jetx_hub_settings',
	// Column / filter / sort / display conf