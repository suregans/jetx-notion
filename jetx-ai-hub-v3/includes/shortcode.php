<?php
/**
 * includes/shortcode.php
 *
 * Registers [jetx_ai_hub] and orchestrates the frontend render pipeline.
 *
 * v4.0 changes:
 *   - jetx_hub_name_cell()  — dynamic: uses title-type + first URL field
 *   - jetx_hub_row_attrs()  — dynamic: uses first select fields for data-* attrs
 *   - jetx_hub_render_cell() — NEW generic type-based cell renderer
 *   - Graph layout + view toggle button (Table ↔ Graph)
 *   - Sigma.js + graphology enqueued only when graph layout is active
 *   - Footer uses jetx_hub_setting() instead of hardcoded constants
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'jetx_ai_hub', 'jetx_hub_shortcode' );

/**
 * Shortcode callback — [jetx_ai_hub]
 *
 * @param  array $atts
 * @return string  HTML
 */
function jetx_hub_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		[
			'limit'         => 0,
			'category'      => '',
			'show_internal' => 'false',
		],
		$atts,
		'jetx_ai_hub'
	);

	$show_internal = filter_var( $atts['show_internal'], FILTER_VALIDATE_BOOLEAN )
	                 && current_user_can( 'manage_options' );

	$data = jetx_hub_fetch_data();

	// ── Error states ──────────────────────────────────────────────────────────
	if ( isset( $data['error'] ) ) {
		if ( in_array( $data['error'], [ 'no_token', 'no_db_id' ], true ) && current_user_can( 'manage_options' ) ) {
			return '<p style="color:#f87171;font-style:italic;">⚠️ JetX AI Hub: Notion connection not configured. '
			       . '<a href="' . esc_url( admin_url( 'options-general.php?page=jetx-ai-hub' ) ) . '">Configure →</a></p>';
		}
		if ( $data['error'] === 'refreshing' ) {
			return '<p style="color:#94a3b8;">🔄 AI Hub loading — please refresh in a moment.</p>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="color:#f87171;">⚠️ Notion API error: ' . esc_html( $data['error'] ) . '</p>';
		}
		return '';
	}

	if ( empty( $data ) ) {
		return '<p>No entries found.</p>';
	}

	// ── Settings ──────────────────────────────────────────────────────────────
	$disp       = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );
	$cols_cfg   = get_option( 'jetx_hub_columns', jetx_hub_default_columns() );
	usort( $cols_cfg, fn( $a, $b ) => $a['order'] <=> $b['order'] );

	$items = $data;

	// Shortcode pre-filter by category value (works with dynamic keys).
	if ( ! empty( $atts['category'] ) ) {
		$fc    = strtolower( trim( $atts['category'] ) );
		$items = array_values(
			array_filter( $items, function ( $i ) use ( $fc ) {
				foreach ( $i as $val ) {
					if ( is_array( $val ) && isset( $val['name'] ) ) {
						if ( strtolower( $val['name'] ) === $fc ) return true;
					}
				}
				return false;
			} )
		);
	}

	// Items limit.
	$limit = (int) $atts['limit'] > 0 ? (int) $atts['limit'] : (int) $disp['items_limit'];
	if ( $limit > 0 ) {
		$items = array_slice( $items, 0, $limit );
	}

	$layout            = $disp['layout']  ?? 'table';
	$theme             = $disp['theme']   ?? 'dark';
	$use_notion_colors = (bool) ( $disp['use_notion_colors'] ?? true );

	// ── Enqueue assets ────────────────────────────────────────────────────────
	wp_enqueue_style(
		'jetx-hub-frontend',
		JETX_HUB_URL . 'assets/css/frontend.css',
		[],
		JETX_HUB_VERSION
	);
	wp_add_inline_style( 'jetx-hub-frontend', jetx_hub_theme_css_vars( $theme ) );

	wp_enqueue_script(
		'jetx-hub-frontend',
		JETX_HUB_URL . 'assets/js/frontend.js',
		[],
		JETX_HUB_VERSION,
		true
	);

	// Graph assets — enqueue for 'graph' layout or when graph toggle is enabled
	// (we always enqueue if there's at least one select field, since toggle is shown).
	$props        = jetx_hub_property_defs();
	$has_selects  = count( array_filter( $props, fn( $p ) => $p['type'] === 'select' ) ) >= 1;

	if ( $has_selects ) {
		wp_enqueue_style(
			'jetx-hub-graph',
			JETX_HUB_URL . 'assets/css/graph.css',
			[ 'jetx-hub-frontend' ],
			JETX_HUB_VERSION
		);

		// graphology — must come before sigma.
		wp_enqueue_script(
			'graphology',
			'https://cdn.jsdelivr.net/npm/graphology@0.25.4/dist/graphology.umd.min.js',
			[],
			'0.25.4',
			true
		);
		wp_enqueue_script(
			'sigma',
			'https://cdn.jsdelivr.net/npm/sigma@2.4.0/build/sigma.min.js',
			[ 'graphology' ],
			'2.4.0',
			true
		);
		wp_enqueue_script(
			'jetx-hub-graph',
			JETX_HUB_URL . 'assets/js/graph.js',
			[ 'sigma' ],
			JETX_HUB_VERSION,
			true
		);
	}

	// ── Render ────────────────────────────────────────────────────────────────
	ob_start();

	echo '<div class="jetx-hub-wrap jhub-theme-' . esc_attr( $theme ) . '" '
	     . 'id="jetx-ai-hub" '
	     . 'data-layout="' . esc_attr( $layout ) . '">';

	// View toggle button (Table / Graph) — shown when graph is possible.
	if ( $has_selects ) {
		$is_graph = $layout === 'graph';
		echo '<div class="jhub-view-toggle" role="group" aria-label="View mode">'
		     . '<button class="jhub-toggle-btn' . ( ! $is_graph ? ' active' : '' ) . '" '
		     . 'data-view="table" aria-pressed="' . ( ! $is_graph ? 'true' : 'false' ) . '">📋 Table</button>'
		     . '<button class="jhub-toggle-btn' . ( $is_graph ? ' active' : '' ) . '" '
		     . 'data-view="graph" aria-pressed="' . ( $is_graph ? 'true' : 'false' ) . '">🔗 Graph</button>'
		     . '</div>';
	}

	// Controls (search + filter dropdowns).
	if ( $disp['show_search'] || $disp['show_filters'] ) {
		require JETX_HUB_PATH . 'public/views/controls.php';
	}

	// ── Table/gallery/list/board panel ────────────────────────────────────────
	$table_hidden = $layout === 'graph' ? ' jhub-hidden' : '';
	echo '<div class="jhub-view-panel jhub-table-panel' . $table_hidden . '" id="jhub-panel-table">';

	switch ( $layout === 'graph' ? 'table' : $layout ) {
		case 'gallery':
			require JETX_HUB_PATH . 'public/views/layout-gallery.php';
			break;
		case 'list':
			require JETX_HUB_PATH . 'public/views/layout-list.php';
			break;
		case 'board':
			require JETX_HUB_PATH . 'public/views/layout-board.php';
			break;
		default:
			require JETX_HUB_PATH . 'public/views/layout-table.php';
			break;
	}

	echo '</div>'; // .jhub-table-panel

	// ── Graph panel ───────────────────────────────────────────────────────────
	if ( $has_selects ) {
		$graph_hidden = $layout !== 'graph' ? ' jhub-hidden' : '';
		echo '<div class="jhub-view-panel jhub-graph-panel' . $graph_hidden . '" id="jhub-panel-graph">';
		require JETX_HUB_PATH . 'public/views/layout-graph.php';
		echo '</div>'; // .jhub-graph-panel
	}

	// No-results message (for search/filter).
	echo '<div class="jhub-no-results" id="jhub-no-results" style="display:none;" role="status">'
	     . 'No results. '
	     . '<button class="jhub-reset-btn" id="jhub-reset" type="button">Clear filters</button>'
	     . '</div>';

	// Footer.
	if ( $disp['show_footer'] ) {
		echo '<div class="jhub-footer">Data synced from '
		     . '<a href="' . esc_url( jetx_hub_notion_url() ) . '" target="_blank" rel="noopener">Notion</a>'
		     . ' &middot; Curated by '
		     . '<a href="' . esc_url( jetx_hub_setting( 'branding_url', JETX_HUB_DEFAULT_BRANDING_URL ) ) . '" target="_blank" rel="noopener">'
		     . esc_html( jetx_hub_setting( 'branding_name', JETX_HUB_DEFAULT_BRANDING_NAME ) ) . '</a></div>';
	}

	echo '</div>'; // .jetx-hub-wrap

	return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────────────────────
