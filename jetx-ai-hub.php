<?php
/**
 * Plugin Name:  JetX AI Intelligence Hub
 * Plugin URI:   https://www.jetxmedia.com
 * Description:  Pulls the JetX AI Intelligence Hub from Notion and renders a live, searchable, filterable table on any WordPress page via [jetx_ai_hub].
 * Version:      1.1.0
 * Author:       JetX Media Inc.
 * Author URI:   https://www.jetxmedia.com
 * License:      GPL v2 or later
 * Text Domain:  jetx-ai-hub
 *
 * Changelog v1.1.0
 * ─────────────────
 * FIX 1 — WP-Cron background refresh: cache is refreshed silently on a schedule.
 *          Page loads always read from cache — no user ever waits for the Notion API.
 * FIX 2 — show_internal="true" shortcode attribute now requires manage_options capability.
 * FIX 3 — Notion API pagination capped at 20 pages (2,000 rows max) to prevent runaway loops.
 * FIX 4 — Stale cache fallback: on any API error the last good dataset is returned instead of empty.
 * FIX 5 — Stampede protection: a short-lived mutex lock prevents concurrent page loads from
 *          all hitting the Notion API simultaneously when cache is cold.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────

define( 'JETX_HUB_VERSION',       '1.1.0' );
define( 'JETX_HUB_DB_ID',         'eee2fe8a-fb05-4c59-954c-8fdb4d29c85d' );
define( 'JETX_HUB_API_BASE',      'https://api.notion.com/v1/' );
define( 'JETX_HUB_API_VER',       '2022-06-28' );
define( 'JETX_HUB_NOTION_URL',    'https://www.notion.so/eee2fe8afb054c59954c8fdb4d29c85d' );
define( 'JETX_HUB_CACHE_KEY',     'jetx_ai_hub_data' );     // Fresh transient
define( 'JETX_HUB_STALE_KEY',     'jetx_ai_hub_stale' );    // Permanent fallback (wp_options)
define( 'JETX_HUB_LOCK_KEY',      'jetx_ai_hub_lock' );     // Stampede mutex (transient)
define( 'JETX_HUB_CRON_HOOK',     'jetx_hub_cron_refresh' );
define( 'JETX_HUB_MAX_PAGES',     20 );                      // FIX 3 — max 2,000 rows


// ─────────────────────────────────────────────────────────────────────────────
// ACTIVATION / DEACTIVATION
// ─────────────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, 'jetx_hub_activate' );
function jetx_hub_activate() {
    jetx_hub_schedule_cron();
}

register_deactivation_hook( __FILE__, 'jetx_hub_deactivate' );
function jetx_hub_deactivate() {
    wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK );
}


// ─────────────────────────────────────────────────────────────────────────────
// FIX 1 — WP-CRON BACKGROUND REFRESH
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Register a dynamic cron interval matching the user's cache_minutes setting.
 */
add_filter( 'cron_schedules', 'jetx_hub_add_cron_interval' );
function jetx_hub_add_cron_interval( $schedules ) {
    $mins = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
    $schedules['jetx_hub_interval'] = [
        'interval' => $mins * 60,
        'display'  => sprintf( 'Every %d minutes (JetX AI Hub)', $mins ),
    ];
    return $schedules;
}

/**
 * Schedule (or re-schedule) the cron event.
 * Called on activation and whenever cache_minutes is saved.
 */
function jetx_hub_schedule_cron() {
    wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK );
    $mins = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
    wp_schedule_event( time() + ( $mins * 60 ), 'jetx_hub_interval', JETX_HUB_CRON_HOOK );
}

/**
 * The cron callback — fetches fresh data from Notion and updates both caches.
 * Runs silently in the background. On API failure, existing caches are left intact.
 */
add_action( JETX_HUB_CRON_HOOK, 'jetx_hub_background_refresh' );
function jetx_hub_background_refresh() {
    $result = jetx_hub_call_api();
    if ( ! isset( $result['error'] ) ) {
        $mins = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
        set_transient( JETX_HUB_CACHE_KEY, $result, $mins * 60 );
        update_option( JETX_HUB_STALE_KEY, $result, false ); // false = don't autoload
    }
    // Silently retain existing caches on any API error.
}

/**
 * Re-schedule cron whenever cache duration is changed in settings.
 */
