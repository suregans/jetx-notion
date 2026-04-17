<?php
/**
 * includes/property-defs.php
 *
 * Single source of truth for the active property map used throughout the plugin.
 *
 * v4.0 behaviour:
 *   If a schema has been auto-detected via the Notion API (jetx_hub_detect_schema()),
 *   jetx_hub_property_defs() returns only the admin-toggled-on fields from that
 *   detected schema. This requires zero PHP editing — all configuration is in WP Admin.
 *
 *   If no schema has been detected yet (first install, or before the admin clicks
 *   "Detect Fields"), the hardcoded 18-field JetX Media defaults are used as a
 *   working fallback so the plugin renders immediately on existing installs.
 *
 * Array format (each entry):
 *   'notion'   — Exact Notion property name (case-sensitive).
 *   'type'     — title | rich_text | select | multi_select | url | date | checkbox | number
 *   'label'    — Human-readable label shown in WP Admin column list.
 *   'internal' — true = hidden from public output; admin-only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the active property map for the current database.
 *
 * Uses auto-detected schema if available, otherwise falls back to JetX Media defaults.
 * Cached statically for the lifetime of the PHP request.
 *
 * @return array<string, array{notion:string, type:string, label:string, internal:bool}>
 */
function jetx_hub_property_defs(): array {
	static $resolved = null;
	if ( $resolved !== null ) return $resolved;

	// ── Auto-detected schema path (v4.0) ──────────────────────────────────────
	$detected = jetx_hub_get_active_fields(); // from notion-schema.php

	if ( ! empty( $detected ) ) {
		$resolved = [];
		foreach ( $detected as $key => $def ) {
			$resolved[ $key ] = [
				'notion'   => $def['notion'],
				'type'     => $def['type'],
				'label'    => ucwords( str_replace( '_', ' ', $key ) ),
				'internal' => false,
			];
		}
		return $resolved;
	}

	// ── Hardcoded fallback (JetX Media Notion schema) ─────────────────────────
	// Used on first install or before schema detection has run.
	// These are the v3 defaults — override via WP Admin → 🔍 Fields → Detect.
	$defaults = [
		'name'            => [ 'label' => 'Name',            'notion' => 'Name',                'type' => 'title',        'internal' => false ],
		'category'        => [ 'label' => 'Category',        'notion' => 'Category',            'type' => 'select',       'internal' => false ],
		'sub_category'    => [ 'label' => 'Sub-category',    'notion' => 'Sub-category',        'type' => 'select',       'internal' => false ],
		'status'          => [ 'label' => 'Status',          'notion' => 'Status',              'type' => 'select',       'internal' => false ],
		'traction'        => [ 'label' => 'Traction',        'notion' => 'Traction',            'type' => 'select',       'internal' => false ],
		'pricing'         => [ 'label' => 'Pricing',         'notion' => 'Pricing',             'type' => 'select',       'internal' => false ],
		'platform'        => [ 'label' => 'Platform',        'notion' => 'Platform',            'type' => 'multi_select', 'internal' => false ],
		'capability_tags' => [ 'label' => 'Capability Tags', 'notion' => 'Capability Tags',     'type' => 'multi_select', 'internal' => false ],
		'era'             => [ 'label' => 'Era',             'notion' => 'Era',                 'type' => 'select',       'internal' => false ],
		'publisher'       => [ 'label' => 'Publisher',       'notion' => 'Publisher / Company', 'type' => 'rich_text',    'internal' => false ],
		'summary'         => [ 'label' => 'Summary',         'notion' => 'Summary',             'type' => 'rich_text',    'internal' => false ],
		'why_it_matters'  => [ 'label' => 'Why It Matters',  'notion' => 'Why It Matters',      'type' => 'rich_text',    'internal' => false ],
		'official_url'    => [ 'label' => 'Official URL',    'notion' => 'Official URL',        'type' => 'url',          'internal' => false ],
		'github_repo'     => [ 'label' => 'GitHub Repo',     'notion' => 'GitHub Repo',         'type' => 'url',          'internal' => false ],
		'date_released'   => [ 'label' => 'Date Released',   'notion' => 'Date Released',       'type' => 'date',         'internal' => false ],
		'blog_status'     => [ 'label' => 'Blog Status',     'notion' => 'Blog Status',         'type' => 'select',       'internal' => true  ],
		'jetx_relevance'  => [ 'label' => 'JetX Relevance',  'notion' => 'JetX Relevance',      'type' => 'select',       'internal' => true  ],
		'jetx_use_case'   => [ 'label' => 'JetX Use Case',   'notion' => 'JetX Use Case',       'type' => 'multi_select', 'internal' => true  ],
	];

	$resolved = $defaults;
	return $resolved;
}

/**
 * Default column display order and visibility.
 * In v4.0 the defaults are rebuilt dynamically from detected schema when available.
 *
 * @return array<int, array{key:string, label:string, visible:bool, order:int}>
 */
function jetx_hub_default_columns(): array {
	$props = jetx_hub_property_defs();
	$cols  = [];
	$order = 0;

	// Title field always first and always visible.
	foreach ( $props as $key => $def ) {
		if ( $def['type'] === 'title' ) {
			$cols[] = [ 'key' => $key, 'label' => $def['label'], 'visible' => true, 'order' => $order++ ];
			break;
		}
	}

	// Select / multi_select fields next (usually the most useful).
	foreach ( $props as $key => $def ) {
		if ( $def['type'] === 'title' ) continue;
		if ( ! in_array( $def['type'], [ 'select', 'multi_select' ], true ) ) continue;
		if ( $def['internal'] ) continue;
		$cols[] = [ 'key' => $key, 'label' => $def['label'], 'visible' => $order < 7, 'order' => $order++ ];
	}

	// Everything else.
	foreach ( $props as $key => $def ) {
		if ( $def['type'] === 'title' ) continue;
		if ( in_array( $def['type'], [ 'select', 'multi_select' ], true ) ) continue;
		$cols[] = [ 'key' => $key, 'label' => $def['label'], 'visible' => false, 'order' => $order++ ];
	}

	return $cols;
}

