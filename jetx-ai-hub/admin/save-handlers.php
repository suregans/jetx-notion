<?php
/**
 * admin/save-handlers.php
 *
 * Handles all POST form submissions from the JetX AI Hub settings page.
 * Runs on admin_init (before any output), validates nonces, sanitizes input,
 * saves to wp_options, then redirects back to the correct tab.
 *
 * Each form uses a dedicated hidden input + nonce pair so handlers are isolated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'jetx_hub_handle_saves' );

function jetx_hub_handle_saves(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;

	jetx_hub_save_connection();
	jetx_hub_save_refresh_now();
	jetx_hub_save_flush_cache();
	jetx_hub_save_schema();
	jetx_hub_save_columns();
	jetx_hub_save_filters();
	jetx_hub_save_sorts();
	jetx_hub_save_display();
}

// ── Tab 1: Connection ─────────────────────────────────────────────────────────

function jetx_hub_save_connection(): void {
	if ( ! isset( $_POST['jetx_save_connection'] ) ) return;
	check_admin_referer( 'jetx_save_connection' );

	update_option( 'jetx_hub_token',         sanitize_text_field( $_POST['jetx_hub_token']         ?? '' ) );
	update_option( 'jetx_hub_db_id',         sanitize_text_field( $_POST['jetx_hub_db_id']         ?? JETX_HUB_DEFAULT_DB_ID ) );
	update_option( 'jetx_hub_cache_minutes', absint(              $_POST['jetx_hub_cache_minutes']  ?? JETX_HUB_DEFAULT_CACHE_MINS ) );

	jetx_hub_schedule_cron();

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&saved=1' ) );
	exit;
}

function jetx_hub_save_refresh_now(): void {
	if ( ! isset( $_POST['jetx_refresh_now'] ) ) return;
	check_admin_referer( 'jetx_refresh_now' );

	jetx_hub_background_refresh();

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&refreshed=1' ) );
	exit;
}

function jetx_hub_save_flush_cache(): void {
	if ( ! isset( $_POST['jetx_flush_cache'] ) ) return;
	check_admin_referer( 'jetx_flush_cache' );

	delete_transient( JETX_HUB_CACHE_KEY );
	delete_transient( JETX_HUB_LOCK_KEY );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&flushed=1' ) );
	exit;
}

// ── Tab 2: Schema ─────────────────────────────────────────────────────────────

/**
 * Save admin-configured Notion field name / type overrides.
 * Auto-flushes the transient cache because field-name changes affect the API
 * query structure and the shape of parsed row data.
 */
function jetx_hub_save_schema(): void {
	if ( ! isset( $_POST['jetx_save_schema'] ) ) return;
	check_admin_referer( 'jetx_save_schema', 'jetx_save_schema_nonce' );

	$raw          = (array) ( $_POST['schema'] ?? [] );
	$defaults     = jetx_hub_property_defs(); // uses existing (pre-save) values
	$valid_types  = [ 'title', 'rich_text', 'select', 'multi_select', 'url', 'date' ];
	$schema       = [];

	foreach ( $defaults as $key => $def ) {
		$entry = $raw[ $key ] ?? [];

		// Sanitise and fall back to existing value if blank.
		$notion = sanitize_text_field( $entry['notion'] ?? '' );
		if ( $notion === '' ) {
			$notion = $def['notion']; // don't allow wiping a field name to empty
		}

		$type = $entry['type'] ?? '';
		if ( ! in_array( $type, $valid_types, true ) ) {
			$type = $def['type'];
		}

		$schema[ $key ] = [
			'notion'   => $notion,
			'type'     => $type,
			'internal' => ! empty( $entry['internal'] ),
		];
	}

	update_option( 'jetx_hub_schema', $schema );

	// Schema changes invalidate all cached data — flush immediately.
	delete_transient( JETX_HUB_CACHE_KEY );
	delete_transient( JETX_HUB_STALE_KEY );
	delete_transient( JETX_HUB_LOCK_KEY );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=schema&saved=1' ) );
	exit;
}

// ── Tab 3: Columns ────────────────────────────────────────────────────────────

function jetx_hub_save_columns(): void {
	if ( ! isset( $_POST['jetx_save_columns'] ) ) return;
	check_admin_referer( 'jetx_save_columns' );

	$order   = array_map( 'sanitize_text_field', (array) ( $_POST['col_order']   ?? [] ) );
	$visible = array_map( 'sanitize_text_field', (array) ( $_POST['col_visible'] ?? [] ) );
	$labels  = array_map( 'sanitize_text_field', (array) ( $_POST['col_label']   ?? [] ) );

	// Build a lookup from defaults so we can preserve any keys not in the POST.
	$defaults = jetx_hub_default_columns();
	$indexed  = [];
	foreach ( $defaults as $d ) {
		$indexed[ $d['key'] ] = $d;
	}

	$cols = [];
	foreach ( $order as $position => $key ) {
		if ( ! isset( $indexed[ $key ] ) ) continue;
		$cols[] = [
			'key'     => $key,
			'label'   => $labels[ $key ] ?? $indexed[ $key ]['label'],
			'visible' => in_array( $key, $visible, true ),
			'order'   => $position,
		];
	}

	update_option( 'jetx_hub_columns', $cols );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=columns&saved=1' ) );
	exit;
}

// ── Tab 3: Filters ────────────────────────────────────────────────────────────

function jetx_hub_save_filters(): void {
	if ( ! isset( $_POST['jetx_save_filters'] ) ) return;
	check_admin_referer( 'jetx_save_filters' );

	$raw     = (array) ( $_POST['filters'] ?? [] );
	$filters = [];

	foreach ( $raw as $rule ) {
		$prop = sanitize_text_field( $rule['property'] ?? '' );
		$op   = sanitize_text_field( $rule['operator']  ?? '' );
		$val  = sanitize_text_field( $rule['value']     ?? '' );
		if ( $prop && $op ) {
			$filters[] = [ 'property' => $prop, 'operator' => $op, 'value' => $val ];
		}
	}

	update_option( 'jetx_hub_filters', $filters );

	// Filter changes affect the Notion API query — must bust the cache.
	delete_transient( JETX_HUB_CACHE_KEY );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=filters&saved=1' ) );
	exit;
}

// ── Tab 3: Sort ───────────────────────────────────────────────────────────────

function jetx_hub_save_sorts(): void {
	if ( ! isset( $_POST['jetx_save_sorts'] ) ) return;
	check_admin_referer( 'jetx_save_sorts' );

	$raw   = (array) ( $_POST['sorts'] ?? [] );
	$sorts = [];

	$allowed_dirs = [ 'ascending', 'descending' ];

	foreach ( $raw as $rule ) {
		$prop = sanitize_text_field( $rule['property']  ?? '' );
		$dir  = sanitize_text_field( $rule['direction'] ?? 'descending' );
		if ( $prop ) {
			$sorts[] = [
				'property'  => $prop,
				'direction' => in_array( $dir,