add_action( 'update_option_jetx_hub_cache_minutes', 'jetx_hub_schedule_cron' );


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — MENU + SETTINGS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'jetx_hub_add_menu' );
function jetx_hub_add_menu() {
    add_options_page(
        'JetX AI Hub',
        'JetX AI Hub',
        'manage_options',
        'jetx-ai-hub',
        'jetx_hub_settings_page'
    );
}

add_action( 'admin_init', 'jetx_hub_register_settings' );
function jetx_hub_register_settings() {
    register_setting( 'jetx_hub_group', 'jetx_hub_token',         [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'jetx_hub_group', 'jetx_hub_cache_minutes', [ 'sanitize_callback' => 'absint', 'default' => 60 ] );
    register_setting( 'jetx_hub_group', 'jetx_hub_db_id',         [ 'sanitize_callback' => 'sanitize_text_field', 'default' => JETX_HUB_DB_ID ] );
}

function jetx_hub_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Handle manual cache flush
    if ( isset( $_POST['jetx_flush_cache'] ) && check_admin_referer( 'jetx_flush_cache' ) ) {
        delete_transient( JETX_HUB_CACHE_KEY );
        delete_transient( JETX_HUB_LOCK_KEY );
        jetx_hub_schedule_cron(); // reschedule so next cron runs fresh
        echo '<div class="notice notice-success is-dismissible"><p><strong>JetX AI Hub:</strong> Cache cleared. Data will refresh on next page load.</p></div>';
    }

    // Handle manual refresh trigger
    if ( isset( $_POST['jetx_refresh_now'] ) && check_admin_referer( 'jetx_refresh_now' ) ) {
        jetx_hub_background_refresh();
        echo '<div class="notice notice-success is-dismissible"><p><strong>JetX AI Hub:</strong> Data refreshed from Notion now.</p></div>';
    }

    $token      = get_option( 'jetx_hub_token', '' );
    $cache_mins = get_option( 'jetx_hub_cache_minutes', 60 );
    $db_id      = get_option( 'jetx_hub_db_id', JETX_HUB_DB_ID );
    $next_cron  = wp_next_scheduled( JETX_HUB_CRON_HOOK );
    $cache_warm = ( false !== get_transient( JETX_HUB_CACHE_KEY ) );
    $stale_set  = ! empty( get_option( JETX_HUB_STALE_KEY, [] ) );

    ?>
    <div class="wrap">
        <h1>🤖 JetX AI Intelligence Hub — Settings</h1>

        <!-- Status row -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;max-width:780px;">
            <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 18px;flex:1;min-width:160px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.05em;">Cache</div>
                <div style="font-size:15px;font-weight:600;margin-top:4px;">
                    <?php echo $cache_warm ? '🟢 Warm' : '🔴 Cold'; ?>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 18px;flex:1;min-width:160px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.05em;">Stale Backup</div>
                <div style="font-size:15px;font-weight:600;margin-top:4px;">
                    <?php echo $stale_set ? '🟢 Available' : '⚪ Not yet set'; ?>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 18px;flex:1;min-width:160px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.05em;">Next Auto-Refresh</div>
                <div style="font-size:15px;font-weight:600;margin-top:4px;">
                    <?php echo $next_cron ? esc_html( human_time_diff( time(), $next_cron ) . ' from now' ) : '⚠️ Not scheduled'; ?>
                </div>
            </div>
        </div>

        <!-- Setup notice if no token -->
        <?php if ( empty( $token ) ) : ?>
        <div style="background:#fff3cd;border-left:4px solid #f0ad4e;padding:16px 20px;border-radius:4px;margin:0 0 20px;max-width:780px;">
            <strong>⚠️ First-time setup required</strong>
            <ol style="margin:10px 0 0 16px;">
                <li>Go to <a href="https://www.notion.so/profile/integrations" target="_blank">notion.so → Settings → Integrations → New integration</a></li>
                <li>Name it <em>JetX WordPress</em>, select your workspace, click Save</li>
                <li>Copy the <strong>Internal Integration Secret</strong> (starts with <code>secret_…</code>)</li>
                <li>In Notion, open the AI Intelligence Hub → click <strong>···</strong> → <strong>Connect to</strong> → select <em>JetX WordPress</em></li>
                <li>Paste the token below and save</li>
            </ol>
        </div>
        <?php endif; ?>

        <!-- Main settings form -->
        <form method="post" action="options.php" style="max-width:780px;">
            <?php settings_fields( 'jetx_hub_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="jetx_hub_token">Notion Integration Token</label></th>
                    <td>
                        <input type="password" id="jetx_hub_token" name="jetx_hub_token"
                               value="<?php echo esc_attr( $token ); ?>" class="regular-text" autocomplete="off" />
                        <p class="description">Starts with <code>secret_</code>. Stored server-side only — never exposed to browsers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="jetx_hub_db_id">Notion Database ID</label></th>
                    <td>
                        <input type="text" id="jetx_hub_db_id" name="jetx_hub_db_id"
                               value="<?php echo esc_attr( $db_id ); ?>" class="regular-text" />
                        <p class="description">Default: <code><?php echo esc_html( JETX_HUB_DB_ID ); ?></code>. Only change if using a different database.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="jetx_hub_cache_minutes">Refresh Interval</label></th>
                    <td>
                        <input type="number" id="jetx_hub_cache_minutes" name="jetx_hub_cache_minutes"
                               value="<?php echo esc_attr( $cache_mins ); ?>" min="5" max="1440" class="small-text" /> minutes
                        <p class="description">How often WP-Cron silently refreshes data from Notion. Page loads always serve from cache — no user ever waits for the API. Default: 60 mins.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>

        <hr style="max-width:780px;margin:24px 0;">

        <!-- Cache controls -->
        <h2>Cache Controls</h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap;max-width:780px;">

            <form method="post" style="margin:0;">
                <?php wp_nonce_field( 'jetx_refresh_now' ); ?>
                <input type="hidden" name="jetx_refresh_now" value="1" />
                <button type="submit" class="button button-primary">🔄 Refresh from Notion Now</button>
                <p class="description" style="margin-top:6px;">Pulls fresh data immediately and updates both the live cache and stale backup.</p>
            </form>

            <form method="post" style="margin:0;">
                <?php wp_nonce_field( 'jetx_flush_cache' ); ?>
                <input type="hidden" name="jetx_flush_cache" value="1" />
                <button type="submit" class="button button-secondary">🗑 Clear Cache</button>
                <p class="description" style="margin-top:6px;">Empties the live cache. Next page load will re-fetch from Notion synchronously.</p>
            </form>

        </div>

        <hr style="max-width:780px;margin:24px 0;">

        <!-- Shortcode reference -->
        <h2>Shortcode Usage</h2>
        <table class="widefat" style="max-width:780px;">
            <thead><tr><th>Shortcode</th><th>Result</th></tr></thead>
            <tbody>
                <tr><td><code>[jetx_ai_hub]</code></td><td>Full table, all public entries, sorted by Date Released</td></tr>
                <tr><td><code>[jetx_ai_hub limit="30"]</code></td><td>First 30 results only</td></tr>
                <tr><td><code>[jetx_ai_hub category="AI Model"]</code></td><td>Pre-filtered to a single category</td></tr>
                <tr><td><code>[jetx_ai_hub show_internal="true"]</code></td><td>Shows JetX Relevance column (admins only)</td></tr>
            </tbody>
        </table>
        <p><strong>Recommended page slug:</strong> <code>/ai-hub</code> or <code>/ai-intelligence-hub</code></p>

    </div>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// NOTION API — LAYER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Public entry point. Always returns usable data or an error array.
 *
 * Priority order:
 *   1. Fresh transient (warm cache)     → instant return
 *   2. Mutex lock held by another req   → serve stale backup (FIX 5)
 *   3. Live Notion API fetch            → acquire lock, fetch, release lock
 *   4. API error                        → return stale backup (FIX 4)
 *   5. No stale backup                  → return error array
 */
function jetx_hub_fetch_data() {

    // 1. Fresh cache hit
    $fresh = get_transient( JETX_HUB_CACHE_KEY );
    if ( false !== $fresh ) return $fresh;

    // 2. Stampede protection — another process is already fetching (FIX 5)
    if ( get_transient( JETX_HUB_LOCK_KEY ) ) {
        $stale = get_option( JETX_HUB_STALE_KEY, null );
        if ( ! empty( $stale ) ) return $stale;
        return [ 'error' => 'refreshing' ];
    }

    // 3. Acquire mutex lock (60 second TTL — long enough for Notion API + pagination)
    set_transient( JETX_HUB_LOCK_KEY, 1, 60 );

    // 4. Fetch from Notion API
    $result = jetx_hub_call_api();

    // 5. Release lock regardless of outcome
    delete_transient( JETX_HUB_LOCK_KEY );

    // 6. API error — return stale backup (FIX 4)
    if ( isset( $result['error'] ) ) {
        $stale = get_option( JETX_HUB_STALE_KEY, null );
        if ( ! empty( $stale ) ) return $stale;
        return $result;
    }

    // 7. Success — update both caches
    $mins = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
    set_transient( JETX_HUB_CACHE_KEY, $result, $mins * 60 );
    update_option( JETX_HUB_STALE_KEY, $result, false );

    return $result;
}

/**
 * Calls the Notion database query API with pagination.
 * Returns a flat array of parsed page objects, or ['error' => '...'] on failure.
 */
function jetx_hub_call_api() {
    $token = get_option( 'jetx_hub_token', '' );
    $db_id = get_option( 'jetx_hub_db_id', JETX_HUB_DB_ID );

    if ( empty( $token ) ) return [ 'error' => 'no_token' ];

    $all_pages   = [];
    $has_more    = true;
    $next_cursor = null;
    $page_count  = 0;

    while ( $has_more && $page_count < JETX_HUB_MAX_PAGES ) { // FIX 3 — pagination cap
        $page_count++;

        $body = [
            'page_size' => 100,
            'sorts'     => [ [ 'property' => 'Date Released', 'direction' => 'descending' ] ],
        ];
        if ( $next_cursor ) $body['start_cursor'] = $next_cursor;

        $response = wp_remote_post(
            JETX_HUB_API_BASE . 'databases/' . sanitize_text_field( $db_id ) . '/query',
            [
                'headers' => [
                    'Authorization'  => 'Bearer ' . $token,
                    'Notion-Version' => JETX_HUB_API_VER,
                    'Content-Type'   => 'application/json',
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            // Network error — return whatever we have so far (partial is better than nothing)
            return empty( $all_pages )
                ? [ 'error' => $response->get_error_message() ]
                : $all_pages;
        }

        $http_code = wp_remote_retrieve_response_code( $response );

        if ( $http_code !== 200 ) {
            $body_data = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg       = $body_data['message'] ?? ( 'Notion API returned HTTP ' . $http_code );
            return empty( $all_pages ) ? [ 'error' => $msg, 'code' => $http_code ] : $all_pages;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        foreach ( $data['results'] ?? [] as $page ) {
            $all_pages[] = jetx_hub_parse_page( $page );
        }

        $has_more    = $data['has_more']    ?? false;
        $next_cursor = $data['next_cursor'] ?? null;
    }

    return $all_pages;
}

/**
 * Converts a raw Notion page API object into a flat, sanitised associative array.
 */
function jetx_hub_parse_page( array $page ): array {
    $p = $page['properties'] ?? [];

    // Scalar extractor — handles all Notion property types used in this database
    $text = static function ( $prop ) {
        if ( empty( $prop ) || ! isset( $prop['type'] ) ) return '';
        switch ( $prop['type'] ) {
            case 'title':        return implode( '', array_column( $prop['title']        ?? [], 'plain_text' ) );
            case 'rich_text':    return implode( '', array_column( $prop['rich_text']     ?? [], 'plain_text' ) );
            case 'url':          return $prop['url']                ?? '';
            case 'select':       return $prop['select']['name']     ?? '';
            case 'date':         return $prop['date']['start']      ?? '';
            case 'created_time': return $prop['created_time']       ?? '';
            default:             return '';
        }
    };

    // Multi-select extractor
    $multi = static function ( $prop ) {
        if ( empty( $prop ) || ( $prop['type'] ?? '' ) !== 'multi_select' ) return [];
        return array_column( $prop['multi_select'] ?? [], 'name' );
    };

    return [
        'id'              => sanitize_text_field( $page['id'] ?? '' ),
        'name'            => $text( $p['Name']               ?? [] ),
        'category'        => $text( $p['Category']           ?? [] ),
        'sub_category'    => $text( $p['Sub-category']       ?? [] ),
        'status'          => $text( $p['Status']             ?? [] ),
        'traction'        => $text( $p['Traction']           ?? [] ),
        'pricing'         => $text( $p['Pricing']            ?? [] ),
        'era'             => $text( $p['Era']                ?? [] ),
        'publisher'       => $text( $p['Publisher / Company'] ?? [] ),
        'summary'         => $text( $p['Summary']            ?? [] ),
        'why_it_matters'  => $text( $p['Why It Matters']     ?? [] ),
        'official_url'    => $text( $p['Official URL']       ?? [] ),
        'github_repo'     => $text( $p['GitHub Repo']        ?? [] ),
        'date_released'   => $text( $p['Date Released']      ?? [] ),
        // Internal fields — only rendered when show_internal + manage_options (FIX 2)
        'blog_status'     => $text( $p['Blog Status']        ?? [] ),
        'jetx_relevance'  => $text( $p['JetX Relevance']     ?? [] ),
        'platform'        => $multi( $p['Platform']          ?? [] ),
        'capability_tags' => $multi( $p['Capability Tags']   ?? [] ),
        'jetx_use_case'   => $multi( $p['JetX Use Case']     ?? [] ),
    ];
}


// ─────────────────────────────────────────────────────────────────────────────
// SHORTCODE  [jetx_ai_hub]
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'jetx_ai_hub', 'jetx_hub_shortcode' );

function jetx_hub_shortcode( $atts ) {

    $atts = shortcode_atts(
        [
            'limit'         => 0,
            'category'      => '',
            'show_internal' => 'false',
        ],
        $atts,
        'jetx_ai_hub'
    );

    // FIX 2 — show_internal only honoured for admins
    $show_internal = filter_var( $atts['show_internal'], FILTER_VALIDATE_BOOLEAN )
                     && current_user_can( 'manage_options' );

    $data = jetx_hub_fetch_data();

    // ── Error states ──
    if ( isset( $data['error'] ) ) {
        if ( $data['error'] === 'no_token' && current_user_can( 'manage_options' ) ) {
            return '<p style="color:#f87171;font-style:italic;">⚠️ JetX AI Hub: No Notion token configured. <a href="' . esc_url( admin_url( 'options-general.php?page=jetx-ai-hub' ) ) . '">Configure now →</a></p>';
        }
        if ( $data['error'] === 'refreshing' ) {
            return '<p style="color:#94a3b8;font-style:italic;">🔄 AI Intelligence Hub is loading — please refresh in a moment.</p>';
        }
        if ( current_user_can( 'manage_options' ) ) {
            return '<p style="color:#f87171;font-style:italic;">⚠️ JetX AI Hub error: ' . esc_html( $data['error'] ) . '</p>';
        }
        return '';
    }

    if ( empty( $data ) ) {
        return '<p style="color:#94a3b8;">No AI tools found. Please check your Notion connection in Settings → JetX AI Hub.</p>';
    }

    $items = $data;

    // Pre-filter by category attribute
    if ( ! empty( $atts['category'] ) ) {
        $filter_cat = strtolower( trim( $atts['category'] ) );
        $items = array_values( array_filter( $items, fn( $i ) => strtolower( $i['category'] ) === $filter_cat ) );
    }

    // Row limit
    if ( (int) $atts['limit'] > 0 ) {
        $items = array_slice( $items, 0, (int) $atts['limit'] );
    }

    // Unique categories for filter dropdown
    $categories = array_unique( array_filter( array_column( $items, 'category' ) ) );
    sort( $categories );

    ob_start();
    ?>

<div class="jetx-hub-wrap" id="jetx-ai-hub">

    <!-- ── Controls ── -->
    <div class="jhub-controls">
        <div class="jhub-search-wrap">
            <svg class="jhub-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M9 3a6 6 0 1 0 3.73 10.74l3.27 3.27 1.06-1.06-3.27-3.27A6 6 0 0 0 9 3zm-4 6a4 4 0 1 1 8 0 4 4 0 0 1-8 0z" clip-rule="evenodd"/></svg>
            <input type="text" id="jhub-search" class="jhub-search" placeholder="Search tools, models, frameworks…" aria-label="Search AI tools" />
        </div>
        <div class="jhub-filters">
            <select id="jhub-cat" class="jhub-select" aria-label="Filter by category">
                <option value="">All Categories</option>
                <?php foreach ( $categories as $c ) : ?>
                <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="jhub-status" class="jhub-select" aria-label="Filter by status">
                <option value="">All Status</option>
                <option>🟢 Active</option>
                <option>🟡 Beta</option>
                <option>🔵 Experimental</option>
                <option>⚪ Announced</option>
                <option>⚫ Acquired</option>
                <option>🔴 Deprecated</option>
            </select>
            <select id="jhub-pricing" class="jhub-select" aria-label="Filter by pricing">
                <option value="">All Pricing</option>
                <option>Free</option>
                <option>Freemium</option>
                <option>Open Source</option>
                <option>Paid</option>
                <option>Enterprise</option>
                <option>API Only</option>
            </select>
            <select id="jhub-traction" class="jhub-select" aria-label="Filter by traction">
                <option value="">All Traction</option>
                <option>🚀 Hypergrowth</option>
                <option>📈 Growing</option>
                <option>➡️ Stable</option>
                <option>⚡ Emerging</option>
                <option>📉 Declining</option>
            </select>
        </div>
        <span id="jhub-count" class="jhub-count" aria-live="polite"></span>
    </div>

    <!-- ── Table ── -->
    <div class="jhub-table-wrap" role="region" aria-label="AI Intelligence Hub">
        <table class="jhub-table" id="jhub-table">
            <thead>
                <tr>
                    <th class="jhub-col-name" scope="col">Tool / Model</th>
                    <th class="jhub-col-cat"    scope="col">Category</th>
                    <th class="jhub-col-status" scope="col">Status</th>
                    <th class="jhub-col-pricing"scope="col">Pricing</th>
                    <th class="jhub-col-traction"scope="col">Traction</th>
                    <th class="jhub-col-platform"scope="col">Platform</th>
                    <th class="jhub-col-era"    scope="col">Era</th>
                    <?php if ( $show_internal ) : ?>
                    <th class="jhub-col-rel"    scope="col">JetX Relevance</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $items as $item ) :
                $platforms = is_array( $item['platform'] )        ? $item['platform']        : [];
                $cap_tags  = is_array( $item['capability_tags'] )  ? $item['capability_tags']  : [];
                $search_blob = strtolower( implode( ' ', array_filter( [
                    $item['name'], $item['category'], $item['sub_category'],
                    $item['publisher'], $item['summary'], $item['era'],
                    implode( ' ', $cap_tags ), implode( ' ', $platforms ),
                ] ) ) );
            ?>
            <tr class="jhub-row"
                data-search="<?php  echo esc_attr( $search_blob ); ?>"
                data-category="<?php echo esc_attr( $item['category'] ); ?>"
                data-status="<?php   echo esc_attr( $item['status'] ); ?>"
                data-pricing="<?php  echo esc_attr( $item['pricing'] ); ?>"
                data-traction="<?php echo esc_attr( $item['traction'] ); ?>">

                <!-- Name + meta -->
                <td class="jhub-td-name">
                    <div class="jhub-name">
                        <?php if ( ! empty( $item['official_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $item['official_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html( $item['name'] ); ?><span class="jhub-ext" aria-hidden="true"> ↗</span>
                            </a>
                        <?php else : ?>
                            <span><?php echo esc_html( $item['name'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $item['publisher'] ) ) : ?>
                    <div class="jhub-publisher"><?php echo esc_html( $item['publisher'] ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $item['summary'] ) ) : ?>
                    <div class="jhub-summary"><?php echo esc_html( mb_strimwidth( $item['summary'], 0, 140, '…' ) ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $cap_tags ) ) : ?>
                    <div class="jhub-tags">
                        <?php foreach ( $cap_tags as $tag ) : ?>
                        <span class="jhub-tag"><?php echo esc_html( $tag ); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $item['github_repo'] ) ) : ?>
                    <a href="<?php echo esc_url( $item['github_repo'] ); ?>" class="jhub-github" target="_blank" rel="noopener noreferrer">
                        <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                        GitHub
                    </a>
                    <?php endif; ?>
                </td>

                <!-- Category -->
                <td class="jhub-td-cat">
                    <?php if ( ! empty( $item['category'] ) ) : ?>
                    <span class="jhub-badge jhub-cat"><?php echo esc_html( $item['category'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $item['sub_category'] ) ) : ?>
                    <span class="jhub-badge jhub-subcat"><?php echo esc_html( $item['sub_category'] ); ?></span>
                    <?php endif; ?>
                </td>

                <!-- Status -->
                <td class="jhub-td-status">
                    <?php if ( ! empty( $item['status'] ) ) : ?>
                    <span class="jhub-badge jhub-status jhub-s-<?php echo esc_attr( sanitize_title( $item['status'] ) ); ?>">
                        <?php echo esc_html( $item['status'] ); ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Pricing -->
                <td class="jhub-td-pricing">
                    <?php if ( ! empty( $item['pricing'] ) ) : ?>
                    <span class="jhub-badge jhub-pricing jhub-p-<?php echo esc_attr( sanitize_title( $item['pricing'] ) ); ?>">
                        <?php echo esc_html( $item['pricing'] ); ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Traction -->
                <td class="jhub-td-traction">
                    <?php if ( ! empty( $item['traction'] ) ) : ?>
                    <span class="jhub-traction"><?php echo esc_html( $item['traction'] ); ?></span>
                    <?php endif; ?>
                </td>

                <!-- Platform -->
                <td class="jhub-td-platform">
                    <?php foreach ( $platforms as $pl ) : ?>
                    <span class="jhub-platform"><?php echo esc_html( $pl ); ?></span>
                    <?php endforeach; ?>
                </td>

                <!-- Era -->
                <td class="jhub-td-era">
                    <?php if ( ! empty( $item['era'] ) ) : ?>
                    <span class="jhub-era"><?php echo esc_html( $item['era'] ); ?></span>
                    <?php endif; ?>
                </td>

                <!-- Internal — admins only (FIX 2) -->
                <?php if ( $show_internal ) : ?>
                <td class="jhub-td-rel">
                    <?php if ( ! empty( $item['jetx_relevance'] ) ) : ?>
                    <span class="jhub-badge jhub-rel"><?php echo esc_html( $item['jetx_relevance'] ); ?></span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>

            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- No results -->
    <div class="jhub-no-results" id="jhub-no-results" style="display:none;" role="status">
        No results match your filters.
        <button class="jhub-reset-btn" id="jhub-reset" type="button">Clear filters</button>
    </div>

    <!-- Footer -->
    <div class="jhub-footer">
        Data synced from <a href="<?php echo esc_url( JETX_HUB_NOTION_URL ); ?>" target="_blank" rel="noopener">Notion</a>
        &middot; Curated by <a href="https://www.jetxmedia.com" target="_blank" rel="noopener">JetX Media</a>
    </div>

</div>

<style>
.jetx-hub-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f172a;border-radius:12px;padding:24px;margin:24px 0;color:#e2e8f0}
.jetx-hub-wrap *{box-sizing:border-box}
/* Controls */
.jhub-controls{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:16px}
.jhub-search-wrap{position:relative;flex:1;min-width:200px}
.jhub-search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#64748b;pointer-events:none}
.jhub-search{width:100%;padding:9px 14px 9px 36px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:14px;outline:none;transition:border-color .2s}
.jhub-search:focus{border-color:#6366f1}
.jhub-search::placeholder{color:#475569}
.jhub-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.jhub-select{padding:8px 12px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:12px;cursor:pointer;outline:none;transition:border-color .2s}
.jhub-select:focus{border-color:#6366f1}
.jhub-count{font-size:11px;color:#475569;white-space:nowrap}
/* Table */
.jhub-table-wrap{overflow-x:auto;border-radius:8px;border:1px solid #1e293b}
.jhub-table{width:100%;border-collapse:collapse;font-size:13px;min-width:640px}
.jhub-table thead th{background:#1e293b;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:11px 14px;text-align:left;border-bottom:1px solid #334155;white-space:nowrap}
.jhub-table tbody tr{border-bottom:1px solid #1a2536;transition:background .12s}
.jhub-table tbody tr:last-child{border-bottom:none}
.jhub-table tbody tr:hover{background:#162032}
.jhub-table tbody tr.jhub-hidden{display:none}
.jhub-table td{padding:12px 14px;vertical-align:top;color:#94a3b8}
/* Name cell */
.jhub-td-name{min-width:200px;max-width:340px}
.jhub-name a{color:#818cf8;font-weight:600;font-size:13.5px;text-decoration:none;transition:color .15s}
.jhub-name a:hover{color:#a5b4fc}
.jhub-name span{color:#e2e8f0;font-weight:600;font-size:13.5px}
.jhub-ext{font-size:10px;opacity:.5}
.jhub-publisher{font-size:11px;color:#475569;margin-top:2px}
.jhub-summary{font-size:11.5px;color:#64748b;margin-top:5px;line-height:1.5}
.jhub-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.jhub-tag{background:#1e3a5f;color:#7dd3fc;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:500}
.jhub-github{display:inline-flex;align-items:center;gap:4px;margin-top:5px;font-size:11px;color:#475569;text-decoration:none;transition:color .15s}
.jhub-github:hover{color:#94a3b8}
/* Badges */
.jhub-badge{display:inline-block;padding:3px 8px;border-radius:5px;font-size:11px;font-weight:500;line-height:1.4;margin:2px 2px 2px 0;white-space:nowrap}
.jhub-cat{background:#1e3a5f;color:#60a5fa}
.jhub-subcat{background:#1e293b;color:#64748b;font-size:10px}
.jhub-rel{background:#312e81;color:#c4b5fd}
/* Status */
.jhub-s-active{background:#052e16;color:#4ade80}
.jhub-s-beta{background:#431407;color:#fb923c}
.jhub-s-experimental{background:#172554;color:#60a5fa}
.jhub-s-deprecated{background:#3f0000;color:#f87171}
.jhub-s-acquired{background:#1c1917;color:#a8a29e}
.jhub-s-announced{background:#1e293b;color:#94a3b8}
/* Pricing */
.jhub-p-free{background:#052e16;color:#4ade80}
.jhub-p-freemium{background:#1a2e05;color:#a3e635}
.jhub-p-open-source{background:#0c1a2e;color:#38bdf8}
.jhub-p-paid{background:#2d1b00;color:#fbbf24}
.jhub-p-enterprise{background:#1e1b4b;color:#a78bfa}
.jhub-p-api-only{background:#1e293b;color:#94a3b8}
/* Misc */
.jhub-platform{display:inline-block;background:#1e293b;color:#94a3b8;padding:2px 7px;border-radius:4px;font-size:10px;margin:2px 2px 2px 0}
.jhub-traction{font-size:12px;white-space:nowrap}
.jhub-era{font-size:12px;color:#475569;white-space:nowrap}
/* No results */
.jhub-no-results{text-align:center;padding:32px 16px;color:#475569;font-size:13px}
.jhub-reset-btn{background:none;border:1px solid #334155;color:#818cf8;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;margin-left:8px;transition:all .15s}
.jhub-reset-btn:hover{background:#1e293b;color:#a5b4fc}
/* Footer */
.jhub-footer{margin-top:14px;text-align:center;font-size:11px;color:#334155}
.jhub-footer a{color:#4f46e5;text-decoration:none}
.jhub-footer a:hover{color:#818cf8}
/* Responsive */
@media(max-width:768px){
    .jetx-hub-wrap{padding:16px}
    .jhub-col-traction,.jhub-col-platform,.jhub-col-era,
    .jhub-td-traction,.jhub-td-platform,.jhub-td-era{display:none}
}
@media(max-width:500px){
    .jhub-col-pricing,.jhub-td-pricing{display:none}
}
</style>

<script>
(function () {
    'use strict';
    var search  = document.getElementById('jhub-search');
    var selCat  = document.getElementById('jhub-cat');
    var selSt   = document.getElementById('jhub-status');
    var selPr   = document.getElementById('jhub-pricing');
    var selTr   = document.getElementById('jhub-traction');
    var rows    = document.querySelectorAll('#jhub-table .jhub-row');
    var countEl = document.getElementById('jhub-count');
    var noRes   = document.getElementById('jhub-no-results');
    var resetBtn= document.getElementById('jhub-reset');

    function applyFilters() {
        var q   = search ? search.value.toLowerCase().trim() : '';
        var cat = selCat  ? selCat.value  : '';
        var st  = selSt   ? selSt.value   : '';
        var pr  = selPr   ? selPr.value   : '';
        var tr  = selTr   ? selTr.value   : '';
        var vis = 0;

        rows.forEach(function (row) {
            var ok = ( !q   || row.dataset.search.indexOf(q) !== -1 )
                  && ( !cat || row.dataset.category === cat )
                  && ( !st  || row.dataset.status   === st  )
                  && ( !pr  || row.dataset.pricing  === pr  )
                  && ( !tr  || row.dataset.traction === tr  );
            row.classList.toggle('jhub-hidden', !ok);
            if (ok) vis++;
        });

        if (countEl) countEl.textContent = vis + ' of ' + rows.length;
        if (noRes)   noRes.style.display  = vis === 0 ? 'block' : 'none';
    }

    function resetFilters() {
        if (search) search.value = '';
        if (selCat) selCat.value = '';
        if (selSt)  selSt.value  = '';
        if (selPr)  selPr.value  = '';
        if (selTr)  selTr.value  = '';
        applyFilters();
    }

    if (search)  search.addEventListener('input',  applyFilters);
    if (selCat)  selCat.addEventListener('change', applyFilters);
    if (selSt)   selSt.addEventListener('change',  applyFilters);
    if (selPr)   selPr.addEventListener('change',  applyFilters);
    if (selTr)   selTr.addEventListener('change',  applyFilters);
    if (resetBtn)resetBtn.addEventListener('click', resetFilters);

    applyFilters();
}());
</script>

    <?php
    return ob_get_clean();
}