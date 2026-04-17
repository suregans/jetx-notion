<?php
/**
 * Plugin Name:  JetX Notion
 * Plugin URI:   https://www.jetxmedia.com
 * Description:  Live, configurable Notion-powered directory for WordPress.
 * Version:      4.5.0
 * Requires PHP: 7.4
 * Author:       JetX Media Inc.
 * Author URI:   https://www.jetxmedia.com
 * License:      GPL v2 or later
 * Text Domain:  jetx-ai-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── PHP version gate (must come before ANY require with modern syntax) ────────
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p>'
		     . '<strong>JetX Notion</strong> requires <strong>PHP 7.4 or higher</strong>. '
		     . 'Your server is running PHP ' . esc_html( PHP_VERSION ) . '. '
		     . 'Please upgrade PHP and re-activate the plugin.</p></div>';
	} );
	add_action( 'admin_init', static function () {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	} );
	return;
}

// ── Path constants ───────────────────────────────────────────────────────────
define( 'JETX_HUB_FILE',    __FILE__ );
define( 'JETX_HUB_PATH',    plugin_dir_path( __FILE__ ) );
define( 'JETX_HUB_URL',     plugin_dir_url( __FILE__ ) );
define( 'JETX_HUB_VERSION', '4.5.0' );

// ── Load config ──────────────────────────────────────────────────────────────
require_once JETX_HUB_PATH . 'config.php';

// ── Core includes ────────────────────────────────────────────────────────────
require_once JETX_HUB_PATH . 'includes/notion-schema.php';
require_once JETX_HUB_PATH . 'includes/property-defs.php';
require_once JETX_HUB_PATH . 'includes/cache.php';
require_once JETX_HUB_PATH . 'includes/api.php';
require_once JETX_HUB_PATH . 'includes/shortcode.php';

// ── Admin includes (wp-admin only) ───────────────────────────────────────────
if ( is_admin() ) {
	require_once JETX_HUB_PATH . 'admin/save-handlers.php';
	require_once JETX_HUB_PATH . 'admin/admin.php';
}

// ── Lifecycle hooks ──────────────────────────────────────────────────────────
register_activation_hook(   JETX_HUB_FILE, 'jetx_hub_activate' );
register_deactivation_hook( JETX_HUB_FILE, 'jetx_hub_deactivate' );

function jetx_hub_activate(): void {
	jetx_hub_schedule_cron();
	// Flag for redirect — handled in admin_init so headers aren't sent yet.
	set_transient( 'jetx_hub_activated', true, 30 );
}

function jetx_hub_deactivate(): void {
	wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK );
}
