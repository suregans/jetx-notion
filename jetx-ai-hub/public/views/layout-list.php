<?php
/**
 * public/views/layout-list.php
 *
 * List layout — compact flex rows.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array
 *   $disp              array
 *   $use_notion_colors bool
 *   $theme             string
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="jhub-list" id="jhub-table">
	<?php foreach ( $items as $item ) :
		$cat = $item['category'] ?? [];
		$st  = $item['status']   ?? [];
		$pr  = $item['pricing']  ?? [];
	?>
	<div <?php echo jetx_hub_row_attrs( $item ); ?> class="jhub-row jhub-list-item">

		<div class="jhub-list-main">
			<?php echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme ); ?>
		</div>

		<div class="jhub-list-meta">
			<?php echo jetx_hub_badge( $cat, $use_notion_colors, $theme ); ?>
			<?php echo jetx_hub_badge( $st,  $use_notion_colors, $theme ); ?>
			<?php if ( ! empty( $pr['name'] ) ) : ?>
			<?php echo jetx_hub_badge( $pr, $use_notion_colors, $theme ); ?>
			<?php endif; ?>
		</div>

	</div>
	<?php endforeach; ?>
</div><!-- .jhub-list -->
