<?php
/**
 * public/views/layout-board.php
 *
 * Board (kanban) layout — columns grouped by a select property.
 * Fully dynamic in v4.0; group-by field is admin-configured.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array
 *   $disp              array
 *   $use_notion_colors bool
 *   $theme             string
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$props    = jetx_hub_property_defs();

// Resolve the group-by field. Fall back to first select field if not configured.
$group_by = $disp['board_group_by'] ?? '';
if ( empty( $group_by ) || ! isset( $props[ $group_by ] ) ) {
	foreach ( $props as $k => $def ) {
		if ( $def['type'] === 'select' && ! $def['internal'] ) {
			$group_by = $k;
			break;
		}
	}
}

// Find one additional select field for the card-meta badge (skip the group_by field).
$meta_badge_key = null;
foreach ( $props as $k => $def ) {
	if ( $k === $group_by ) continue;
	if ( $def['type'] === 'select' && ! $def['internal'] ) {
		$meta_badge_key = $k;
		break;
	}
}

// Group items by the chosen property value.
$groups = [];
foreach ( $items as $item ) {
	$prop_val = $item[ $group_by ] ?? '';
	$label    = is_array( $prop_val ) ? ( $prop_val['name'] ?? '—' ) : ( $prop_val ?: '—' );
	$groups[ $label ][] = $item;
}
ksort( $groups );
?>

<div class="jhub-board" id="jhub-table">
	<?php foreach ( $groups as $group_name => $group_items ) :
		// Use the first item's color for the group header badge.
		$color_src  = $group_items[0][ $group_by ] ?? [];
		$badge_item = is_array( $color_src ) && isset( $color_src['name'] )
			? $color_src
			: [ 'name' => $group_name, 'color' => 'default' ];
	?>
	<div class="jhub-board-col">

		<div class="jhub-board-head">
			<?php echo j