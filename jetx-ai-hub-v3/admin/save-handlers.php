<?php
/**
 * admin/save-handlers.php
 *
 * All POST form submissions for the JetX AI Hub settings page.
 * Runs on admin_init (before any output), validates nonces, sanitizes input,
 * saves to wp_options, then redirects back to the correct tab.
 *
 * v4.0 additions:
 *   jetx_hub_detect_fields_handler() — triggers Notion API schema detection
 *   jetx_hub_save_fields()           — saves which fields are toggled on/off
 *   jetx_hub_save_settings()         — saves branding + performance + graph config
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'jetx_hub_handle_saves' );

function jetx_hub_handle_saves(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;

	// v4.0 handlers (new).
	jetx_hub_detect_fields_handler();
	jetx_hub_save_fields();
	jetx_hub_save_settings();

	// v3 handlers (retained).
	jetx_hub_save_connection();
	jetx_hub_save_refresh_now();
	jetx_hub_save_flush_cache();
	jetx_hub_save_columns();
	jetx_hub_save_filters();
	jetx_hub_save_sorts();
	jetx_hub_save_display();
}

// ── Tab: Fields — Detect ─────────────────────────────────────────────────────

/**
 * Triggers Notion API schema detection when "Detect Fields" button is clicked.
 */
function jetx_hub_detect_fields_handler(): void {
	if ( ! isset( $_POST['jetx_detect_fields'] ) ) return;
	check_admin_referer( 'jetx_detect_fields' );

	$result = jetx_hub_detect_schema(); // from notion-schema.php

	if ( isset( $result['error'] ) ) {
		wp_safe_redirect( admin_url(
			'options-general.php?page=jetx-ai-hub&tab=fields&det_error=1&msg='
			. urlencode( $result['error'] )
		) );
		exit;
	}

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=fields&detected=1' ) );
	exit;
}

// ── Tab: Fields — Toggle active fields ───────────────────────────────────────

/**
 * Saves which detected fields the admin has toggled on/off.
 */
function jetx_hub_save_fields(): void {
	if ( ! isset( $_POST['jetx_save_fields'] ) ) return;
	check_admin_referer( 'jetx_save_fields' );

	$detected     = jetx_hub_get_detected_schema(); // from notion-schema.php
	$checked      = array_map( 'sanitize_text_field', array_keys( (array) ( $_POST['active_fields'] ?? [] ) ) );
	$active_flags = [];

	foreach ( $detected as $key => $def ) {
		// Title fields are always active.
		$active_flags[ $key ] = ( $def['type'] === 'title' ) || in_array( $key, $checked, true );
	}

	update_option( 'jetx_hub_active_fields', $active_flags, false );

	// Flush cache — active field changes affect what is parsed from each row.
	delete_transient( JETX_HUB_CACHE_KEY );
	delete_transient( JETX_HUB_STALE_KEY );
	delete_transient( JETX_HUB_LOCK_KEY );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=fields&saved=1' ) );
	exit;
}

// ── Tab: Settings ─────────────────────────────────────────────────────────────

/**
 * Saves branding, performance limits, and graph field configuration.
 */
function jetx_hub_save_settings(): void {
	if ( ! isset( $_POST['jetx_save_settings'] ) ) return;
	check_admin_referer( 'jetx_save_settings' );

	$settings = [
		'branding_name' => sanitize_text_field(  $_POST['branding_name'] ?? '' ),
		'branding_url'  => esc_url_raw(           $_POST['branding_url']  ?? '' ),
		'admin_title'   => sanitize_text_field(  $_POST['admin_title']   ?? '' ),
		'menu_label'    => sanitize_text_field(  $_POST['menu_label']    ?? '' ),
		'max_pages'     => (string) max( 1, min( 100, absint( $_POST['max_pages'] ?? JETX_HUB_DEFAULT_MAX_PAGES ) ) ),
	];

	update_option( 'jetx_hub_settings', $settings );

	// Save graph field config into display settings.
	$disp = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );
	$disp['graph_category_field']     = sanitize_key( $_POST['graph_category_field']     ?? '' );
	$disp['graph_sub_category_field'] = sanitize_key( $_POST['graph_sub_category_field'] ?? '' );
	update_option( 'jetx_hub_display', $disp );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=settings&saved=1' ) );
	exit;
}

// ── Tab: Connection ───────────────────────────────────────────────────────────

