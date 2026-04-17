<?php
/**
 * public/views/layout-list.php
 *
 * List layout — compact flex rows. Fully dynamic in v4.0.
 * Meta badges use the first three active select fields in the schema.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array
 *   $disp              array
 *   $use_notion_colors bool
 *   $theme             string
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Find up to three select fields for the row meta badges.
$props       = jetx_hub_property_defs();
$select_keys = [];
foreach ( $props as $k => $def ) {
	if ( in_array( $def['type'], [ 'select', 'multi_select' ], true ) && ! $def['internal'] ) {
		$select_keys[] = $k;
		if ( count( $select_keys ) >= 3 ) break;
	}
}
?>

<div class="jhub-list" id="jhub-table">
	<?php foreach ( $items as $item ) : ?>
	<div <?php echo jetx_hub_row_attrs( $item ); ?> class="jhub-row jhub-list-item">

		<div class="jhub-list-main">
			<?php echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme ); ?>
		</div>

		<div class="jhub-list-meta">
			<?php foreach ( $select_keys as $sk ) :
				$val = $item[ $sk ] ?? [];
				// multi_select: show first item only in list meta.
				if ( is_array( $val ) && isset( $val[0] ) ) $val = $val[0];
				if ( ! empty( $val['name'] ) ) {
					echo jetx_hub_badge( $val, $use_notion_colors, $theme );
				}
			endforeach; ?>
		</div>

	</div>
	<?php endforeach; ?>
</div><!-- .jhub-list -->
