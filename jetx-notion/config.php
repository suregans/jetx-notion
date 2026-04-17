<?php
/**
 * config.php — Plugin infrastructure constants.
 *
 * Branding (name, URL, admin title, menu label) has moved to WP Admin →
 * Settings → JetX AI Hub → ⚙️ Settings tab. config.php now contains only
 * infrastructure constants that never change at runtime.
 *
 * WHITE-LABEL GUIDE (v4.0)
 * ─────────────────────────
 * 1. Install the plugin on the client's WordPress site.
 * 2. Go to Settings → JetX AI Hub → ⚙️ Settings and enter the client's
 *    branding name, URL, and preferred admin labels.
 * 3. Go to 🔌 Connection and enter the client's Notion API token + DB ID.
 * 4. Go to 🔍 Fields, click "Detect Fields from Notion", then toggle on/off
 *    the columns to display.
 * No PHP editing required.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Notion API ───────────────────────────────────────────────────────────────
define( 'JETX_HUB_API_BASE', 'https://api.notion.com/v1/' );
define( 'JETX_HUB_API_VER',  '2022-06-28' );

// ── Cache / performance ──────────────────────────────────────────────────────
define( 'JETX_HUB_CACHE_KEY',  'jetx_ai_hub_data' );
define( 'JETX_HUB_STALE_KEY',  'jetx_ai_hub_stale' );
define( 'JETX_HUB_LOCK_KEY',   'jetx_ai_hub_lock' );
define( 'JETX_HUB_CRON_HOOK',  'jetx_hub_cron_refresh' );

// ── Fallback defaults (used only before WP Admin settings are saved) ─────────
define( 'JETX_HUB_DEFAULT_CACHE_MINS', 60 ); // Minutes between auto-refreshes.
define( 'JETX_HUB_DEFAULT_MAX_PAGES',  20 ); // Max Notion API pages (100 rows each).

// ── Branding fallback defaults ───────────────────────────────────────────────
// These are used if the Settings tab has never been saved.
// Override these by saving the ⚙️ Settings tab in WP Admin.
define( 'JETX_HUB_DEFAULT_BRANDING_NAME',  'JetX Media' );
define( 'JETX_HUB_DEFAULT_BRANDING_URL',   'https://www.jetxmedia.com' );
define( 'JETX_HUB_DEFAULT_ADMIN_TITLE',    'JetX Notion' );
define( 'JETX_HUB_DEFAULT_MENU_LABEL',     'JetX Notion' );

// ── Helper: read a branding/settings value from wp_options (with fallback) ───
/**
 * Get a plugin setting with fallback to config.php constants.
 *
 * @param  string $key      Setting key: branding_name|branding_url|admin_title|menu_label|max_pages
 * @param  string $fallback Default value if not set.
 * @return string
 */
function jetx_hub_setting( string $key, string $fallback = '' ): string {
	static $settings = null;
	if ( $settings === null ) {
		$settings = get_option( 'jetx_hub_settings', [] );
	}

	if ( isset( $settings[ $key ] ) && $settings[ $key ] !== '' ) {
		return (string) $settings[ $key ];
	}

	// Fall back to compile-time constants.
	$const_map = [
		'brandin