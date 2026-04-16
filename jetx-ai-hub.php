<?php
/**
 * Plugin Name:  JetX AI Intelligence Hub
 * Plugin URI:   https://www.jetxmedia.com
 * Description:  Live, configurable Notion-powered AI directory for WordPress.
 * Version:      2.0.0
 * Author:       JetX Media Inc.
 * Author URI:   https://www.jetxmedia.com
 * License:      GPL v2 or later
 * Text Domain:  jetx-ai-hub
 *
 * v2.0.0 — Full admin settings page
 * ──────────────────────────────────
 * Tab 1  Connection    API token, database ID, cache, status, test connection
 * Tab 2  Columns       Show/hide columns, drag-to-reorder, custom labels
 * Tab 3  Filters       Dynamic filter rules applied to Notion API query
 * Tab 4  Sort          Multi-property sort rules applied to Notion API query
 * Tab 5  Display       Layout (Table/Gallery/List/Board), theme, Notion colors
 *
 * All v1.1.0 fixes retained:
 *   WP-Cron background refresh, show_internal capability gate,
 *   pagination cap, stale-cache fallback, stampede mutex.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────

define( 'JETX_HUB_VERSION',    '2.0.0' );
define( 'JETX_HUB_DB_ID',      'eee2fe8a-fb05-4c59-954c-8fdb4d29c85d' );
define( 'JETX_HUB_API_BASE',   'https://api.notion.com/v1/' );
define( 'JETX_HUB_API_VER',    '2022-06-28' );
define( 'JETX_HUB_NOTION_URL', 'https://www.notion.so/eee2fe8afb054c59954c8fdb4d29c85d' );
define( 'JETX_HUB_CACHE_KEY',  'jetx_ai_hub_data' );
define( 'JETX_HUB_STALE_KEY',  'jetx_ai_hub_stale' );
define( 'JETX_HUB_LOCK_KEY',   'jetx_ai_hub_lock' );
define( 'JETX_HUB_CRON_HOOK',  'jetx_hub_cron_refresh' );
define( 'JETX_HUB_MAX_PAGES',  20 );


// ─────────────────────────────────────────────────────────────────────────────
// PROPERTY DEFINITIONS  (used in admin dropdowns + API filter builder)
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_property_defs() {
    return [
        'name'            => [ 'label' => 'Name',              'notion' => 'Name',                'type' => 'title',        'internal' => false ],
        'category'        => [ 'label' => 'Category',          'notion' => 'Category',            'type' => 'select',       'internal' => false ],
        'sub_category'    => [ 'label' => 'Sub-category',      'notion' => 'Sub-category',        'type' => 'select',       'internal' => false ],
        'status'          => [ 'label' => 'Status',            'notion' => 'Status',              'type' => 'select',       'internal' => false ],
        'traction'        => [ 'label' => 'Traction',          'notion' => 'Traction',            'type' => 'select',       'internal' => false ],
        'pricing'         => [ 'label' => 'Pricing',           'notion' => 'Pricing',             'type' => 'select',       'internal' => false ],
        'platform'        => [ 'label' => 'Platform',          'notion' => 'Platform',            'type' => 'multi_select', 'internal' => false ],
        'capability_tags' => [ 'label' => 'Capability Tags',   'notion' => 'Capability Tags',     'type' => 'multi_select', 'internal' => false ],
        'era'             => [ 'label' => 'Era',               'notion' => 'Era',                 'type' => 'select',       'internal' => false ],
        'publisher'       => [ 'label' => 'Publisher / Co.',   'notion' => 'Publisher / Company', 'type' => 'rich_text',    'internal' => false ],
        'summary'         => [ 'label' => 'Summary',           'notion' => 'Summary',             'type' => 'rich_text',    'internal' => false ],
        'why_it_matters'  => [ 'label' => 'Why It Matters',    'notion' => 'Why It Matters',      'type' => 'rich_text',    'internal' => false ],
        'official_url'    => [ 'label' => 'Official URL',      'notion' => 'Official URL',        'type' => 'url',          'internal' => false ],
        'github_repo'     => [ 'label' => 'GitHub Repo',       'notion' => 'GitHub Repo',         'type' => 'url',          'internal' => false ],
        'date_released'   => [ 'label' => 'Date Released',     'notion' => 'Date Released',       'type' => 'date',         'internal' => false ],
        'blog_status'     => [ 'label' => 'Blog Status',       'notion' => 'Blog Status',         'type' => 'select',       'internal' => true  ],
        'jetx_relevance'  => [ 'label' => 'JetX Relevance',    'notion' => 'JetX Relevance',      'type' => 'select',       'internal' => true  ],
        'jetx_use_case'   => [ 'label' => 'JetX Use Case',     'notion' => 'JetX Use Case',       'type' => 'multi_select', 'internal' => true  ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// DEFAULT SETTINGS
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_default_columns() {
    return [
        [ 'key' => 'name',           'label' => 'Tool / Model',    'visible' => true,  'order' => 0  ],
        [ 'key' => 'category',       'label' => 'Category',        'visible' => true,  'order' => 1  ],
        [ 'key' => 'status',         'label' => 'Status',          'visible' => true,  'order' => 2  ],
        [ 'key' => 'pricing',        'label' => 'Pricing',         'visible' => true,  'order' => 3  ],
        [ 'key' => 'traction',       'label' => 'Traction',        'visible' => true,  'order' => 4  ],
        [ 'key' => 'platform',       'label' => 'Platform',        'visible' => true,  'order' => 5  ],
        [ 'key' => 'era',            'label' => 'Era',             'visible' => true,  'order' => 6  ],
        [ 'key' => 'sub_category',   'label' => 'Sub-category',    'visible' => false, 'order' => 7  ],
        [ 'key' => 'publisher',      'label' => 'Publisher',       'visible' => false, 'order' => 8  ],
        [ 'key' => 'why_it_matters', 'label' => 'Why It Matters',  'visible' => false, 'order' => 9  ],
        [ 'key' => 'date_released',  'label' => 'Date Released',   'visible' => false, 'order' => 10 ],
        [ 'key' => 'capability_tags','label' => 'Capability Tags', 'visible' => false, 'order' => 11 ],
        [ 'key' => 'blog_status',    'label' => 'Blog Status',     'visible' => false, 'order' => 12 ],
        [ 'key' => 'jetx_relevance', 'label' => 'JetX Relevance',  'visible' => false, 'order' => 13 ],
    ];
}

function jetx_hub_default_sorts() {
    return [ [ 'property' => 'date_released', 'direction' => 'descending' ] ];
}

function jetx_hub_default_display() {
    return [
        'layout'          => 'table',
        'theme'           => 'dark',
        'board_group_by'  => 'category',
        'show_search'     => true,
        'show_filters'    => true,
        'items_limit'     => 0,
        'use_notion_colors'=> true,
        'show_summary'    => true,
        'show_tags'       => true,
        'show_github'     => true,
        'show_footer'     => true,
    ];
}

// Notion color → CSS variables (both dark and light themes)
function jetx_hub_notion_colors() {
    return [
        'default' => [ 'dark' => [ 'bg' => '#1e293b', 'text' => '#94a3b8' ], 'light' => [ 'bg' => '#f1f5f9', 'text' => '#475569' ] ],
        'gray'    => [ 'dark' => [ 'bg' => '#1e293b', 'text' => '#94a3b8' ], 'light' => [ 'bg' => '#f1f5f9', 'text' => '#6b7280' ] ],
        'brown'   => [ 'dark' => [ 'bg' => '#292219', 'text' => '#c8a97e' ], 'light' => [ 'bg' => '#fef3c7', 'text' => '#92400e' ] ],
        'orange'  => [ 'dark' => [ 'bg' => '#2d1b00', 'text' => '#fb923c' ], 'light' => [ 'bg' => '#fff7ed', 'text' => '#c2410c' ] ],
        'yellow'  => [ 'dark' => [ 'bg' => '#2d2500', 'text' => '#fbbf24' ], 'light' => [ 'bg' => '#fefce8', 'text' => '#a16207' ] ],
        'green'   => [ 'dark' => [ 'bg' => '#052e16', 'text' => '#4ade80' ], 'light' => [ 'bg' => '#f0fdf4', 'text' => '#166534' ] ],
        'blue'    => [ 'dark' => [ 'bg' => '#1e3a5f', 'text' => '#60a5fa' ], 'light' => [ 'bg' => '#eff6ff', 'text' => '#1d4ed8' ] ],
        'purple'  => [ 'dark' => [ 'bg' => '#1e1b4b', 'text' => '#a78bfa' ], 'light' => [ 'bg' => '#faf5ff', 'text' => '#7e22ce' ] ],
        'pink'    => [ 'dark' => [ 'bg' => '#2d1a2e', 'text' => '#f472b6' ], 'light' => [ 'bg' => '#fdf2f8', 'text' => '#9d174d' ] ],
        'red'     => [ 'dark' => [ 'bg' => '#3f0000', 'text' => '#f87171' ], 'light' => [ 'bg' => '#fff5f5', 'text' => '#b91c1c' ] ],
    ];
}


// ─────────────────────────────────────────────────────────────────────────────
// ACTIVATION / DEACTIVATION / CRON
// ─────────────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, function() { jetx_hub_schedule_cron(); } );
register_deactivation_hook( __FILE__, function() { wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK ); } );

add_filter( 'cron_schedules', function( $s ) {
    $m = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
    $s['jetx_hub_interval'] = [ 'interval' => $m * 60, 'display' => "Every {$m} min (JetX Hub)" ];
    return $s;
} );

add_action( JETX_HUB_CRON_HOOK, 'jetx_hub_background_refresh' );
add_action( 'update_option_jetx_hub_cache_minutes', 'jetx_hub_schedule_cron' );

function jetx_hub_schedule_cron() {
    wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK );
    $m = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
    wp_schedule_event( time() + $m * 60, 'jetx_hub_interval', JETX_HUB_CRON_HOOK );
}

function jetx_hub_background_refresh() {
    $r = jetx_hub_call_api();
    if ( ! isset( $r['error'] ) ) {
        $m = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
        set_transient( JETX_HUB_CACHE_KEY, $r, $m * 60 );
        update_option( JETX_HUB_STALE_KEY, $r, false );
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — MENU + ENQUEUE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function() {
    add_options_page( 'JetX AI Hub', 'JetX AI Hub', 'manage_options', 'jetx-ai-hub', 'jetx_hub_admin_page' );
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'settings_page_jetx-ai-hub' ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
} );


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — SAVE HANDLERS  (run before page output)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_init', 'jetx_hub_handle_saves' );
function jetx_hub_handle_saves() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // ── Connection tab ──
    if ( isset( $_POST['jetx_save_connection'] ) && check_admin_referer( 'jetx_save_connection' ) ) {
        update_option( 'jetx_hub_token',         sanitize_text_field( $_POST['jetx_hub_token']         ?? '' ) );
        update_option( 'jetx_hub_db_id',         sanitize_text_field( $_POST['jetx_hub_db_id']         ?? JETX_HUB_DB_ID ) );
        update_option( 'jetx_hub_cache_minutes', absint( $_POST['jetx_hub_cache_minutes']               ?? 60 ) );
        jetx_hub_schedule_cron();
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&saved=1' ) );
        exit;
    }

    // ── Refresh now ──
    if ( isset( $_POST['jetx_refresh_now'] ) && check_admin_referer( 'jetx_refresh_now' ) ) {
        jetx_hub_background_refresh();
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&refreshed=1' ) );
        exit;
    }

    // ── Clear cache ──
    if ( isset( $_POST['jetx_flush_cache'] ) && check_admin_referer( 'jetx_flush_cache' ) ) {
        delete_transient( JETX_HUB_CACHE_KEY );
        delete_transient( JETX_HUB_LOCK_KEY );
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&flushed=1' ) );
        exit;
    }

    // ── Columns tab ──
    if ( isset( $_POST['jetx_save_columns'] ) && check_admin_referer( 'jetx_save_columns' ) ) {
        $order   = array_map( 'sanitize_text_field', (array) ( $_POST['col_order']   ?? [] ) );
        $visible = array_map( 'sanitize_text_field', (array) ( $_POST['col_visible'] ?? [] ) );
        $labels  = array_map( 'sanitize_text_field', (array) ( $_POST['col_label']   ?? [] ) );
        $defs    = jetx_hub_default_columns();
        $indexed = [];
        foreach ( $defs as $d ) $indexed[ $d['key'] ] = $d;
        $cols = [];
        foreach ( $order as $i => $key ) {
            if ( isset( $indexed[ $key ] ) ) {
                $cols[] = [
                    'key'     => $key,
                    'label'   => $labels[ $key ] ?? $indexed[ $key ]['label'],
                    'visible' => in_array( $key, $visible ),
                    'order'   => $i,
                ];
            }
        }
        update_option( 'jetx_hub_columns', $cols );
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=columns&saved=1' ) );
        exit;
    }

    // ── Filters tab ──
    if ( isset( $_POST['jetx_save_filters'] ) && check_admin_referer( 'jetx_save_filters' ) ) {
        $raw     = (array) ( $_POST['filters'] ?? [] );
        $filters = [];
        foreach ( $raw as $f ) {
            $prop = sanitize_text_field( $f['property'] ?? '' );
            $op   = sanitize_text_field( $f['operator']  ?? '' );
            $val  = sanitize_text_field( $f['value']     ?? '' );
            if ( $prop && $op ) $filters[] = [ 'property' => $prop, 'operator' => $op, 'value' => $val ];
        }
        update_option( 'jetx_hub_filters', $filters );
        delete_transient( JETX_HUB_CACHE_KEY ); // stale cache must refresh with new filters
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=filters&saved=1' ) );
        exit;
    }

    // ── Sort tab ──
    if ( isset( $_POST['jetx_save_sorts'] ) && check_admin_referer( 'jetx_save_sorts' ) ) {
        $raw   = (array) ( $_POST['sorts'] ?? [] );
        $sorts = [];
        foreach ( $raw as $s ) {
            $prop = sanitize_text_field( $s['property']  ?? '' );
            $dir  = sanitize_text_field( $s['direction'] ?? 'descending' );
            if ( $prop ) $sorts[] = [ 'property' => $prop, 'direction' => in_array( $dir, [ 'ascending', 'descending' ] ) ? $dir : 'descending' ];
        }
        update_option( 'jetx_hub_sorts', $sorts );
        delete_transient( JETX_HUB_CACHE_KEY );
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=filters&saved=1' ) );
        exit;
    }

    // ── Display tab ──
    if ( isset( $_POST['jetx_save_display'] ) && check_admin_referer( 'jetx_save_display' ) ) {
        $allowed_layouts = [ 'table', 'gallery', 'list', 'board' ];
        $d = [
            'layout'           => in_array( $_POST['layout']   ?? '', $allowed_layouts ) ? $_POST['layout'] : 'table',
            'theme'            => ( $_POST['theme'] ?? 'dark' ) === 'light' ? 'light' : 'dark',
            'board_group_by'   => sanitize_text_field( $_POST['board_group_by']  ?? 'category' ),
            'show_search'      => isset( $_POST['show_search'] ),
            'show_filters'     => isset( $_POST['show_filters'] ),
            'items_limit'      => absint( $_POST['items_limit'] ?? 0 ),
            'use_notion_colors'=> isset( $_POST['use_notion_colors'] ),
            'show_summary'     => isset( $_POST['show_summary'] ),
            'show_tags'        => isset( $_POST['show_tags'] ),
            'show_github'      => isset( $_POST['show_github'] ),
            'show_footer'      => isset( $_POST['show_footer'] ),
        ];
        update_option( 'jetx_hub_display', $d );
        wp_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=display&saved=1' ) );
        exit;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN — PAGE ROUTER
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $tab  = sanitize_key( $_GET['tab'] ?? 'connection' );
    $tabs = [
        'connection' => '🔌 Connection',
        'columns'    => '📋 Columns',
        'filters'    => '🔍 Filters & Sort',
        'display'    => '🎨 Display',
    ];

    echo '<div class="wrap"><h1>🤖 JetX AI Intelligence Hub</h1>';

    // Notice banner
    $notices = [
        'saved'     => [ 'success', 'Settings saved.' ],
        'refreshed' => [ 'success', 'Data refreshed from Notion.' ],
        'flushed'   => [ 'success', 'Cache cleared.' ],
    ];
    foreach ( $notices as $key => $n ) {
        if ( isset( $_GET[ $key ] ) ) {
            echo '<div class="notice notice-' . $n[0] . ' is-dismissible"><p>' . esc_html( $n[1] ) . '</p></div>';
        }
    }

    // Tab nav
    echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
    foreach ( $tabs as $key => $label ) {
        $url    = admin_url( 'options-general.php?page=jetx-ai-hub&tab=' . $key );
        $active = ( $tab === $key ) ? ' nav-tab-active' : '';
        echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . $active . '">' . esc_html( $label ) . '</a>';
    }
    echo '</nav>';

    // Dispatch to tab
    switch ( $tab ) {
        case 'columns': jetx_hub_tab_columns(); break;
        case 'filters': jetx_hub_tab_filters(); break;
        case 'display': jetx_hub_tab_display(); break;
        default:        jetx_hub_tab_connection(); break;
    }

    echo '</div>'; // .wrap
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN TAB 1 — CONNECTION
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_tab_connection() {
    $token     = get_option( 'jetx_hub_token', '' );
    $db_id     = get_option( 'jetx_hub_db_id', JETX_HUB_DB_ID );
    $mins      = get_option( 'jetx_hub_cache_minutes', 60 );
    $next_cron = wp_next_scheduled( JETX_HUB_CRON_HOOK );
    $warm      = false !== get_transient( JETX_HUB_CACHE_KEY );
    $stale     = ! empty( get_option( JETX_HUB_STALE_KEY, [] ) );
    ?>
    <!-- Status row -->
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;max-width:800px;">
        <?php
        $cards = [
            [ 'Cache',         $warm  ? '🟢 Warm'      : '🔴 Cold'         ],
            [ 'Stale Backup',  $stale ? '🟢 Available'  : '⚪ Not yet set'  ],
            [ 'Next Refresh',  $next_cron ? human_time_diff( time(), $next_cron ) . ' from now' : '⚠️ Not scheduled' ],
            [ 'Token',         ! empty( $token ) ? '🟢 Configured' : '🔴 Missing' ],
        ];
        foreach ( $cards as $c ) :
        ?>
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 18px;flex:1;min-width:140px;">
            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $c[0] ); ?></div>
            <div style="font-size:14px;font-weight:600;margin-top:4px;"><?php echo esc_html( $c[1] ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ( empty( $token ) ) : ?>
    <div style="background:#fff3cd;border-left:4px solid #f0ad4e;padding:14px 18px;border-radius:4px;margin-bottom:20px;max-width:800px;">
        <strong>⚠️ Setup required</strong> — create a Notion integration to get your token:
        <ol style="margin:8px 0 0 16px;line-height:1.8;">
            <li>Go to <a href="https://www.notion.so/profile/integrations" target="_blank">notion.so → Integrations → + New integration</a></li>
            <li>Name it <em>JetX WordPress</em>, select your workspace, click Save</li>
            <li>Copy the <strong>Internal Integration Secret</strong> (starts with <code>secret_…</code>)</li>
            <li>In Notion, open the AI Intelligence Hub → <strong>···</strong> → <strong>Connect to</strong> → select <em>JetX WordPress</em></li>
        </ol>
    </div>
    <?php endif; ?>

    <!-- Settings form -->
    <form method="post" style="max-width:800px;">
        <?php wp_nonce_field( 'jetx_save_connection' ); ?>
        <input type="hidden" name="jetx_save_connection" value="1">
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="jhub_token">Notion Integration Token</label></th>
                <td>
                    <input type="password" id="jhub_token" name="jetx_hub_token"
                           value="<?php echo esc_attr( $token ); ?>" class="regular-text" autocomplete="off">
                    <p class="description">Starts with <code>secret_…</code> — stored server-side only, never sent to browsers.</p>
                </td>
            </tr>
            <tr>
                <th><label for="jhub_db">Notion Database ID</label></th>
                <td>
                    <input type="text" id="jhub_db" name="jetx_hub_db_id"
                           value="<?php echo esc_attr( $db_id ); ?>" class="regular-text">
                    <p class="description">Default: <code><?php echo esc_html( JETX_HUB_DB_ID ); ?></code></p>
                </td>
            </tr>
            <tr>
                <th><label for="jhub_mins">Auto-refresh interval</label></th>
                <td>
                    <input type="number" id="jhub_mins" name="jetx_hub_cache_minutes"
                           value="<?php echo esc_attr( $mins ); ?>" min="5" max="1440" class="small-text"> minutes
                    <p class="description">WP-Cron refreshes data silently in the background. Use 5 for testing, 60 for production.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Save Connection Settings', 'primary', 'jetx_save_connection_btn' ); ?>
    </form>

    <hr style="max-width:800px;margin:24px 0;">
    <h2 style="margin-bottom:12px;">Cache Controls</h2>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <form method="post">
            <?php wp_nonce_field( 'jetx_refresh_now' ); ?>
            <input type="hidden" name="jetx_refresh_now" value="1">
            <button class="button button-primary" type="submit">🔄 Refresh from Notion Now</button>
        </form>
        <form method="post">
            <?php wp_nonce_field( 'jetx_flush_cache' ); ?>
            <input type="hidden" name="jetx_flush_cache" value="1">
            <button class="button button-secondary" type="submit">🗑 Clear Cache</button>
        </form>
    </div>

    <hr style="max-width:800px;margin:24px 0;">
    <h2>Shortcode</h2>
    <p>Add <code>[jetx_ai_hub]</code> to any page or post.</p>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN TAB 2 — COLUMNS
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_tab_columns() {
    $saved = get_option( 'jetx_hub_columns', jetx_hub_default_columns() );
    // Merge saved with defaults (in case new columns were added)
    $defs    = jetx_hub_default_columns();
    $saved_keys = array_column( $saved, 'key' );
    foreach ( $defs as $d ) {
        if ( ! in_array( $d['key'], $saved_keys ) ) $saved[] = $d;
    }
    usort( $saved, fn( $a, $b ) => $a['order'] <=> $b['order'] );

    $props = jetx_hub_property_defs();
    ?>
    <p style="max-width:700px;color:#555;">Drag rows to reorder columns. Toggle visibility with the checkbox. Rename the column header label. Internal columns (marked 🔒) are only shown when <code>show_internal="true"</code> is set in the shortcode by a WordPress admin.</p>

    <form method="post" style="max-width:760px;">
        <?php wp_nonce_field( 'jetx_save_columns' ); ?>
        <input type="hidden" name="jetx_save_columns" value="1">

        <table class="wp-list-table widefat fixed striped" id="jhub-col-table">
            <thead>
                <tr>
                    <th style="width:30px;"></th>
                    <th style="width:60px;">Show</th>
                    <th>Column Key</th>
                    <th>Header Label</th>
                    <th style="width:80px;">Type</th>
                    <th style="width:60px;">Access</th>
                </tr>
            </thead>
            <tbody id="jhub-sortable">
            <?php foreach ( $saved as $col ) :
                $def      = $props[ $col['key'] ] ?? null;
                $internal = $def ? $def['internal'] : false;
                $type     = $def ? $def['type'] : '—';
            ?>
            <tr style="cursor:move;">
                <td style="vertical-align:middle;">
                    <span style="color:#aaa;font-size:18px;cursor:grab;" title="Drag to reorder">⠿</span>
                    <input type="hidden" name="col_order[]" value="<?php echo esc_attr( $col['key'] ); ?>">
                </td>
                <td style="vertical-align:middle;text-align:center;">
                    <input type="checkbox" name="col_visible[]"
                           value="<?php echo esc_attr( $col['key'] ); ?>"
                           <?php checked( $col['visible'] ); ?>>
                </td>
                <td style="vertical-align:middle;">
                    <code><?php echo esc_html( $col['key'] ); ?></code>
                </td>
                <td style="vertical-align:middle;">
                    <input type="text" name="col_label[<?php echo esc_attr( $col['key'] ); ?>]"
                           value="<?php echo esc_attr( $col['label'] ); ?>" class="regular-text" style="width:100%;">
                </td>
                <td style="vertical-align:middle;color:#888;font-size:12px;"><?php echo esc_html( $type ); ?></td>
                <td style="vertical-align:middle;font-size:12px;"><?php echo $internal ? '🔒 Admin' : '🌐 Public'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:8px;font-size:12px;color:#888;">Note: <em>name</em>, <em>summary</em>, <em>official_url</em>, <em>github_repo</em>, and <em>capability_tags</em> are rendered inside the Tool/Model cell rather than as separate columns.</p>

        <?php submit_button( 'Save Column Settings', 'primary', 'jetx_save_col_btn' ); ?>
    </form>

    <script>
    jQuery(function($){
        $('#jhub-sortable').sortable({ handle: 'span[title]', axis: 'y', opacity: 0.7 });
    });
    </script>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN TAB 3 — FILTERS & SORT
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_tab_filters() {
    $filters = get_option( 'jetx_hub_filters', [] );
    $sorts   = get_option( 'jetx_hub_sorts', jetx_hub_default_sorts() );
    $props   = jetx_hub_property_defs();

    $filter_operators = [
        'select'       => [ 'equals' => 'is', 'does_not_equal' => 'is not', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
        'multi_select' => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
        'title'        => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
        'rich_text'    => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'equals' => 'equals', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
        'url'          => [ 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
        'date'         => [ 'after' => 'after', 'before' => 'before', 'on_or_after' => 'on or after', 'on_or_before' => 'on or before', 'is_empty' => 'is empty' ],
    ];
    $ops_json = json_encode( $filter_operators );
    $types_json = json_encode( array_map( fn($p) => $p['type'], $props ) );
    ?>

    <!-- FILTERS -->
    <h2>Filter Rules</h2>
    <p style="color:#555;max-width:700px;">Filter rules are sent to the Notion API — only matching entries are fetched. Adding or removing rules clears the cache automatically.</p>

    <form method="post" style="max-width:800px;">
        <?php wp_nonce_field( 'jetx_save_filters' ); ?>
        <input type="hidden" name="jetx_save_filters" value="1">

        <table class="wp-list-table widefat" id="jhub-filter-table">
            <thead>
                <tr><th>Property</th><th>Operator</th><th>Value</th><th style="width:60px;"></th></tr>
            </thead>
            <tbody id="jhub-filter-rows">
            <?php foreach ( $filters as $i => $f ) : ?>
            <tr class="jhub-filter-row">
                <td>
                    <select name="filters[<?php echo $i; ?>][property]" class="jhub-prop-sel" style="width:100%;">
                        <option value="">— Select —</option>
                        <?php foreach ( $props as $key => $def ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['property'], $key ); ?>>
                            <?php echo esc_html( $def['label'] ); ?> (<?php echo esc_html( $def['type'] ); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="filters[<?php echo $i; ?>][operator]" class="jhub-op-sel" style="width:100%;">
                        <?php
                        $ptype = $props[ $f['property'] ]['type'] ?? 'select';
                        $ops   = $filter_operators[ $ptype ] ?? $filter_operators['select'];
                        foreach ( $ops as $val => $lbl ) :
                        ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['operator'], $val ); ?>>
                            <?php echo esc_html( $lbl ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" name="filters[<?php echo $i; ?>][value]"
                           value="<?php echo esc_attr( $f['value'] ?? '' ); ?>" style="width:100%;">
                </td>
                <td>
                    <button type="button" class="button jhub-remove-row">✕</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" class="button" id="jhub-add-filter" style="margin-top:8px;">+ Add Filter Rule</button>
        <p class="description">All rules are combined with AND logic. Leave Value empty for "is empty" / "is not empty" operators.</p>
        <?php submit_button( 'Save Filters', 'primary', 'jhub_save_filter_btn', false ); ?>
    </form>

    <hr style="margin:28px 0;">

    <!-- SORT -->
    <h2>Sort Rules</h2>
    <p style="color:#555;max-width:700px;">Rules are applied in order — first rule has highest priority.</p>

    <form method="post" style="max-width:600px;">
        <?php wp_nonce_field( 'jetx_save_sorts' ); ?>
        <input type="hidden" name="jetx_save_sorts" value="1">

        <table class="wp-list-table widefat" id="jhub-sort-table">
            <thead>
                <tr><th>Property</th><th style="width:160px;">Direction</th><th style="width:60px;"></th></tr>
            </thead>
            <tbody id="jhub-sort-rows">
            <?php foreach ( $sorts as $i => $s ) : ?>
            <tr class="jhub-sort-row">
                <td>
                    <select name="sorts[<?php echo $i; ?>][property]" style="width:100%;">
                        <option value="">— Select —</option>
                        <?php foreach ( $props as $key => $def ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['property'], $key ); ?>>
                            <?php echo esc_html( $def['label'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="sorts[<?php echo $i; ?>][direction]" style="width:100%;">
                        <option value="descending" <?php selected( $s['direction'], 'descending' ); ?>>↓ Descending</option>
                        <option value="ascending"  <?php selected( $s['direction'], 'ascending'  ); ?>>↑ Ascending</option>
                    </select>
                </td>
                <td><button type="button" class="button jhub-remove-row">✕</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" class="button" id="jhub-add-sort" style="margin-top:8px;">+ Add Sort Rule</button>
        <?php submit_button( 'Save Sort Rules', 'primary', 'jhub_save_sort_btn', false ); ?>
    </form>

    <!-- Template rows (hidden, cloned by JS) -->
    <template id="jhub-filter-tpl">
        <tr class="jhub-filter-row">
            <td>
                <select name="filters[__IDX__][property]" class="jhub-prop-sel" style="width:100%;">
                    <option value="">— Select —</option>
                    <?php foreach ( $props as $key => $def ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?> (<?php echo esc_html( $def['type'] ); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><select name="filters[__IDX__][operator]" class="jhub-op-sel" style="width:100%;"><option value="contains">contains</option></select></td>
            <td><input type="text" name="filters[__IDX__][value]" style="width:100%;"></td>
            <td><button type="button" class="button jhub-remove-row">✕</button></td>
        </tr>
    </template>

    <template id="jhub-sort-tpl">
        <tr class="jhub-sort-row">
            <td>
                <select name="sorts[__IDX__][property]" style="width:100%;">
                    <option value="">— Select —</option>
                    <?php foreach ( $props as $key => $def ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="sorts[__IDX__][direction]" style="width:100%;">
                    <option value="descending">↓ Descending</option>
                    <option value="ascending">↑ Ascending</option>
                </select>
            </td>
            <td><button type="button" class="button jhub-remove-row">✕</button></td>
        </tr>
    </template>

    <script>
    (function(){
        var ops   = <?php echo $ops_json; ?>;
        var types = <?php echo $types_json; ?>;

        function reindex(tbody, prefix) {
            tbody.querySelectorAll('tr').forEach(function(tr, i){
                tr.querySelectorAll('[name]').forEach(function(el){
                    el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                });
            });
        }

        function updateOperators(propSel) {
            var key  = propSel.value;
            var type = types[key] || 'select';
            var opSel = propSel.closest('tr').querySelector('.jhub-op-sel');
            if (!opSel) return;
            var available = ops[type] || ops['select'];
            opSel.innerHTML = '';
            Object.entries(available).forEach(function([val, lbl]){
                var o = document.createElement('option');
                o.value = val; o.textContent = lbl;
                opSel.appendChild(o);
            });
        }

        document.addEventListener('change', function(e){
            if (e.target.classList.contains('jhub-prop-sel')) updateOperators(e.target);
        });

        document.addEventListener('click', function(e){
            if (e.target.classList.contains('jhub-remove-row')) {
                e.target.closest('tr').remove();
            }
        });

        document.getElementById('jhub-add-filter')?.addEventListener('click', function(){
            var tbody = document.getElementById('jhub-filter-rows');
            var tpl   = document.getElementById('jhub-filter-tpl').content.cloneNode(true);
            var idx   = tbody.querySelectorAll('tr').length;
            tpl.querySelectorAll('[name]').forEach(function(el){
                el.name = el.name.replace('__IDX__', idx);
            });
            tbody.appendChild(tpl);
        });

        document.getElementById('jhub-add-sort')?.addEventListener('click', function(){
            var tbody = document.getElementById('jhub-sort-rows');
            var tpl   = document.getElementById('jhub-sort-tpl').content.cloneNode(true);
            var idx   = tbody.querySelectorAll('tr').length;
            tpl.querySelectorAll('[name]').forEach(function(el){
                el.name = el.name.replace('__IDX__', idx);
            });
            tbody.appendChild(tpl);
        });
    })();
    </script>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN TAB 4 — DISPLAY & LAYOUT
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_tab_display() {
    $d     = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );
    $props = jetx_hub_property_defs();
    $select_props = array_filter( $props, fn($p) => $p['type'] === 'select' && ! $p['internal'] );
    ?>
    <form method="post" style="max-width:760px;">
        <?php wp_nonce_field( 'jetx_save_display' ); ?>
        <input type="hidden" name="jetx_save_display" value="1">

        <!-- Layout -->
        <h2>Layout</h2>
        <p style="color:#555;margin-bottom:12px;">
            <strong>Table</strong>, <strong>Gallery</strong>, <strong>List</strong>, and <strong>Board</strong> are built from Notion data.
            Timeline, Calendar, Chart, and Map are not available — Notion's API returns raw data only; those views are Notion's own frontend rendering and cannot be extracted.
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th>Layout type</th>
                <td>
                    <?php foreach ( [ 'table' => '📋 Table', 'gallery' => '🖼 Gallery', 'list' => '📄 List', 'board' => '📌 Board' ] as $val => $lbl ) : ?>
                    <label style="margin-right:18px;">
                        <input type="radio" name="layout" value="<?php echo esc_attr( $val ); ?>"
                               <?php checked( $d['layout'], $val ); ?>> <?php echo esc_html( $lbl ); ?>
                    </label>
                    <?php endforeach; ?>
                    <p class="description">Board groups entries into kanban-style columns by a select property you choose below.</p>
                </td>
            </tr>
            <tr id="jhub-board-row" style="<?php echo $d['layout'] !== 'board' ? 'display:none;' : ''; ?>">
                <th><label for="jhub_group">Board — group by</label></th>
                <td>
                    <select id="jhub_group" name="board_group_by">
                        <?php foreach ( $select_props as $key => $def ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $d['board_group_by'], $key ); ?>>
                            <?php echo esc_html( $def['label'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Theme</th>
                <td>
                    <label style="margin-right:18px;"><input type="radio" name="theme" value="dark"  <?php checked( $d['theme'], 'dark'  ); ?>> 🌙 Dark</label>
                    <label>                             <input type="radio" name="theme" value="light" <?php checked( $d['theme'], 'light' ); ?>> ☀️ Light</label>
                </td>
            </tr>
        </table>

        <!-- Notion Colors -->
        <h2 style="margin-top:28px;">Notion Colors</h2>
        <p style="color:#555;">The Notion API returns the color for every select and multi-select tag. When enabled, tags are rendered with their exact Notion colors. When disabled, a uniform neutral style is used.</p>

        <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
            <label style="font-weight:600;">
                <input type="checkbox" name="use_notion_colors" value="1" <?php checked( $d['use_notion_colors'] ); ?>>
                Use Notion color-coded tag styling
            </label>
            <p class="description" style="margin-top:6px;">
                Replicates the exact colors from your Notion database for Category, Status, Pricing, Traction, Platform, Capability Tags, and Era badges.
            </p>
            <!-- Color preview -->
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">
                <?php foreach ( jetx_hub_notion_colors() as $color => $vals ) : ?>
                <span style="background:<?php echo esc_attr( $vals['dark']['bg'] ); ?>;color:<?php echo esc_attr( $vals['dark']['text'] ); ?>;padding:3px 10px;border-radius:5px;font-size:12px;font-weight:500;">
                    <?php echo esc_html( ucfirst( $color ) ); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <p class="description" style="margin-top:6px;">Preview shown in dark theme. Light theme uses lighter backgrounds.</p>
        </div>

        <!-- Visibility toggles -->
        <h2 style="margin-top:28px;">Visible Elements</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th>Search bar</th>
                <td><label><input type="checkbox" name="show_search"  value="1" <?php checked( $d['show_search']  ); ?>> Show live search input above the table</label></td>
            </tr>
            <tr>
                <th>Filter dropdowns</th>
                <td><label><input type="checkbox" name="show_filters" value="1" <?php checked( $d['show_filters'] ); ?>> Show Category / Status / Pricing / Traction dropdowns</label></td>
            </tr>
            <tr>
                <th>Summary text</th>
                <td><label><input type="checkbox" name="show_summary" value="1" <?php checked( $d['show_summary'] ); ?>> Show summary snippet below tool name</label></td>
            </tr>
            <tr>
                <th>Capability tags</th>
                <td><label><input type="checkbox" name="show_tags"    value="1" <?php checked( $d['show_tags']    ); ?>> Show capability tag chips below tool name</label></td>
            </tr>
            <tr>
                <th>GitHub link</th>
                <td><label><input type="checkbox" name="show_github"  value="1" <?php checked( $d['show_github']  ); ?>> Show GitHub repo link below tool name (when available)</label></td>
            </tr>
            <tr>
                <th>Footer</th>
                <td><label><input type="checkbox" name="show_footer"  value="1" <?php checked( $d['show_footer']  ); ?>> Show "Powered by Notion / JetX Media" footer</label></td>
            </tr>
            <tr>
                <th><label for="jhub_limit">Row limit</label></th>
                <td>
                    <input type="number" id="jhub_limit" name="items_limit"
                           value="<?php echo esc_attr( $d['items_limit'] ); ?>" min="0" max="2000" class="small-text">
                    <span> entries (0 = no limit)</span>
                    <p class="description">Applied after filters. Useful for showing "Top 10" or limiting gallery cards.</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Display Settings' ); ?>
    </form>

    <script>
    document.querySelectorAll('input[name="layout"]').forEach(function(r){
        r.addEventListener('change', function(){
            document.getElementById('jhub-board-row').style.display =
                this.value === 'board' ? '' : 'none';
        });
    });
    </script>
    <?php
}


// ─────────────────────────────────────────────────────────────────────────────
// API LAYER
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_fetch_data() {
    $fresh = get_transient( JETX_HUB_CACHE_KEY );
    if ( false !== $fresh ) return $fresh;

    if ( get_transient( JETX_HUB_LOCK_KEY ) ) {
        $stale = get_option( JETX_HUB_STALE_KEY, null );
        return ! empty( $stale ) ? $stale : [ 'error' => 'refreshing' ];
    }

    set_transient( JETX_HUB_LOCK_KEY, 1, 60 );
    $result = jetx_hub_call_api();
    delete_transient( JETX_HUB_LOCK_KEY );

    if ( isset( $result['error'] ) ) {
        $stale = get_option( JETX_HUB_STALE_KEY, null );
        return ! empty( $stale ) ? $stale : $result;
    }

    $m = max( 5, (int) get_option( 'jetx_hub_cache_minutes', 60 ) );
    set_transient( JETX_HUB_CACHE_KEY, $result, $m * 60 );
    update_option( JETX_HUB_STALE_KEY, $result, false );
    return $result;
}

function jetx_hub_call_api() {
    $token = get_option( 'jetx_hub_token', '' );
    $db_id = get_option( 'jetx_hub_db_id', JETX_HUB_DB_ID );
    if ( empty( $token ) ) return [ 'error' => 'no_token' ];

    // Build sorts from admin settings
    $saved_sorts  = get_option( 'jetx_hub_sorts', jetx_hub_default_sorts() );
    $props        = jetx_hub_property_defs();
    $api_sorts    = [];
    foreach ( $saved_sorts as $s ) {
        $notion_prop = $props[ $s['property'] ]['notion'] ?? null;
        if ( $notion_prop ) {
            $api_sorts[] = [ 'property' => $notion_prop, 'direction' => $s['direction'] ];
        }
    }
    if ( empty( $api_sorts ) ) $api_sorts = [ [ 'property' => 'Date Released', 'direction' => 'descending' ] ];

    // Build filters from admin settings
    $saved_filters = get_option( 'jetx_hub_filters', [] );
    $api_filter    = null;
    if ( ! empty( $saved_filters ) ) {
        $conditions = [];
        foreach ( $saved_filters as $f ) {
            $def  = $props[ $f['property'] ] ?? null;
            if ( ! $def ) continue;
            $notion_prop = $def['notion'];
            $type        = $def['type'];
            $op          = $f['operator'];
            $val         = $f['value'] ?? '';
            $no_value_ops = [ 'is_empty', 'is_not_empty' ];
            if ( in_array( $op, $no_value_ops ) ) {
                $conditions[] = [ 'property' => $notion_prop, $type => [ $op => true ] ];
            } else {
                $conditions[] = [ 'property' => $notion_prop, $type => [ $op => $val ] ];
            }
        }
        if ( count( $conditions ) === 1 ) {
            $api_filter = $conditions[0];
        } elseif ( count( $conditions ) > 1 ) {
            $api_filter = [ 'and' => $conditions ];
        }
    }

    $all   = [];
    $more  = true;
    $cursor= null;
    $pages = 0;

    while ( $more && $pages < JETX_HUB_MAX_PAGES ) {
        $pages++;
        $body = [ 'page_size' => 100, 'sorts' => $api_sorts ];
        if ( $cursor     ) $body['start_cursor'] = $cursor;
        if ( $api_filter ) $body['filter']       = $api_filter;

        $resp = wp_remote_post(
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

        if ( is_wp_error( $resp ) ) {
            return empty( $all ) ? [ 'error' => $resp->get_error_message() ] : $all;
        }
        $code = wp_remote_retrieve_response_code( $resp );
        if ( $code !== 200 ) {
            $b   = json_decode( wp_remote_retrieve_body( $resp ), true );
            $msg = $b['message'] ?? 'Notion API HTTP ' . $code;
            return empty( $all ) ? [ 'error' => $msg, 'code' => $code ] : $all;
        }

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        foreach ( $data['results'] ?? [] as $page ) $all[] = jetx_hub_parse_page( $page );
        $more   = $data['has_more']    ?? false;
        $cursor = $data['next_cursor'] ?? null;
    }

    return $all;
}

/**
 * Parse a Notion page object. Captures color info for select/multi_select.
 */
