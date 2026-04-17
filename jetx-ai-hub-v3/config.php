<?php
/**
 * config.php — All plugin defaults and white-label targets.
 *
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  WHITE-LABEL GUIDE                                               ║
 * ║  To reskin this plugin for a different brand or database:        ║
 * ║  1. Change JETX_HUB_BRANDING_* constants below                  ║
 * ║  2. Clear JETX_HUB_DEFAULT_DB_ID (each install enters their own) ║
 * ║  3. Update property-defs.php to match the target Notion schema   ║
 * ║  4. Search/replace the text-domain 'jetx-ai-hub' if needed       ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Notion API ───────────────────────────────────────────────────────────────
define( 'JETX_HUB_API_BASE', 'https://api.notion.com/v1/' );
define( 'JETX_HUB_API_VER',  '2022-06-28' );

// ── Database default ─────────────────────────────────────────────────────────
// WHITE-LABEL: Set to '' so every install enters its own DB ID in WP Admin.
// JetX-specific value kept here as the fallback for this deployment only.
define( 'JETX_HUB_DEFAULT_DB_ID', 'eee2fe8a-fb05-4c59-954c-8fdb4d29c85d' );

// ── Branding ─────────────────────────────────────────────────────────────────
// WHITE-LABEL: Replace these with the reseller's name, URL, and desired plugin title.
define( 'JETX_HUB_BRANDING_NAME',  'JetX Media' );
define( 'JETX_HUB_BRANDING_URL',   'https://www.jetxmedia.com' );
define( 'JETX_HUB_ADMIN_TITLE',    '🤖 JetX AI Intelligence Hub' ); // WP Admin page heading.
define( 'JETX_HUB_MENU_LABEL',     'JetX AI Hub' );                  // Settings menu label.

// ── Cache / performance ──────────────────────────────────────────────────────
define( 'JETX_HUB_CACHE_KEY',  'jetx_ai_hub_data' );
define( 'JETX_HUB_STALE_KEY',  'jetx_ai_hub_stale' );
define( 'JETX_HUB_LOCK_KEY',   'jetx_ai_hub_lock' );
define( 'JETX_HUB_CRON_HOOK',  'jetx_hub_cron_refresh' );
define( 'JETX_HUB_MAX_PAGES',  20 );         // Max Notion API pages fetched per request (100 rows/page).
define( 'JETX_HUB_DEFAULT_CACHE_MINS', 60 ); // Default auto-refresh interval (minutes).

// ── Helper: derive the Notion database URL from the saved DB ID ──────────────
// This replaces the old hardcoded JETX_HUB_N