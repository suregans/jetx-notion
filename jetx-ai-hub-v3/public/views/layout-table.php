<?php
/**
 * public/views/layout-table.php
 *
 * Table layout renderer — fully dynamic in v4.0.
 * Cell rendering is type-based; no hardcoded Notion field key strings.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array   Parsed Notion rows
 *   $disp              array   Display settings
 *   $cols_cfg          array   Sorted column config (key, label, visible, order)
 *   $use_notion_colors bool
 *   $show_internal     bool
 *   $theme             string  'dark' | 'light'
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$props   = jetx_hub_property_defs();
$visible = array_filter( $cols_cfg, fn( $c ) => $c['visible'] );
?>

<div class="jhub-table-wrap" role="region" aria-label="AI Intelligence Hub">
	<table class="jhub-table" id="jhub-table">
		<thead>
			<tr>
			<?php foreach ( $visible as $col ) :
				$def = $props[ $col['key'] ] ?? null;
				if ( $def && $def['internal'] && ! $show_internal ) continue;
			?>
				<th scope="col" class="jhub-col-<?php echo esc_attr( $col['key'] ); ?>">
					<?php echo esc_html( $col['label'] ); ?>
				</th>
			<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $items as $item ) : ?>
			<tr <?php echo jetx_hub_row_attrs( $item ); ?>>
			<?php foreach ( $visible as $col ) :
				$key = $col['key'];
				$def = $props[ $key ] ?? null;
				if ( $def && $def['internal'] && ! $show_internal ) continue;
				$type = $def['type'] ?? 'rich_text';
				$val  = $item[ $key ] ?? '';
			?>
				<td class="jhub-td-<?php echo esc_attr( $key ); ?>">
				<?php
				if ( $def && $def['type'] === 'title' ) {
					echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme );
				} else {
					echo jetx_hub_render_cell( $val, $type, $use_notion_colors, $theme );
				}
				?>
				</td>
			<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div><!-- .jhub-table-wrap -->