// HELPER FUNCTIONS — shared by layout templates
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Generic type-based cell renderer.
 * Used by all layout templates to render any field value without knowing its key.
 *
 * @param  mixed  $val               The field value (string | array{name,color} | array of same).
 * @param  string $type              Normalized type: title|rich_text|select|multi_select|url|date|etc.
 * @param  bool   $use_notion_colors
 * @param  string $theme
 * @return string  HTML.
 */
function jetx_hub_render_cell( $val, string $type, bool $use_notion_colors, string $theme ): string {
	switch ( $type ) {
		case 'select':
			if ( is_array( $val ) && isset( $val['name'] ) ) {
				return jetx_hub_badge( $val, $use_notion_colors, $theme );
			}
			return ! empty( $val ) ? '<span class="jhub-text">' . esc_html( (string) $val ) . '</span>' : '';

		case 'multi_select':
			if ( is_array( $val ) ) {
				$out = '';
				foreach ( $val as $item ) {
					if ( ! empty( $item['name'] ) ) {
						$out .= jetx_hub_badge( $item, $use_notion_colors, $theme );
					}
				}
				return $out;
			}
			return '';

		case 'url':
			$url = is_array( $val ) ? ( $val['name'] ?? '' ) : (string) $val;
			if ( empty( $url ) ) return '';
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">↗ Link</a>';

		case 'title':
			// Title cells are handled by jetx_hub_name_cell() — this is a fallback.
			$text = is_array( $val ) ? ( $val['name'] ?? (string) $val ) : (string) $val;
			return '<span>' . esc_html( $text ) . '</span>';

		case 'checkbox':
			return $val ? '✅' : '—';

		case 'number':
			return is_numeric( $val ) ? esc_html( number_format( (float) $val ) ) : '';

		case 'date':
			$str = is_array( $val ) ? ( $val['name'] ?? (string) $val ) : (string) $val;
			return '<span class="jhub-date">' . esc_html( $str ) . '</span>';

		default: // rich_text and everything else.
			$str = is_array( $val ) ? ( $val['name'] ?? implode( ', ', array_column( (array) $val, 'name' ) ) ) : (string) $val;
			return $str !== '' ? esc_html( mb_strimwidth( $str, 0, 120, '…' ) ) : '';
	}
}

