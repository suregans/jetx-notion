<?php
/**
 * includes/shortcode.php
 *
 * Registers [jetx_ai_hub] and orchestrates the frontend render pipeline.
 * Helper functions (badge, name cell, row attrs) used by all layout templates.
 *
 * Layout templates live in public/views/layout-*.php
 * Controls template lives in public/views/controls.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'jetx_ai_hub', 'jetx_hub_shortcode' );

/**
 * Shortcode callback.
 *
 * Supported attributes:
 *   limit         (int)    — Override items_limit from Display settings. 0 = no override.
 *   category      (string) — Pre-filter to a specific category name.
 *   show_internal (bool)   — Show internal columns. Only honoured for manage_options users.
 *
 * @param  array $atts  Shortcode attributes.
 * @return string       HTML output (buffered).
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

	// Security: show_internal is only honoured for WP admins.
	$show_internal = filter_var( $atts['show_internal'], FILTER_VALIDATE_BOOLEAN )
	                 && current_user_can( 'manage_options' );

	$data = jetx_hub_fetch_data();

	// ── Error states ──────────────────────────────────────────────────────────
	if ( isset( $data['error'] ) ) {
		if ( $data['error'] === 'no_token' && current_user_can( 'manage_options' ) ) {
			return '<p style="color:#f87171;font-style:italic;">⚠️ JetX AI Hub: No Notion token configured. '
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

	// ── Load settings ─────────────────────────────────────────────────────────
	$disp     = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );
	$cols_cfg = get_option( 'jetx_hub_columns', jetx_hub_default_columns() );
	usort( $cols_cfg, fn( $a, $b ) => $a['order'] <=> $b['order'] );

	$items = $data;

	// Shortcode category override (pre-filters before display limit).
	if ( ! empty( $atts['category'] ) ) {
		$fc    = strtolower( trim( $atts['category'] ) );
		$items = array_values(
			array_filter( $items, fn( $i ) => strtolower( $i['category']['name'] ?? '' ) === $fc )
		);
	}

	// Items limit (shortcode attribute takes precedence over admin setting).
	$limit = (int) $atts['limit'] > 0 ? (int) $atts['limit'] : (int) $disp['items_limit'];
	if ( $limit > 0 ) {
		$items = array_slice( $items, 0, $limit );
	}

	// Unique sorted category list for the filter dropdown.
	$categories = array_unique(
		array_filter( array_map( fn( $i ) => $i['category']['name'] ?? '', $items ) )
	);
	sort( $categories );

	$layout           = $disp['layout']  ?? 'table';
	$theme            = $disp['theme']   ?? 'dark';
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

	// ── Render ────────────────────────────────────────────────────────────────
	ob_start();

	echo '<div class="jetx-hub-wrap jhub-theme-' . esc_attr( $theme ) . '" '
	     . 'id="jetx-ai-hub" '
	     . 'data-layout="' . esc_attr( $layout ) . '">';

	// Controls bar (search + filter dropdowns).
	if ( $disp['show_search'] || $disp['show_filters'] ) {
		require JETX_HUB_PATH . 'public/views/controls.php';
	}

	// Layout renderer.
	switch ( $layout ) {
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

	echo '<div class="jhub-no-results" id="jhub-no-results" style="display:none;" role="status">'
	     . 'No results. '
	     . '<button class="jhub-reset-btn" id="jhub-reset" type="button">Clear filters</button>'
	     . '</div>';

	if ( $disp['show_footer'] ) {
		echo '<div class="jhub-footer">Data synced from '
		     . '<a href="' . esc_url( jetx_hub_notion_url() ) . '" target="_blank" rel="noopener">Notion</a>'
		     . ' &middot; Curated by '
		     . '<a href="' . esc_url( JETX_HUB_BRANDING_URL ) . '" target="_blank" rel="noopener">'
		     . esc_html( JETX_HUB_BRANDING_NAME ) . '</a></div>';
	}

	echo '</div>'; // .jetx-hub-wrap

	return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────────────────────
// HELPER FUNCTIONS  (shared by all layout templates)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render a single badge (select or multi_select item) with optional Notion color.
 *
 * @param  array  $item              ['name' => string, 'color' => string]
 * @param  bool   $use_notion_colors Whether to apply Notion color styles.
 * @param  string $theme             'dark' | 'light'
 * @return string HTML span element.
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

/**
 * Render the name/tool cell HTML (shared across Table, Gallery, List, Board layouts).
 *
 * @param  array  $item
 * @param  array  $disp              Display settings array.
 * @param  bool   $use_notion_colors
 * @param  string $theme             'dark' | 'light'
 * @return string HTML string.
 */
function jetx_hub_name_cell( array $item, array $disp, bool $use_notion_colors, string $theme ): string {
	$out = '<div class="jhub-name">';

	if ( ! empty( $item['official_url'] ) ) {
		$out .= '<a href="' . esc_url( $item['official_url'] ) . '" '
		        . 'target="_blank" rel="noopener noreferrer">'
		        . esc_html( $item['name'] )
		        . ' <span class="jhub-ext" aria-hidden="true">↗</span></a>';
	} else {
		$out .= '<span>' . esc_html( $item['name'] ) . '</span>';
	}

	$out .= '</div>';

	if ( ! empty( $item['publisher'] ) ) {
		$out .= '<div class="jhub-pub">' . esc_html( $item['publisher'] ) . '</div>';
	}

	if ( $disp['show_summary'] && ! empty( $item['summary'] ) ) {
		$out .= '<div class="jhub-sum">'
		        . esc_html( mb_strimwidth( $item['summary'], 0, 140, '…' ) )
		        . '</div>';
	}

	if ( $disp['show_tags'] && ! empty( $item['capability_tags'] ) ) {
		$out .= '<div class="jhub-tags">';
		foreach ( $item['capability_tags'] as $tag ) {
			$out .= jetx_hub_badge( $tag, $use_notion_colors, $theme );
		}
		$out .= '</div>';
	}

	if ( $disp['show_github'] && ! empty( $item['github_repo'] ) ) {
		$out .= '<a href="' . esc_url( $item['github_repo'] ) . '" '
		        . 'class="jhub-gh" target="_blank" rel="noopener noreferrer">⭐ GitHub</a>';
	}

	return $out;
}

/**
 * Build the data-* attributes string for a row element (used by frontend.js filtering).
 *
 * @param  array $item
 * @return string HTML attributes string (not wrapped in element tags).
 */
function jetx_hub_row_attrs( array $item ): string {
	$cat  = $item['category']['name']  ?? '';
	$st   = $item['status']['name']    ?? '';
	$pr   = $item['pricing']['name']   ?? '';
	$tr   = $item['traction']['name']  ?? '';
	$blob = strtolower( implode( ' ', array_filter( [
		$item['name'],
		$cat,
		$item['sub_category']['name'] ?? '',
		$item['publisher'],
		$item['summary'],
		implode( ' ', array_column( $item['capability_tags'], 'name' ) ),
		implode( ' ', array_column( $item['platform'],        'name' ) ),
	] ) ) );

	return 'class="jhub-row"'
	       . ' data-search="'   . esc_attr( $blob ) . '"'
	       . ' data-category="' . esc_attr( $cat  ) . '"'
	       . ' data-status="'   . esc_attr( $st   ) . '"'
	       . ' data-pricing="'  . esc_attr( $pr   ) . '"'
	       . ' data-traction="' . esc_attr( $tr   ) . '"';
}