function jetx_hub_save_connection(): void {
	if ( ! isset( $_POST['jetx_save_connection'] ) ) return;
	check_admin_referer( 'jetx_save_connection' );

	update_option( 'jetx_hub_token',         sanitize_text_field( $_POST['jetx_hub_token']         ?? '' ) );
	update_option( 'jetx_hub_db_id',         sanitize_text_field( $_POST['jetx_hub_db_id']         ?? '' ) );
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
	delete_transient( JETX_HUB_STALE_KEY );
	delete_transient( JETX_HUB_LOCK_KEY );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection&flushed=1' ) );
	exit;
}

// ── Tab: Columns ──────────────────────────────────────────────────────────────

function jetx_hub_save_columns(): void {
	if ( ! isset( $_POST['jetx_save_columns'] ) ) return;
	check_admin_referer( 'jetx_save_columns' );

	$order   = array_map( 'sanitize_text_field', (array) ( $_POST['col_order']   ?? [] ) );
	$visible = array_map( 'sanitize_text_field', (array) ( $_POST['col_visible'] ?? [] ) );
	$labels  = array_map( 'sanitize_text_field', (array) ( $_POST['col_label']   ?? [] ) );

	// Build a lookup from defaults so we can fall back cleanly.
	$defaults = jetx_hub_default_columns();
	$indexed  = [];
	foreach ( $defaults as $d ) {
		$indexed[ $d['key'] ] = $d;
	}

	// Also allow saving any active detected key not in defaults.
	$props = jetx_hub_property_defs();
	foreach ( $props as $key => $def ) {
		if ( ! isset( $indexed[ $key ] ) ) {
			$indexed[ $key ] = [ 'key' => $key, 'label' => $def['label'], 'visible' => true, 'order' => 99 ];
		}
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

// ── Tab: Filters ──────────────────────────────────────────────────────────────

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

// ── Tab: Sort ─────────────────────────────────────────────────────────────────

function jetx_hub_save_sorts(): void {
	if ( ! isset( $_POST['jetx_save_sorts'] ) ) return;
	check_admin_referer( 'jetx_save_sorts' );

	$raw          = (array) ( $_POST['sorts'] ?? [] );
	$sorts        = [];
	$allowed_dirs = [ 'ascending', 'descending' ];

	foreach ( $raw as $rule ) {
		$prop = sanitize_text_field( $rule['property']  ?? '' );
		$dir  = sanitize_text_field( $rule['direction'] ?? 'descending' );
		if ( $prop ) {
			$sorts[] = [
				'property'  => $prop,
				'direction' => in_array( $dir, $allowed_dirs, true ) ? $dir : 'descending',
			];
		}
	}

	update_option( 'jetx_hub_sorts', $sorts );

	// Sort changes affect the Notion API query — must bust the cache.
	delete_transient( JETX_HUB_CACHE_KEY );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=filters&saved=1' ) );
	exit;
}

// ── Tab: Display ──────────────────────────────────────────────────────────────

function jetx_hub_save_display(): void {
	if ( ! isset( $_POST['jetx_save_display'] ) ) return;
	check_admin_referer( 'jetx_save_display' );

	$disp = [
		'layout'               => sanitize_key(  $_POST['layout']            ?? 'table' ),
		'theme'                => sanitize_key(  $_POST['theme']             ?? 'dark' ),
		'board_group_by'       => sanitize_key(  $_POST['board_group_by']    ?? '' ),
		'show_search'          => ! empty(       $_POST['show_search'] ),
		'show_filters'         => ! empty(       $_POST['show_filters'] ),
		'items_limit'          => absint(        $_POST['items_limit']       ?? 0 ),
		'use_notion_colors'    => ! empty(       $_POST['use_notion_colors'] ),
		'show_summary'         => ! empty(       $_POST['show_summary'] ),
		'show_tags'            => ! empty(       $_POST['show_tags'] ),
		'show_github'          => ! empty(       $_POST['show_github'] ),
		'show_footer'          => ! empty(       $_POST['show_footer'] ),
		// v4.0 fields (preserve graph config that's set via Settings tab).
		'graph_category_field'     => sanitize_key( $_POST['graph_category_field']     ?? '' ),
		'graph_sub_category_field' => sanitize_key( $_POST['graph_sub_category_field'] ?? '' ),
		'primary_url_field'        => sanitize_key( $_POST['primary_url_field']        ?? '' ),
	];

	update_option( 'jetx_hub_display', $disp );

	wp_safe_redirect( admin_url( 'options-general.php?page=jetx-ai-hub&tab=display&saved=1' ) );
	exit;
}
