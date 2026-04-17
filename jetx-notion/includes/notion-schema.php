<?php
/**
 * includes/notion-schema.php
 *
 * Auto-detects the Notion database schema (all property names and types) via the
 * Notion API and stores the result in wp_options. Provides helpers to retrieve
 * the detected schema and the admin-toggled active fields.
 *
 * wp_options keys used:
 *   jetx_hub_detected_schema  — raw schema from Notion API (array of prop info)
 *   jetx_hub_active_fields    — admin-toggled field keys (key => bool)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Notion type support list ──────────────────────────────────────────────────
// Only these Notion property types can be meaningfully extracted via the API.
const JETX_HUB_SUPPORTED_TYPES = [
	'title',
	'rich_text',
	'select',
	'multi_select',
	'url',
	'date',
	'status',   // treated as select in parse_page()
	'checkbox',
	'number',
	'email',
	'phone_number',
];

/**
 * Convert a Notion property name to a safe internal PHP key.
 *
 * Examples:
 *   "Name"            → "name"
 *   "Official URL"    → "official_url"
 *   "Why It Matters"  → "why_it_matters"
 *   "Publisher / Co." → "publisher_co"
 *
 * @param  string $notion_name  Raw Notion property name.
 * @return string               Lowercase snake_case key, ASCII-safe.
 */
function jetx_hub_name_to_key( string $notion_name ): string {
	$key = mb_strtolower( $notion_name );
	$key = preg_replace( '/[^a-z0-9]+/', '_', $key );
	$key = trim( $key, '_' );
	return $key !== '' ? $key : 'field';
}

/**
 * Map a Notion property type to our internal simplified type.
 * All unsupported types fall back to 'rich_text' (renders as text).
 *
 * @param  string $notion_type  e.g. 'select', 'title', 'number'.
 * @return string               One of: title|rich_text|select|multi_select|url|date|checkbox|number.
 */
function jetx_hub_normalize_type( string $notion_type ): string {
	$map = [
		'title'        => 'title',
		'rich_text'    => 'rich_text',
		'select'       => 'select',
		'multi_select' => 'multi_select',
		'url'          => 'url',
		'date'         => 'date',
		'status'       => 'select',      // Notion "status" behaves like select
		'checkbox'     => 'checkbox',
		'number'       => 'number',
		'email'        => 'rich_text',
		'phone_number' => 'rich_text',
		'created_time' => 'date',
	];
	return $map[ $notion_type ] ?? 'rich_text';
}

/**
 * Call the Notion API to detect all properties for the configured database.
 * Stores the result in wp_options('jetx_hub_detected_schema').
 *
 * On success returns the schema array.
 * On failure returns ['error' => string].
 *
 * Schema array format:
 * [
 *   'name' => [
 *     'notion' => 'Name',        // exact Notion property name
 *     'type'   => 'title',       // normalized type
 *     'raw_type' => 'title',     // raw Notion type
 *   ],
 *   'category' => [ ... ],
 *   ...
 * ]
 *
 * @return array
 */