function jetx_hub_parse_page( array $page ): array {
    $p = $page['properties'] ?? [];

    $text = static function ( $prop ) {
        if ( empty( $prop ) || ! isset( $prop['type'] ) ) return '';
        switch ( $prop['type'] ) {
            case 'title':        return implode( '', array_column( $prop['title']    ?? [], 'plain_text' ) );
            case 'rich_text':    return implode( '', array_column( $prop['rich_text'] ?? [], 'plain_text' ) );
            case 'url':          return $prop['url']            ?? '';
            case 'select':       return $prop['select']['name'] ?? '';
            case 'date':         return $prop['date']['start']  ?? '';
            case 'created_time': return $prop['created_time']   ?? '';
            default:             return '';
        }
    };

    // Select with color
    $select = static function ( $prop ) {
        if ( empty( $prop ) || ( $prop['type'] ?? '' ) !== 'select' ) return [ 'name' => '', 'color' => 'default' ];
        return [ 'name' => $prop['select']['name'] ?? '', 'color' => $prop['select']['color'] ?? 'default' ];
    };

    // Multi-select with colors
    $multi = static function ( $prop ) {
        if ( empty( $prop ) || ( $prop['type'] ?? '' ) !== 'multi_select' ) return [];
        return array_map( fn( $i ) => [ 'name' => $i['name'] ?? '', 'color' => $i['color'] ?? 'default' ], $prop['multi_select'] ?? [] );
    };

    return [
        'id'               => sanitize_text_field( $page['id'] ?? '' ),
        // Plain string values (for search/filter)
        'name'             => $text( $p['Name']               ?? [] ),
        'publisher'        => $text( $p['Publisher / Company'] ?? [] ),
        'summary'          => $text( $p['Summary']            ?? [] ),
        'why_it_matters'   => $text( $p['Why It Matters']     ?? [] ),
        'official_url'     => $text( $p['Official URL']       ?? [] ),
        'github_repo'      => $text( $p['GitHub Repo']        ?? [] ),
        'date_released'    => $text( $p['Date Released']      ?? [] ),
        // Select with color
        'category'         => $select( $p['Category']         ?? [] ),
        'sub_category'     => $select( $p['Sub-category']     ?? [] ),
        'status'           => $select( $p['Status']           ?? [] ),
        'traction'         => $select( $p['Traction']         ?? [] ),
        'pricing'          => $select( $p['Pricing']          ?? [] ),
        'era'              => $select( $p['Era']              ?? [] ),
        'blog_status'      => $select( $p['Blog Status']      ?? [] ),
        'jetx_relevance'   => $select( $p['JetX Relevance']   ?? [] ),
        // Multi-select with colors
        'platform'         => $multi( $p['Platform']          ?? [] ),
        'capability_tags'  => $multi( $p['Capability Tags']   ?? [] ),
        'jetx_use_case'    => $multi( $p['JetX Use Case']     ?? [] ),
    ];
}


