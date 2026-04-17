<?php
/**
 * Plugin Name:  JetX AI Intelligence Hub
 * Plugin URI:   https://www.jetxmedia.com
 * Description:  Live, configurable Notion-powered AI directory for WordPress.
 * Version:      3.0.0
 * Author:       JetX Media Inc.
 * Author URI:   https://www.jetxmedia.com
 * License:      GPL v2 or later
 * Text Domain:  jetx-ai-hub
 *
 * v3.0.0 — Refactored to multi-file architecture
 *   Separate PHP, HTML (view templates), CSS, and JS files.
 *   config.php centralises all white-label defaults.
 *   admin/ contains all wp-admin code.
 *   public/ contains all frontend templates.
 *   assets/ contains all static CSS and JS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Path constants ───────────────────────────────────────────────────────────
define( 'JETX_HUB_FILE',    __FILE__ );
define( 'JETX_HUB_PATH',    plugin_dir_path( __FILE__ ) );
define( 'JETX_HUB_URL',     plugin_dir_url( __FILE__ ) );
define( 'JETX_HUB_VERSION', '3.0.0' );

// ── Load config (all hardcoded defaults + white-label targets) ───────────────
require_once JETX_HUB_PATH . 'config.php';

// ── Core includes ────────────────────────────────────────────────────────────
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

function jetx_hub_activate() {
	jetx_hub_schedule_cron();
}

function jetx_hub_deactivate() {
	wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK );
}
