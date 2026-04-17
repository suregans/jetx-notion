<?php
/**
 * public/views/layout-board.php
 *
 * Board (kanban) layout — columns grouped by a select property.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array
 *   $disp              array
 *   $use_notion_colors bool
 *   $theme             string
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$group_by = $disp['board_group_by'] ?? 'category';

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
		$color_src = $group_items[0][ $group_by ] ?? [];
		$badge_item = is_array( $color_src )
			? $color_src
			: [ 'name' => $group_name, 'color' => 'default' ];
	?>
	<div class="jhub-board-col">

		<div class="jhub-board-head">
			<?php echo jetx_hub_badge( $badge_item, $use_notion_colors, $theme ); ?>
			<span class="jhub-board-count">(<?php echo count( $group_items ); ?>)</span>
		</div>

		<?php foreach ( $group_items as $item ) : ?>
		<div <?php echo jetx_hub_row_attrs( $item ); ?> class="jhub-row jhub-board-card">

			<?php echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme ); ?>

			<div class="jhub-board-card-meta">
				<?php echo jetx_hub_badge( $item['status']  ?? [], $use_notion_colors, $theme ); ?>
				<?php echo jetx_hub_badge( $item['pricing'] ?? [], $use_notion_colors, $theme ); ?>
			</div>

		</div>
		<?php endforeach; ?>

	</div>
	<?php endforeach; ?>
</div><!-- .jhub-board -->