/**
 * Render the name/title cell — dynamic in v4.0.
 * Automatically finds the title-type field and the primary URL field.
 *
 * @param  array  $item
 * @param  array  $disp
 * @param  bool   $use_notion_colors
 * @param  string $theme
 * @return string HTML.
 */
function jetx_hub_name_cell( array $item, array $disp, bool $use_notion_colors, string $theme ): string {
	$title_key = jetx_hub_title_field_key();
	$url_key   = jetx_hub_primary_url_field_key();
	$props     = jetx_hub_property_defs();

	// Resolve title text.
	$name_raw = $item[ $title_key ] ?? '';
	$name     = is_array( $name_raw ) ? ( $name_raw['name'] ?? '' ) : (string) $name_raw;

	// Resolve URL.
	$url_raw = $url_key ? ( $item[ $url_key ] ?? '' ) : '';
	$url     = is_array( $url_raw ) ? ( $url_raw['name'] ?? '' ) : (string) $url_raw;

	$out = '<div class="jhub-name">';

	if ( ! empty( $url ) ) {
		$out .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">'
		        . esc_html( $name )
		        . ' <span class="jhub-ext" aria-hidden="true">↗</span></a>';
	} else {
		$out .= '<span>' . esc_html( $name ) . '</span>';
	}

	$out .= '</div>';

	// Publisher: first rich_text field (excluding summary).
	$publisher_key = null;
	$summary_key   = null;
	foreach ( $props as $k => $def ) {
		if ( $def['type'] === 'rich_text' && ! $def['internal'] ) {
			if ( $publisher_key === null ) {
				$publisher_key = $k;
			} elseif ( $summary_key === null ) {
				$summary_key = $k;
			}
		}
	}
	// Heuristic: if a key contains "summary", prefer it as summary; first is publisher.
	foreach ( $props as $k => $def ) {
		if ( $def['type'] === 'rich_text' && ! $def['internal'] ) {
			if ( stripos( $k, 'summary' ) !== false || stripos( $def['notion'], 'summary' ) !== false ) {
				$summary_key   = $k;
			} elseif ( stripos( $k, 'publisher' ) !== false || stripos( $k, 'company' ) !== false ) {
				$publisher_key = $k;
			}
		}
	}

	if ( $publisher_key && ! empty( $item[ $publisher_key ] ) ) {
		$pub = is_array( $item[ $publisher_key ] ) ? ( $item[ $publisher_key ]['name'] ?? '' ) : (string) $item[ $publisher_key ];
		if ( $pub ) {
			$out .= '<div class="jhub-pub">' . esc_html( $pub ) . '</div>';
		}
	}

	if ( ! empty( $disp['show_summary'] ) && $summary_key && ! empty( $item[ $summary_key ] ) ) {
		$sum = is_array( $item[ $summary_key ] ) ? ( $item[ $summary_key ]['name'] ?? '' ) : (string) $item[ $summary_key ];
		if ( $sum ) {
			$out .= '<div class="jhub-sum">' . esc_html( mb_strimwidth( $sum, 0, 140, '…' ) ) . '</div>';
		}
	}

	// Tags: first multi_select field that looks like "tags" or "capabilities".
	if ( ! empty( $disp['show_tags'] ) ) {
		$tag_key = null;
		foreach ( $props as $k => $def ) {
			if ( $def['type'] === 'multi_select' && ! $def['internal'] ) {
				$tag_key = $k;
				// Prefer a field with 'tag' or 'capabilit' in its name.
				if ( stripos( $k, 'tag' ) !== false || stripos( $k, 'capabilit' ) !== false ) break;
			}
		}
		if ( $tag_key && ! empty( $item[ $tag_key ] ) ) {
			$out .= '<div class="jhub-tags">';
			foreach ( (array) $item[ $tag_key ] as $tag ) {
				if ( ! empty( $tag['name'] ) ) {
					$out .= jetx_hub_badge( $tag, $use_notion_colors, $theme );
				}
			}
			$out .= '</div>';
		}
	}

	// GitHub: any url field with 'github' in its key.
	if ( ! empty( $disp['show_github'] ) ) {
		foreach ( $props as $k => $def ) {
			if ( $def['type'] === 'url' && stripos( $k, 'github' ) !== false ) {
				$gh_url = $item[ $k ] ?? '';
				if ( is_array( $gh_url ) ) $gh_url = $gh_url['name'] ?? '';
				if ( ! empty( $gh_url ) ) {
					$out .= '<a href="' . esc_url( $gh_url ) . '" class="jhub-gh" target="_blank" rel="noopener noreferrer">⭐ GitHub</a>';
				}
				break;
			}
		}
	}

	return $out;
}

