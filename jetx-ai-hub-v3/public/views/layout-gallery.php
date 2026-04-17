<?php
/**
 * public/views/layout-gallery.php
 *
 * Gallery layout — CSS grid of cards.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array
 *   $disp              array
 *   $use_notion_colors bool
 *   $theme             string
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="jhub-gallery" id="jhub-table">
	<?php foreach ( $items as $item ) :
		$cat = $item['category'] ?? [];
		$st  = $item['status']   ?? [];
		$pr  = $item['pricing']  ?? [];
		$tr  = $item['traction'] ?? [];
	?>
	<div <?php echo jetx_hub_row_attrs( $item ); ?> class="jhub-row jhub-card">

		<div class="jhub-card-head">
			<?php echo jetx_hub_badge( $cat, $use_notion_colors, $theme ); ?>
			<?php echo jetx_hub_badge( $st,  $use_notion_colors, $theme ); ?>
		</div>

		<?php echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme ); ?>

		<div class="jhub-card-foot">
			<?php echo jetx_hub_badge( $pr, $use_notion_colors, $theme ); ?>
			<?php if ( ! empty( $tr['name'] ) ) : ?>
			<span class="jhub-era"><?php echo esc_html( $tr['name'] ); ?></span>
			<?php endif; ?>
		</div>

	</div>
	<?php endforeach; ?>
</div><!-- .jhub-gallery -->
