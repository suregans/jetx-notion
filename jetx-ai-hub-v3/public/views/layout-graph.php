<?php
/**
 * public/views/layout-graph.php
 *
 * Graph (network) layout — Sigma.js v2 + graphology.
 * Three-level hierarchy: Category → Sub-category → Tool.
 *
 * The graph data is serialized as JSON into a <script> tag so graph.js
 * can initialize Sigma without any Ajax calls.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array   Parsed Notion rows
 *   $disp              array   Display settings (includes graph_category_field, graph_sub_category_field)
 *   $use_notion_colors bool
 *   $theme             string  'dark' | 'light'
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$props       = jetx_hub_property_defs();
$cat_key     = $disp['graph_category_field']     ?? '';
$sub_key     = $disp['graph_sub_category_field'] ?? '';
$title_key   = jetx_hub_title_field_key();
$url_key     = jetx_hub_primary_url_field_key();

// Fallback: auto-pick first and second select fields if not configured.
if ( empty( $cat_key ) || ! isset( $props[ $cat_key ] ) ) {
	foreach ( $props as $k => $def ) {
		if ( $def['type'] === 'select' && ! $def['internal'] ) {
			$cat_key = $k;
			break;
		}
	}
}
if ( empty( $sub_key ) || ! isset( $props[ $sub_key ] ) || $sub_key === $cat_key ) {
	foreach ( $props as $k => $def ) {
		if ( $k === $cat_key ) continue;
		if ( $def['type'] === 'select' && ! $def['internal'] ) {
			$sub_key = $k;
			break;
		}
	}
}

// ── Build graph data ──────────────────────────────────────────────────────────

$graph_nodes = []; // id → {label, type, color, size, url}
$graph_edges = []; // [{source, target}]

$node_ids     = []; // label → node_id (for dedup)
$node_counter = 0;

$mk_node = function( string $label, string $type, string $color ) use ( &$graph_nodes, &$node_ids, &$node_counter ): string {
	$cache_key = $type . '::' . $label;
	if ( isset( $node_ids[ $cache_key ] ) ) return $node_ids[ $cache_key ];

	$id   = 'n' . ( ++$node_counter );
	$size = match( $type ) {
		'category'    => 22,
		'subcategory' => 14,
		default       => 8,
	};

	$graph_nodes[ $id ] = [
		'id'    => $id,
		'label' => $label,
		'type'  => $type,
		'size'  => $size,
		'color' => $color,
		'url'   => '',
	];
	$node_ids[ $cache_key ] = $id;
	return $id;
};

$mk_edge = function( string $src, string $tgt ) use ( &$graph_edges ): void {
	$eid = 'e_' . $src . '_' . $tgt;
	if ( ! isset( $graph_edges[ $eid ] ) ) {
		$graph_edges[ $eid ] = [ 'id' => $eid, 'source' => $src, 'target' => $tgt ];
	}
};

// Node color palette.
$cat_colors = [ '#60a5fa','#a78bfa','#34d399','#fb923c','#f472b6','#fbbf24','#4ade80','#f87171','#38bdf8','#c084fc' ];
$sub_colors = [ '#93c5fd','#c4b5fd','#6ee7b7','#fdba74','#f9a8d4','#fde68a','#86efac','#fca5a5','#7dd3fc','#d8b4fe' ];
$cat_color_map = [];
$sub_color_map = [];
$cat_ci        = 0;
$sub_ci        = 0;

foreach ( $items as $item ) {
	$title = is_array( $item[ $title_key ] ?? '' )
		? ( $item[ $title_key ]['name'] ?? '' )
		: ( $item[ $title_key ] ?? '' );
	if ( empty( $title ) ) continue;

	$cat_val = $item[ $cat_key ] ?? null;
	$cat_name = is_array( $cat_val ) ? ( $cat_val['name'] ?? '' ) : (string) $cat_val;

	$sub_val = $sub_key ? ( $item[ $sub_key ] ?? null ) : null;
	$sub_name = $sub_val ? ( is_array( $sub_val ) ? ( $sub_val['name'] ?? '' ) : (string) $sub_val ) : '';

	$item_url = $url_key ? ( $item[ $url_key ] ?? '' ) : '';
	if ( is_array( $item_url ) ) $item_url = $item_url['name'] ?? '';

	// Assign consistent colours per category/sub-category name.
	if ( $cat_name && ! isset( $cat_color_map[ $cat_name ] ) ) {
		$cat_color_map[ $cat_name ] = $cat_colors[ $cat_ci % count( $cat_colors ) ];
		$cat_ci++;
	}
	if ( $sub_name && ! isset( $sub_color_map[ $sub_name ] ) ) {
		$sub_color_map[ $sub_name ] = $sub_colors[ $sub_ci % count( $sub_colors ) ];
		$sub_ci++;
	}

	$cat_color  = $cat_name ? ( $cat_color_map[ $cat_name ] ?? '#60a5fa' ) : '#94a3b8';
	$sub_color  = $sub_name ? ( $sub_color_map[ $sub_name ] ?? '#93c5fd' ) : '#94a3b8';
	$tool_color = $theme === 'light' ? '#64748b' : '#94a3b8';

	// Category node.
	$cat_id = $cat_name ? $mk_node( $cat_name, 'category', $cat_color ) : null;

	// Sub-category node.
	$sub_id = $sub_name ? $mk_node( $sub_name, 'subcategory', $sub_color ) : null;

	// Tool node.
	$tool_id = $mk_node( $title, 'tool', $tool_color );
	$graph_nodes[ $tool_id ]['url'] = $item_url;

	// Edges: cat → sub → tool (or cat → tool if no sub).
	if ( $cat_id && $sub_id )  $mk_edge( $cat_id, $sub_id );
	if ( $sub_id )             $mk_edge( $sub_id, $tool_id );
	elseif ( $cat_id )         $mk_edge( $cat_id, $tool_id );
}

$graph_data = [
	'nodes'  => array_values( $graph_nodes ),
	'edges'  => array_values( $graph_edges ),
	'theme'  => $theme,
	'cat_key' => $cat_key,
	'sub_key' => $sub_key,
];
?>

<div class="jhub-graph-wrap" id="jhub-graph-wrap">
	<div id="jhub-sigma-container" class="jhub-sigma-container"></div>

	<div class="jhub-graph-legend" id="jhub-graph-legend">
		<div class="jhub-legend-item">
			<span class="jhub-legend-dot jhub-legend-cat"></span> Category
		</div>
		<div class="jhub-legend-item">
			<span class="jhub-legend-dot jhub-legend-sub"></span> Sub-category
		</div>
		<div class="jhub-legend-item">
			<span class="jhub-legend-dot jhub-legend-tool"></span> Tool
		</div>
	</div>

	<div class="jhub-graph-hint">
		Scroll to zoom &nbsp;·&nbsp; Drag to pan &nbsp;·&nbsp; Click a tool node to open its page
	</div>
</div>

<script id="jhub-graph-data" type="application/json">
<?php echo wp_json_encode( $graph_data ); ?>
</script>
