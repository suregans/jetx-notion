<?php
/**
 * admin/views/tab-columns.php
 *
 * Tab 2 — Column manager: drag-to-reorder, show/hide, custom labels.
 * jQuery UI Sortable is enqueued by admin/admin.php (admin_enqueue_scripts).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Merge saved columns with any newly added default columns.
$saved      = get_option( 'jetx_hub_columns', jetx_hub_default_columns() );
$defaults   = jetx_hub_default_columns();
$saved_keys = array_column( $saved, 'key' );

foreach ( $defaults as $default_col ) {
	if ( ! in_array( $default_col['key'], $saved_keys, true ) ) {
		$saved[] = $default_col;
	}
}

usort( $saved, fn( $a, $b ) => $a['order'] <=> $b['order'] );

$props = jetx_hub_property_defs();
?>

<p class="jhub-tab-intro">
	Drag rows to reorder. Toggle the checkbox to show or hide a column.
	Rename the header label in the text field.
	Columns marked 🔒 are internal — only shown when <code>show_internal="true"</code>
	is passed to the shortcode by a WordPress admin.
</p>

<form method="post" style="max-width:780px;">
	<?php wp_nonce_field( 'jetx_save_columns' ); ?>
	<input type="hidden" name="jetx_save_columns" value="1">

	<table class="wp-list-table widefat fixed striped" id="jhub-col-table">
		<thead>
			<tr>
				<th style="width:32px;"></th>
				<th style="width:56px;">Show</th>
				<th style="width:160px;">Column Key</th>
				<th>Header Label</th>
				<th style="width:100px;">Type</th>
				<th style="width:80px;">Access</th>
			</tr>
		</thead>
		<tbody id="jhub-sortable">
		<?php foreach ( $saved as $col ) :
			$def      = $props[ $col['key'] ] ?? null;
			$internal = $def ? $def['internal'] : false;
			$type     = $def ? $def['type']     : '—';
		?>
			<tr style="cursor:move;">
				<td style="vertical-align:middle;">
					<span class="jhub-drag-handle" title="Drag to reorder">⠿</span>
					<input type="hidden" name="col_order[]" value="<?php echo esc_attr( $col['key'] ); ?>">
				</td>
				<td style="vertical-align:middle;text-align:center;">
					<input type="checkbox"
					       name="col_visible[]"
					       value="<?php echo esc_attr( $col['key'] ); ?>"
					       <?php checked( $col['visible'] ); ?>>
				</td>
				<td style="vertical-align:middle;">
					<code><?php echo esc_html( $col['key'] ); ?></code>
				</td>
				<td style="vertical-align:middle;">
					<input type="text"
					       name="col_label[<?php echo esc_attr( $col['key'] ); ?>]"
					       value="<?php echo esc_attr( $col['label'] ); ?>"
					       class="regular-text"
					       style="width:100%;">
				</td>
				<td style="vertical-align:middle;color:#888;font-size:12px;">
					<?php echo esc_html( $type ); ?>
				</td>
				<td style="vertical-align:middle;font-size:12px;">
					<?php echo $internal ? '🔒 Admin' : '🌐 Public'; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description" style="margin-top:8px;">
		Note: <em>name</em>, <em>summary</em>, <em>official_url</em>, <em>github_repo</em>,
		and <em>capability_tags</em> are always rendered inside the Tool/Model cell
		regardless of column visibility — they are not standalone columns.
	</p>

	<?php submit_button( 'Save Column Settings', 'primary', 'jetx_save_col_btn' ); ?>
</form>
