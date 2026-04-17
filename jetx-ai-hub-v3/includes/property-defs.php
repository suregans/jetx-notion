<?php
/**
 * includes/property-defs.php
 *
 * Defines the mapping between internal PHP keys and Notion property names,
 * plus default column order, sort rules, display settings, and Notion color map.
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  WHITE-LABEL / DIFFERENT DATABASE                                        ║
 * ║  The 'notion' values in jetx_hub_property_defs() are the exact property  ║
 * ║  names from your Notion database (case-sensitive).                       ║
 * ║  If deploying against a different Notion database, update every 'notion' ║
 * ║  value to match that database's column names, and add/remove entries     ║
 * ║  as needed. The 'key' (left side) is the internal PHP identifier and     ║
 * ║  can stay the same.                                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the canonical property map, merging admin-saved overrides into the
 * built-in defaults. All Notion field names and types are therefore configurable
 * from WP Admin (Schema tab) without editing any PHP file.
 *
 * Keys:
 *   'label'    — Display name shown in WP Admin column list.
 *   'notion'   — Exact property name in the Notion database (case-sensitive).
 *               Overridable in WP Admin → Settings → JetX AI Hub → Schema.
 *   'type'     — Notion property type: title | rich_text | select | multi_select | url | date.
 *               Overridable in WP Admin → Settings → JetX AI Hub → Schema.
 *   'internal' — true = hidden from public output; only shown to admins.
 *               Overridable in WP Admin → Settings → JetX AI Hub → Schema.
 *
 * @return array<string, array{label:string, notion:string, type:string, internal:bool}>
 */
function jetx_hub_property_defs(): array {
	// Static cache so we only merge once per request.
	static $resolved = null;
	if ( $resolved !== null ) return $resolved;

	// ── Built-in defaults (JetX Media Notion schema) ─────────────────────────
	// These are the fallback values. Every field can be overridden via the
	// Schema tab in WP Admin — no PHP editing required.
	$defaults = [
		// Visible / public fields
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
		// Internal / admin-only fields
		'blog_status'     => [ 'label' => 'Blog Status',       'notion' => 'Blog Status',         'type' => 'select',       'internal' => true  ],
		'jetx_relevance'  => [ 'label' => 'JetX Relevance',    'notion' => 'JetX Relevance',      'type' => 'select',       'internal' => true  ],
		'jetx_use_case'   => [ 'label' => 'JetX Use Case',     'notion' => 'JetX Use Case',       'type' => 'multi_select', 'internal' => true  ],
	];

	// ── Merge admin-saved overrides ───────────────────────────────────────────
	// Saved by the Schema tab via jetx_hub_save_schema() → wp_options 'jetx_hub_schema'.
	$saved        = get_option( 'jetx_hub_schema', [] );
	$valid_types  = [ 'title', 'rich_text', 'select', 'multi_select', 'url', 'date' ];

	foreach ( $defaults as $key => &$def ) {
		if ( empty( $saved[ $key ] ) ) continue;

		$override = $saved[ $key ];

		// Notion property name — only apply if non-empty.
		if ( ! empty( $override['notion'] ) ) {
			$def['notion'] = sanitize_text_field( $override['notion'] );
		}

		// Property type — only apply if a valid Notion type.
		if ( ! empty( $override['type'] ) && in_array( $override['type'], $valid_types, true ) ) {
			$def['type'] = $override['type'];
		}

		// Internal flag.
		if ( isset( $override['internal'] ) ) {
			$def['internal'] = (bool) $override['internal'];
		}
	}
	unset( $def );

	$resolved = $defaults;
	return $resolved;
}

/**
 * Default column display order and visibility.
 * Saved to wp_options under 'jetx_hub_columns' on first admin save.
 *
 * @return array<int, array{key:string, label:string, visible:bool, order:int}>
 */
function jetx_hub_default_columns(): array {
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

/**
 * Default sort rules applied to the Notion API query.
 *
 * @return array<int, array{property:string, direction:string}>
 */
function jetx_hub_default_sorts(): array {
	return [
		[ 'property' => 'date_released', 'direction' => 'descending' ],
	];
}

/**
 * Default display / layout settings.
 *
 * @return array<string, mixed>
 */
function jetx_hub_default_display(): array {
	return [
		'layout'            => 'table',
		'theme'             => 'dark',
		'board_group_by'    => 'category',
		'show_search'       => true,
		'show_filters'      => true,
		'items_limit'       => 0,
		'use_notion_colors' => true,
		'show_summary'      => true,
		'show_tags'         => true,
		'show_github'       => true,
		'show_footer'       => true,
	];
}

/**
 * Maps Notion color names to dark/light theme CSS values.
 * Used both in PHP badge rendering and in the admin color preview.
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
		'pink'    => [ '