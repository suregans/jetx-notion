<?php
/**
 * public/views/layout-table.php
 *
 * Table layout renderer.
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
			?>
				<td class="jhub-td-<?php echo esc_attr( $key ); ?>">
				<?php
				switch ( $key ) {

					case 'name':
						echo jetx_hub_name_cell( $item, $disp, $use_notion_colors, $theme );
						break;

					case 'platform':
					case 'capability_tags':
					case 'jetx_use_case':
						foreach ( $item[ $key ] ?? [] as $v ) {
							echo jetx_hub_badge( $v, $use_notion_colors, $theme );
						}
						break;

					case 'summary':
					case 'why_it_matters':
					case 'publisher':
						echo esc_html( mb_strimwidth( $item[ $key ] ?? '', 0, 100, '…' ) );
						break;

					case 'official_url':
					case 'github_repo':
						if ( ! empty( $item[ $key ] ) ) {
							echo '<a href="' . esc_url( $item[ $key ] ) . '" '
							   . 'target="_blank" rel="noopener">↗ Link</a>';
						}
						break;

					default:
						$val = $item[ $key ] ?? '';
						if ( is_array( $val ) && isset( $val['name'] ) ) {
							echo jetx_hub_badge( $val, $use_notion_colors, $theme );
						} elseif ( is_array( $val ) ) {
							echo esc_html( implode( ', ', array_column( $val, 'name' ) ) );
						} else {
							echo esc_html( $val );
						}
						break;
				}
				?>
				</td>
			<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div><!-- .jhub-table-wrap -->