// ─────────────────────────────────────────────────────────────────────────────
// SHORTCODE + RENDERING
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode( 'jetx_ai_hub', 'jetx_hub_shortcode' );

function jetx_hub_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'limit'         => 0,
        'category'      => '',
        'show_internal' => 'false',
    ], $atts, 'jetx_ai_hub' );

    $show_internal = filter_var( $atts['show_internal'], FILTER_VALIDATE_BOOLEAN )
                     && current_user_can( 'manage_options' );

    $data = jetx_hub_fetch_data();

    if ( isset( $data['error'] ) ) {
        if ( $data['error'] === 'no_token' && current_user_can( 'manage_options' ) ) {
            return '<p style="color:#f87171;font-style:italic;">⚠️ JetX AI Hub: No token. <a href="' . esc_url( admin_url( 'options-general.php?page=jetx-ai-hub' ) ) . '">Configure →</a></p>';
        }
        if ( $data['error'] === 'refreshing' ) return '<p style="color:#94a3b8;">🔄 AI Hub loading — refresh in a moment.</p>';
        if ( current_user_can( 'manage_options' ) ) return '<p style="color:#f87171;">⚠️ ' . esc_html( $data['error'] ) . '</p>';
        return '';
    }

    if ( empty( $data ) ) return '<p>No entries found.</p>';

    // Load settings
    $disp = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );
    $cols_cfg = get_option( 'jetx_hub_columns', jetx_hub_default_columns() );
    usort( $cols_cfg, fn( $a, $b ) => $a['order'] <=> $b['order'] );

    $items = $data;

    // Shortcode category override
    if ( ! empty( $atts['category'] ) ) {
        $fc = strtolower( trim( $atts['category'] ) );
        $items = array_values( array_filter( $items, fn( $i ) => strtolower( $i['category']['name'] ?? '' ) === $fc ) );
    }

    // Limit
    $limit = (int) $atts['limit'] > 0 ? (int) $atts['limit'] : (int) $disp['items_limit'];
    if ( $limit > 0 ) $items = array_slice( $items, 0, $limit );

    // Unique categories for filter dropdown
    $categories = array_unique( array_filter( array_map( fn( $i ) => $i['category']['name'] ?? '', $items ) ) );
    sort( $categories );

    $layout = $disp['layout'] ?? 'table';
    $theme  = $disp['theme']  ?? 'dark';
    $colors = $disp['use_notion_colors'] ?? true;

    ob_start();
    $color_css = jetx_hub_color_css( $theme );
    echo '<style>' . $color_css . jetx_hub_base_css( $theme ) . '</style>';

    echo '<div class="jetx-hub-wrap jhub-theme-' . esc_attr( $theme ) . '" id="jetx-ai-hub" data-layout="' . esc_attr( $layout ) . '">';

    // Controls
    if ( $disp['show_search'] || $disp['show_filters'] ) {
        echo '<div class="jhub-controls">';
        if ( $disp['show_search'] ) {
            echo '<div class="jhub-search-wrap"><svg class="jhub-si" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M9 3a6 6 0 1 0 3.73 10.74l3.27 3.27 1.06-1.06-3.27-3.27A6 6 0 0 0 9 3zm-4 6a4 4 0 1 1 8 0 4 4 0 0 1-8 0z" clip-rule="evenodd"/></svg><input type="text" id="jhub-search" class="jhub-search" placeholder="Search…" aria-label="Search"></div>';
        }
        if ( $disp['show_filters'] ) {
            echo '<div class="jhub-filters">';
            echo '<select id="jhub-cat" class="jhub-select" aria-label="Category"><option value="">All Categories</option>';
            foreach ( $categories as $c ) echo '<option value="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</option>';
            echo '</select>';
            $quick = [
                'jhub-status'  => [ 'All Status',  [ '🟢 Active','🟡 Beta','🔵 Experimental','⚪ Announced','⚫ Acquired','🔴 Deprecated' ] ],
                'jhub-pricing' => [ 'All Pricing', [ 'Free','Freemium','Open Source','Paid','Enterprise','API Only' ] ],
                'jhub-traction'=> [ 'All Traction',[ '🚀 Hypergrowth','📈 Growing','➡️ Stable','⚡ Emerging','📉 Declining' ] ],
            ];
            foreach ( $quick as $id => $q ) {
                echo '<select id="' . esc_attr( $id ) . '" class="jhub-select" aria-label="' . esc_attr( $q[0] ) . '"><option value="">' . esc_html( $q[0] ) . '</option>';
                foreach ( $q[1] as $o ) echo '<option>' . esc_html( $o ) . '</option>';
                echo '</select>';
            }
            echo '</div>';
        }
        echo '<span id="jhub-count" class="jhub-count" aria-live="polite"></span>';
        echo '</div>'; // .jhub-controls
    }

    // Render selected layout
    switch ( $layout ) {
        case 'gallery': jetx_hub_render_gallery( $items, $disp, $cols_cfg, $colors ); break;
        case 'list':    jetx_hub_render_list(    $items, $disp, $cols_cfg, $colors ); break;
        case 'board':   jetx_hub_render_board(   $items, $disp, $cols_cfg, $colors ); break;
        default:        jetx_hub_render_table(   $items, $disp, $cols_cfg, $colors, $show_internal ); break;
    }

    echo '<div class="jhub-no-results" id="jhub-no-results" style="display:none;" role="status">No results. <button class="jhub-reset-btn" id="jhub-reset" type="button">Clear filters</button></div>';

    if ( $disp['show_footer'] ) {
        echo '<div class="jhub-footer">Data synced from <a href="' . esc_url( JETX_HUB_NOTION_URL ) . '" target="_blank" rel="noopener">Notion</a> &middot; Curated by <a href="https://www.jetxmedia.com" target="_blank" rel="noopener">JetX Media</a></div>';
    }

    echo '</div>'; // .jetx-hub-wrap
    echo '<script>' . jetx_hub_frontend_js() . '</script>';

    return ob_get_clean();
}