/**
 * Default sort rules applied to the Notion API query.
 *
 * @return array<int, array{property:string, direction:string}>
 */
function jetx_hub_default_sorts(): array {
	// Use date field if one exists, otherwise first field.
	$props = jetx_hub_property_defs();
	foreach ( $props as $key => $def ) {
		if ( $def['type'] === 'date' ) {
			return [ [ 'property' => $key, 'direction' => 'descending' ] ];
		}
	}
	// Fall back: sort by the title field ascending.
	foreach ( $props as $key => $def ) {
		if ( $def['type'] === 'title' ) {
			return [ [ 'property' => $key, 'direction' => 'ascending' ] ];
		}
	}
	return [];
}

/**
 * Default display / layout settings.
 *
 * @return array<string, mixed>
 */
function jetx_hub_default_display(): array {
	// Determine a sensible default for board_group_by and graph fields.
	$props      = jetx_hub_property_defs();
	$select_keys = [];
	foreach ( $props as $key => $def ) {
		if ( $def['type'] === 'select' && ! $def['internal'] ) {
			$select_keys[] = $key;
		}
	}

	return [
		'layout'               => 'table',
		'theme'                => 'dark',
		'board_group_by'       => $select_keys[0] ?? '',
		'show_search'          => true,
		'show_filters'         => true,
		'items_limit'          => 0,
		'use_notion_colors'    => true,
		'show_summary'         => true,
		'show_tags'            => false,
		'show_github'          => false,
		'show_footer'          => true,
		// v4.0 graph configuration
		'graph_category_field'     => $select_keys[0] ?? '',
		'graph_sub_category_field' => $select_keys[1] ?? '',
		// v4.0 field roles
		'primary_url_field'        => '',
	];
}

/**
 * Maps Notion color names to dark/light theme CSS values.
 *
 * @return array<string, array{dark:array{bg:string,text:string}, light:array{bg:string,text:string}}>
 */
function jetx_hub_notion_colors(): array {
	return [
		'default' => [ 'dark' => [ 'bg' => '#1e293b', 'text' => '#94a3b8' ], 'light' => [ 'bg' => '#f1f5f9', 'text' => '#475569' ] ],
		'gray'    => [ 'dark' => [ 'bg' => '#1e293b', 'text' => '#94a3b8' ], 'light' => [ 'bg' => '#f1f5f9', 'text' => '#6b7280' ] ],
		'brown'   => [ 'dark' => [ 'bg' => '#292219', 'text' => '#c8a97e' ], 'light' => [ 'bg' => '#fef3c7', 'text' => '#92400e' ] ],
		'orange'  => [ 'dark' => [ 'bg' => '#2d1b00', 'text' => '#fb923c' ], 'light' => [ 'bg' => '#fff7ed', 'text' => '#c2410c' ] ],
		'yellow'  => [ 'dark' => [ 'bg' => '#2d2500', 'text' => '#fbbf24' ], 'light' => [ 'bg' => '#fefce8', 'text' => '#a16207' ] ],
		'green'   => [ 'dark' => [ 'bg' => '#052e16', 'text' => '#4ade80' ], 'light' => [ 'bg' => '#f0fdf4', 'text' => '#166534' ] ],
		'blue'    => [ 'dark' => [ 'bg' => '#1e3a5f', 'text' => '#60a5fa' ], 'light' => [ 'bg' => '#eff6ff', 'text' => '#1d4ed8' ] ],
		'purple'  => [ 'dark' => [ 'bg' => '#1e1b4b', 'text' => '#a78bfa' ], 'light' => [ 'bg' => '#faf5ff', 'text' => '#7e22ce' ] ],
		'pink'    => [ 'dark' => [ 'bg' => '#3b0764', 'text' => '#f472b6' ], 'light' => [ 'bg' => '#fdf4ff', 'text' => '#86198f' ] ],
		'red'     => [ 'dark' => [ 'bg' => '#450a0a', 'text' => '#f87171' ], 'light' => [ 'bg' => '#fef2f2', 'text' => '#b91c1c' ] ],
	];
}

/**
 * Generate CSS custom properties for the chosen theme.
 *
 * @param  string $theme  'dark' | 'light'
 * @return string         CSS string suitable for wp_add_inline_style().
 */
function jetx_hub_theme_css_vars( string $theme ): string {
	$vars = $theme === 'light'
		? [
			'--jhub-bg'       => '#ffffff',
			'--jhub-bg2'      => '#f8fafc',
			'--jhub-border'   => '#e2e8f0',
			'--jhub-text'     => '#1e293b',
			'--jhub-text-dim' => '#64748b',
			'--jhub-link'     => '#2563eb',
			'--jhub-hover'    => '#f1f5f9',
			'--jhub-badge-bg' => '#f1f5f9',
			'--jhub-badge-tx' => '#475569',
			'--jhub-input-bg' => '#f8fafc',
		]
		: [
			'--jhub-bg'       => '#0f172a',
			'--jhub-bg2'      => '#1e293b',
			'--jhub-border'   => '#334155',
			'--jhub-text'     => '#e2e8f0',
			'--jhub-text-dim' => '#94a3b8',
			'--j