function jetx_hub_detect_schema(): array {
	$token = get_option( 'jetx_hub_token', '' );
	$db_id = get_option( 'jetx_hub_db_id', '' );

	if ( empty( $token ) ) {
		return [ 'error' => 'no_token' ];
	}
	if ( empty( $db_id ) ) {
		return [ 'error' => 'no_db_id' ];
	}

	$response = wp_remote_get(
		JETX_HUB_API_BASE . 'databases/' . sanitize_text_field( $db_id ),
		[
			'headers' => [
				'Authorization'  => 'Bearer ' . $token,
				'Notion-Version' => JETX_HUB_API_VER,
				'Content-Type'   => 'application/json',
			],
			'timeout' => 20,
		]
	);

	if ( is_wp_error( $response ) ) {
		return [ 'error' => $response->get_error_message() ];
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code !== 200 ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return [ 'error' => $body['message'] ?? 'Notion API HTTP ' . $code ];
	}

	$data       = json_decode( wp_remote_retrieve_body( $response ), true );
	$properties = $data['properties'] ?? [];

	if ( empty( $properties ) ) {
		return [ 'error' => 'No properties found in this database.' ];
	}

	// Build the schema. Ensure keys are unique by appending a counter for collisions.
	$schema   = [];
	$key_used = [];

	// Title field always comes first (Notion databases always have exactly one).
	foreach ( $properties as $notion_name => $prop ) {
		if ( ( $prop['type'] ?? '' ) !== 'title' ) continue;

		$key              = jetx_hub_name_to_key( $notion_name );
		$schema[ $key ]   = [
			'notion'   => $notion_name,
			'type'     => 'title',
			'raw_type' => 'title',
		];
		$key_used[ $key ] = true;
	}

	// All other supported types follow.
	foreach ( $properties as $notion_name => $prop ) {
		$raw_type = $prop['type'] ?? '';
		if ( $raw_type === 'title' ) continue; // already handled
		if ( ! in_array( $raw_type, JETX_HUB_SUPPORTED_TYPES, true ) ) continue;

		$key = jetx_hub_name_to_key( $notion_name );

		// Deduplicate: if the key is already taken by a different Notion prop, append a suffix.
		if ( isset( $key_used[ $key ] ) ) {
			$original = $key;
			$counter  = 2;
			while ( isset( $key_used[ $key ] ) ) {
				$key = $original . '_' . $counter++;
			}
		}

		$schema[ $key ]   = [
			'notion'   => $notion_name,
			'type'     => jetx_hub_normalize_type( $raw_type ),
			'raw_type' => $raw_type,
		];
		$key_used[ $key ] = true;
	}

	// Persist to wp_options (not autoloaded — only needed in admin + shortcode).
	update_option( 'jetx_hub_detected_schema', $schema, false );

	// Seed active_fields defaults: all fields ON unless already saved by admin.
	$existing_active = get_option( 'jetx_hub_active_fields', null );
	if ( $existing_active === null ) {
		$active = [];
		foreach ( $schema as $key => $def ) {
			$active[ $key ] = true;
		}
		update_option( 'jetx_hub_active_fields', $active, false );
	}

	// Flush data cache — schema change means parsed rows need rebuilding.
	delete_transient( JETX_HUB_CACHE_KEY );
	delete_transient( JETX_HUB_STALE_KEY );
	delete_transient( JETX_HUB_LOCK_KEY );

	return $schema;
}

/**
 * Return the stored detected schema, or an empty array if none detected yet.
 *
 * @return array  Key → [notion, type, raw_type]
 */
function jetx_hub_get_detected_schema(): array {
	static $cache = null;
	if ( $cache !== null ) return $cache;
	$cache = get_option( 'jetx_hub_detected_schema', [] );
	return $cache;
}

/**
 * Return only the active (toggled-on) fields from the detected schema.
 * Used by jetx_hub_property_defs() to build the working property map.
 *
 * @return array  Same format as jetx_hub_get_detected_schema() but filtered.
 */
function jetx_hub_get_active_fields(): array {
	$detected = jetx_hub_get_detected_schema();
	if ( empty( $detected ) ) return [];

	$active_flags = get_option( 'jetx_hub_active_fields', [] );

	$active = [];
	foreach ( $detected as $key => $def ) {
		// Default to true if the admin has never toggled this field.
		$on = $active_flags[ $key ] ?? true;
		if ( $on ) {
			$active[ $key ] = $def;
		}
	}

	// Always include the title field even if accidentally toggled off.
	foreach ( $detected as $key => $def ) {
		if ( $def['type'] === 'title' && ! isset( $active[ $key ] ) ) {
			$active = array_merge( [ $key => $def ], $active );
		}
	}

	return $active;
}

/**
 * Return the key of the first title-type field in the active schema.
 * Returns empty string if no title field exists (should never happen in Notion).
 *
 * @return string
 */
function jetx_hub_title_field_key(): string {
	static $key = null;
	if ( $key !== null ) return $key;
	foreach ( jetx_hub_property_defs() as $k => $def ) {
		if ( $def['type'] === 'title' ) {
			$key = $k;
			return $key;
		}
	}
	$key = '';
	return $key;
}

/**
 * Return the key of the primary URL field (used for linking tool names).
 * Respects the admin-configured preference from Display settings.
 *
 * @return string  Empty string if no URL field is active.
 */
function jetx_hub_primary_url_field_key(): string {
	static $key = null;
	if ( $key !== null ) return $key;

	$disp    = get_option( 'jetx_hub_display', [] );
	$prefer  = $disp['primary_url_field'] ?? '';
	$props   = jetx_hub_property_defs();

	if ( $prefer && isset( $props[ $prefer ] ) && $props[ $prefer ]['type'] === 'url' ) {
		$key = $prefer;
		return $key;
	}

	// Fall back: first URL field in active schema.
	foreach ( $props as $k => $def ) {
		if ( $def['type'] === 'url' ) {
			$key = $k;
			return $key;
		}
	}
	$key = '';
	return $key;
}