// ── Badge helper ──
function jetx_hub_badge( $item, $use_notion_colors, $theme = 'dark' ) {
    if ( empty( $item['name'] ) ) return '';
    $color   = $item['color'] ?? 'default';
    $colors  = jetx_hub_notion_colors();
    $palette = $colors[ $color ] ?? $colors['default'];
    $style   = '';
    if ( $use_notion_colors ) {
        $bg    = $palette[ $theme ]['bg'];
        $fg    = $palette[ $theme ]['text'];
        $style = ' style="background:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg ) . ';"';
    }
    return '<span class="jhub-badge jhub-color-' . esc_attr( $color ) . '"' . $style . '>' . esc_html( $item['name'] ) . '</span>';
}

// ── Item name cell HTML (shared across layouts) ──
function jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme ) {
    $out = '<div class="jhub-name">';
    if ( ! empty( $item['official_url'] ) ) {
        $out .= '<a href="' . esc_url( $item['official_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $item['name'] ) . ' <span class="jhub-ext" aria-hidden="true">↗</span></a>';
    } else {
        $out .= '<span>' . esc_html( $item['name'] ) . '</span>';
    }
    $out .= '</div>';
    if ( ! empty( $item['publisher'] ) ) {
        $out .= '<div class="jhub-pub">' . esc_html( $item['publisher'] ) . '</div>';
    }
    if ( $disp['show_summary'] && ! empty( $item['summary'] ) ) {
        $out .= '<div class="jhub-sum">' . esc_html( mb_strimwidth( $item['summary'], 0, 140, '…' ) ) . '</div>';
    }
    if ( $disp['show_tags'] && ! empty( $item['capability_tags'] ) ) {
        $out .= '<div class="jhub-tags">';
        foreach ( $item['capability_tags'] as $t ) $out .= jetx_hub_badge( $t, $use_notion_colors, $theme );
        $out .= '</div>';
    }
    if ( $disp['show_github'] && ! empty( $item['github_repo'] ) ) {
        $out .= '<a href="' . esc_url( $item['github_repo'] ) . '" class="jhub-gh" target="_blank" rel="noopener noreferrer">⭐ GitHub</a>';
    }
    return $out;
}

// ── Search data attribute ──
function jetx_hub_row_attrs( $item ) {
    $cat = $item['category']['name']  ?? '';
    $st  = $item['status']['name']    ?? '';
    $pr  = $item['pricing']['name']   ?? '';
    $tr  = $item['traction']['name']  ?? '';
    $blob= strtolower( implode( ' ', array_filter([
        $item['name'], $cat, $item['sub_category']['name'] ?? '',
        $item['publisher'], $item['summary'],
        implode( ' ', array_column( $item['capability_tags'], 'name' ) ),
        implode( ' ', array_column( $item['platform'], 'name' ) ),
    ])));
    return 'class="jhub-row" data-search="' . esc_attr( $blob ) . '" data-category="' . esc_attr( $cat ) . '" data-status="' . esc_attr( $st ) . '" data-pricing="' . esc_attr( $pr ) . '" data-traction="' . esc_attr( $tr ) . '"';
}


// ─────────────────────────────────────────────────────────────────────────────
// LAYOUT RENDERERS
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_render_table( $items, $disp, $cols_cfg, $use_notion_colors, $show_internal ) {
    $theme   = $disp['theme'] ?? 'dark';
    $visible = array_filter( $cols_cfg, fn( $c ) => $c['visible'] );

    echo '<div class="jhub-table-wrap" role="region" aria-label="AI Intelligence Hub"><table class="jhub-table" id="jhub-table"><thead><tr>';
    foreach ( $visible as $col ) {
        $props = jetx_hub_property_defs();
        $def   = $props[ $col['key'] ] ?? null;
        if ( $def && $def['internal'] && ! $show_internal ) continue;
        echo '<th scope="col" class="jhub-col-' . esc_attr( $col['key'] ) . '">' . esc_html( $col['label'] ) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ( $items as $item ) {
        echo '<tr ' . jetx_hub_row_attrs( $item ) . '>';
        foreach ( $visible as $col ) {
            $key  = $col['key'];
            $def  = jetx_hub_property_defs()[ $key ] ?? null;
            if ( $def && $def['internal'] && ! $show_internal ) continue;
            echo '<td class="jhub-td-' . esc_attr( $key ) . '">';
            switch ( $key ) {
                case 'name':
                    echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme );
                    break;
                case 'platform': case 'capability_tags': case 'jetx_use_case':
                    foreach ( $item[ $key ] ?? [] as $v ) echo jetx_hub_badge( $v, $use_notion_colors, $theme );
                    break;
                case 'summary': case 'why_it_matters': case 'publisher':
                    echo esc_html( mb_strimwidth( $item[ $key ] ?? '', 0, 100, '…' ) );
                    break;
                case 'official_url': case 'github_repo':
                    if ( ! empty( $item[ $key ] ) ) echo '<a href="' . esc_url( $item[ $key ] ) . '" target="_blank" rel="noopener">↗ Link</a>';
                    break;
                default:
                    $val = $item[ $key ] ?? '';
                    if ( is_array( $val ) && isset( $val['name'] ) ) echo jetx_hub_badge( $val, $use_notion_colors, $theme );
                    else echo esc_html( is_array( $val ) ? implode( ', ', array_column( $val, 'name' ) ) : $val );
            }
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function jetx_hub_render_gallery( $items, $disp, $cols_cfg, $use_notion_colors ) {
    $theme = $disp['theme'] ?? 'dark';
    echo '<div class="jhub-gallery" id="jhub-table">';
    foreach ( $items as $item ) {
        $cat = $item['category'] ?? [];
        $st  = $item['status']   ?? [];
        $pr  = $item['pricing']  ?? [];
        echo '<div ' . jetx_hub_row_attrs( $item ) . ' style="all:revert;display:flex;flex-direction:column;gap:6px;" class="jhub-row jhub-card">';
        echo '<div class="jhub-card-head">';
        echo jetx_hub_badge( $cat, $use_notion_colors, $theme );
        echo jetx_hub_badge( $st,  $use_notion_colors, $theme );
        echo '</div>';
        echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme );
        echo '<div class="jhub-card-foot">';
        echo jetx_hub_badge( $pr, $use_notion_colors, $theme );
        if ( ! empty( $item['traction']['name'] ) ) echo '<span class="jhub-era" style="font-size:11px;">' . esc_html( $item['traction']['name'] ) . '</span>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

function jetx_hub_render_list( $items, $disp, $cols_cfg, $use_notion_colors ) {
    $theme = $disp['theme'] ?? 'dark';
    echo '<div class="jhub-list" id="jhub-table">';
    foreach ( $items as $item ) {
        $cat = $item['category'] ?? [];
        $st  = $item['status']   ?? [];
        echo '<div ' . jetx_hub_row_attrs( $item ) . ' class="jhub-row jhub-list-item">';
        echo '<div class="jhub-list-main">' . jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme ) . '</div>';
        echo '<div class="jhub-list-meta">';
        echo jetx_hub_badge( $cat, $use_notion_colors, $theme );
        echo jetx_hub_badge( $st,  $use_notion_colors, $theme );
        if ( ! empty( $item['pricing']['name'] ) ) echo jetx_hub_badge( $item['pricing'], $use_notion_colors, $theme );
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

function jetx_hub_render_board( $items, $disp, $cols_cfg, $use_notion_colors ) {
    $theme    = $disp['theme']           ?? 'dark';
    $group_by = $disp['board_group_by']  ?? 'category';
    // Group items
    $groups = [];
    foreach ( $items as $item ) {
        $val = is_array( $item[ $group_by ] ?? '' ) ? ( $item[ $group_by ]['name'] ?? '—' ) : ( $item[ $group_by ] ?? '—' );
        if ( empty( $val ) ) $val = '—';
        $groups[ $val ][] = $item;
    }
    ksort( $groups );
    echo '<div class="jhub-board" id="jhub-table">';
    foreach ( $groups as $group_name => $group_items ) {
        $color_item = ( $group_items[0][ $group_by ] ?? [] );
        echo '<div class="jhub-board-col">';
        echo '<div class="jhub-board-head">' . jetx_hub_badge( is_array( $color_item ) ? $color_item : [ 'name' => $group_name, 'color' => 'default' ], $use_notion_colors, $theme ) . ' <span class="jhub-board-count">(' . count( $group_items ) . ')</span></div>';
        foreach ( $group_items as $item ) {
            echo '<div ' . jetx_hub_row_attrs( $item ) . ' class="jhub-row jhub-board-card">';
            echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme );
            echo '<div style="margin-top:6px;">';
            echo jetx_hub_badge( $item['status']  ?? [], $use_notion_colors, $theme );
            echo jetx_hub_badge( $item['pricing'] ?? [], $use_notion_colors, $theme );
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}


// ─────────────────────────────────────────────────────────────────────────────
// CSS
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_color_css( $theme ) {
    // Pre-generate badge color classes for all Notion colors
    $map = jetx_hub_notion_colors();
    $css = '';
    foreach ( $map as $color => $vals ) {
        $bg   = $vals[ $theme ]['bg'];
        $text = $vals[ $theme ]['text'];
        $css .= '.jhub-color-' . $color . '{background:' . $bg . ';color:' . $text . '}';
    }
    return $css;
}

function jetx_hub_base_css( $theme ) {
    $is_dark  = $theme === 'dark';
    $bg       = $is_dark ? '#0f172a' : '#ffffff';
    $bg2      = $is_dark ? '#1e293b' : '#f8fafc';
    $border   = $is_dark ? '#1e293b' : '#e2e8f0';
    $border2  = $is_dark ? '#334155' : '#cbd5e1';
    $text     = $is_dark ? '#e2e8f0' : '#1e293b';
    $text2    = $is_dark ? '#94a3b8' : '#475569';
    $text3    = $is_dark ? '#64748b' : '#94a3b8';
    $link     = $is_dark ? '#818cf8' : '#4f46e5';
    $link_h   = $is_dark ? '#a5b4fc' : '#6366f1';
    $row_h    = $is_dark ? '#162032' : '#f1f5f9';
    $inp_bg   = $is_dark ? '#1e293b' : '#f1f5f9';

    return "
.jetx-hub-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:{$bg};border-radius:12px;padding:24px;margin:24px 0;color:{$text};box-sizing:border-box}
.jetx-hub-wrap *{box-sizing:border-box}
/* Controls */
.jhub-controls{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:16px}
.jhub-search-wrap{position:relative;flex:1;min-width:200px}
.jhub-si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:{$text3};pointer-events:none}
.jhub-search{width:100%;padding:9px 14px 9px 36px;background:{$inp_bg};border:1px solid {$border2};border-radius:8px;color:{$text};font-size:14px;outline:none;transition:border-color .2s}
.jhub-search:focus{border-color:{$link}}.jhub-search::placeholder{color:{$text3}}
.jhub-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.jhub-select{padding:8px 12px;background:{$inp_bg};border:1px solid {$border2};border-radius:8px;color:{$text};font-size:12px;cursor:pointer;outline:none;transition:border-color .2s}
.jhub-select:focus{border-color:{$link}}.jhub-count{font-size:11px;color:{$text3};white-space:nowrap}
/* Badge */
.jhub-badge{display:inline-block;padding:3px 8px;border-radius:5px;font-size:11px;font-weight:500;line-height:1.4;margin:2px 2px 2px 0;white-space:nowrap;background:{$bg2};color:{$text2}}
/* TABLE */
.jhub-table-wrap{overflow-x:auto;border-radius:8px;border:1px solid {$border}}
.jhub-table{width:100%;border-collapse:collapse;font-size:13px;min-width:580px}
.jhub-table thead th{background:{$bg2};color:{$text3};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:11px 14px;text-align:left;border-bottom:1px solid {$border2};white-space:nowrap}
.jhub-table tbody tr{border-bottom:1px solid {$border};transition:background .12s}
.jhub-table tbody tr:last-child{border-bottom:none}
.jhub-table tbody tr:hover{background:{$row_h}}
.jhub-table td{padding:12px 14px;vertical-align:top;color:{$text2}}
/* Name cell */
.jhub-td-name,.jhub-list-main{min-width:180px;max-width:320px}
.jhub-name a{color:{$link};font-weight:600;font-size:13.5px;text-decoration:none;transition:color .15s}
.jhub-name a:hover{color:{$link_h}}.jhub-name span{color:{$text};font-weight:600;font-size:13.5px}
.jhub-ext{font-size:10px;opacity:.5}.jhub-pub{font-size:11px;color:{$text3};margin-top:2px}
.jhub-sum{font-size:11.5px;color:{$text3};margin-top:5px;line-height:1.5}
.jhub-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.jhub-gh{display:inline-flex;align-items:center;gap:4px;margin-top:5px;font-size:11px;color:{$text3};text-decoration:none;transition:color .15s}
.jhub-gh:hover{color:{$text2}}
/* GALLERY */
.jhub-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.jhub-card{background:{$bg2};border:1px solid {$border2};border-radius:10px;padding:16px;transition:border-color .15s}
.jhub-card:hover{border-color:{$link}}.jhub-card-head{margin-bottom:8px}.jhub-card-foot{margin-top:8px;padding-top:8px;border-top:1px solid {$border}}
/* LIST */
.jhub-list{display:flex;flex-direction:column;gap:1px;border:1px solid {$border};border-radius:8px;overflow:hidden}
.jhub-list-item{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:12px 16px;background:{$bg};border-bottom:1px solid {$border};transition:background .12s}
.jhub-list-item:last-child{border-bottom:none}.jhub-list-item:hover{background:{$row_h}}
.jhub-list-meta{display:flex;flex-wrap:wrap;gap:4px;align-items:flex-start;min-width:160px;justify-content:flex-end}
/* BOARD */
.jhub-board{display:flex;gap:16px;overflow-x:auto;padding-bottom:8px;align-items:flex-start}
.jhub-board-col{flex:0 0 240px;background:{$bg2};border:1px solid {$border2};border-radius:10px;padding:12px}
.jhub-board-head{margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid {$border2};font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px}
.jhub-board-count{color:{$text3};font-size:11px;font-weight:400}
.jhub-board-card{background:{$bg};border:1px solid {$border};border-radius:8px;padding:12px;margin-bottom:8px;transition:border-color .15s}
.jhub-board-card:hover{border-color:{$link}}
/* Hidden rows */
.jhub-table tbody tr.jhub-hidden,.jhub-gallery .jhub-hidden,.jhub-list .jhub-hidden,.jhub-board-card.jhub-hidden{display:none}
/* No results / footer */
.jhub-no-results{text-align:center;padding:32px;color:{$text3};font-size:13px}
.jhub-reset-btn{background:none;border:1px solid {$border2};color:{$link};padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;margin-left:8px;transition:all .15s}
.jhub-reset-btn:hover{background:{$bg2};color:{$link_h}}
.jhub-footer{margin-top:14px;text-align:center;font-size:11px;color:{$border2}}
.jhub-footer a{color:{$link};text-decoration:none}.jhub-footer a:hover{color:{$link_h}}
.jhub-era{font-size:12px;color:{$text3};white-space:nowrap}
/* Responsive */
@media(max-width:768px){.jetx-hub-wrap{padding:16px}.jhub-table .jhub-col-traction,.jhub-table .jhub-td-traction,.jhub-table .jhub-col-platform,.jhub-table .jhub-td-platform,.jhub-table .jhub-col-era,.jhub-table .jhub-td-era{display:none}}
@media(max-width:500px){.jhub-table .jhub-col-pricing,.jhub-table .jhub-td-pricing{display:none}.jhub-gallery{grid-template-columns:1fr}}
";
}


// ─────────────────────────────────────────────────────────────────────────────
// FRONTEND JS
// ─────────────────────────────────────────────────────────────────────────────

function jetx_hub_frontend_js() {
    return <<<'JS'
(function(){
    'use strict';
    var wrap   = document.getElementById('jetx-ai-hub');
    if (!wrap) return;
    var layout = wrap.dataset.layout || 'table';
    var search = document.getElementById('jhub-search');
    var selCat = document.getElementById('jhub-cat');
    var selSt  = document.getElementById('jhub-status');
    var selPr  = document.getElementById('jhub-pricing');
    var selTr  = document.getElementById('jhub-traction');
    var countEl= document.getElementById('jhub-count');
    var noRes  = document.getElementById('jhub-no-results');
    var reset  = document.getElementById('jhub-reset');

    function getRows(){
        if (layout==='table') return document.querySelectorAll('#jhub-table .jhub-row');
        if (layout==='board') return document.querySelectorAll('.jhub-board-card.jhub-row');
        return document.querySelectorAll('#jhub-table .jhub-row');
    }

    function applyFilters(){
        var q   = search  ? search.value.toLowerCase().trim() : '';
        var cat = selCat  ? selCat.value  : '';
        var st  = selSt   ? selSt.value   : '';
        var pr  = selPr   ? selPr.value   : '';
        var tr  = selTr   ? selTr.value   : '';
        var rows= getRows();
        var vis = 0;
        rows.forEach(function(row){
            var ok = (!q   || row.dataset.search.indexOf(q)  !== -1)
                  && (!cat || row.dataset.category === cat)
                  && (!st  || row.dataset.status   === st)
                  && (!pr  || row.dataset.pricing  === pr)
                  && (!tr  || row.dataset.traction === tr);
            row.classList.toggle('jhub-hidden', !ok);
            if(ok) vis++;
        });
        if(countEl) countEl.textContent = vis + ' of ' + rows.length;
        if(noRes)   noRes.style.display = vis===0 ? 'block' : 'none';
        // Hide empty board columns
        if(layout==='board'){
            document.querySelectorAll('.jhub-board-col').forEach(function(col){
                var visible = col.querySelectorAll('.jhub-board-card:not(.jhub-hidden)').length;
                col.style.display = visible===0 ? 'none' : '';
            });
        }
    }

    function clearFilters(){
        if(search) search.value='';
        if(selCat) selCat.value='';
        if(selSt)  selSt.value='';
        if(selPr)  selPr.value='';
        if(selTr)  selTr.value='';
        applyFilters();
    }

    [search,selCat,selSt,selPr,selTr].forEach(function(el){
        if(el) el.addEventListener(el.tagName==='INPUT'?'input':'change', applyFilters);
    });
    if(reset) reset.addEventListener('click', clearFilters);
    applyFilters();
})();
JS;
}