/**
 * Build data-* attributes for a row element used by frontend.js filtering.
 * Dynamic in v4.0: uses the first 4 select fields as filter axes.
 *
 * @param  array $item
 * @return string HTML attribute string.
 */
function jetx_hub_row_attrs( array $item ): string {
	$props       = jetx_hub_property_defs();
	$select_keys = [];
	foreach ( $props as $k => $def ) {
		if ( in_array( $def['type'], [ 'select', 'multi_select' ], true ) && ! $def['internal'] ) {
			$select_keys[] = $k;
			if ( count( $select_keys ) >= 4 ) break;
		}
	}

	// Build full-text search blob from all active text-ish fields.
	$blob_parts = [];
	foreach ( $item as $key => $val ) {
		if ( is_string( $val ) && $val !== '' ) {
			$blob_parts[] = $val;
		} elseif ( is_array( $val ) ) {
			if ( isset( $val['name'] ) ) {
				$blob_parts[] = $val['name'];
			} else {
				foreach ( $val as $v ) {
					if ( isset( $v['name'] ) ) $blob_parts[] = $v['name'];
				}
			}
		}
	}
	$blob = strtolower( implode( ' ', array_filter( $blob_parts ) ) );

	$attrs = 'class="jhub-row"'
	         . ' data-search="' . esc_attr( $blob ) . '"';

	// Add data-[key]="value" for each of the first 4 select axes.
	foreach ( $select_keys as $i => $sk ) {
		$v = $item[ $sk ] ?? '';
		if ( is_array( $v ) ) {
			$name = isset( $v['name'] )
				? $v['name']
				: implode( ',', array_column( $v, 'name' ) );
		} else {
			$name = (string) $v;
		}
		// Use index-based data attrs (data-f0, data-f1...) so frontend.js can read them.
		$attrs .= ' data-f' . $i . '="' . esc_attr( $name ) . '"';
	}

	return $attrs;
}

/**
 * Render a badge span for a select / multi_select item with optional Notion color.
 *
 * @param  array  $item              ['name' => string, 'color' => string]
 * @param  bool   $use_notion_colors
 * @param  string $theme             'dark' | 'light'
 * @return string HTML.
 */
function jetx_hub_badge( array $item, bool $use_notion_colors, string $theme = 'dark' ): string {
	if ( empty( $item['name'] ) ) return '';

	$color   = $item['color'] ?? 'default';
	$colors  = jetx_hub_notion_colors();
	$palette = $colors[ $color ] ?? $colors['default'];
	$style   = '';

	if ( $use_notion_colors ) {
		$style = ' style="background:' . esc_attr( $palette[ $theme ]['bg'] ) . ';'
		         . 'color:' . esc_attr( $palette[ $theme ]['text'] ) . ';"';
	}

	return '<span class="jhub-badge jhub-color-' . esc_attr( $color ) . '"' . $style . '>'
	       . esc_html( $item['name'] )
	       . '</span>';
}
