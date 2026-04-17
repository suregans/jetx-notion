<?php
/**
 * admin/views/tab-schema.php
 *
 * Schema / Field Mapping tab.
 *
 * Allows admins to map each internal PHP field key to the exact Notion property
 * name in their database, choose the property type, and mark fields as
 * admin-only — all without editing any PHP file.
 *
 * Saving auto-flushes the cache because field-name changes affect the API query
 * structure and the parsed row shape.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$props = jetx_hub_property_defs();

$type_options = [
	'title'        => 'Title',
	'rich_text'    => 'Rich Text',
	'select'       => 'Select',
	'multi_select' => 'Multi-select',
	'url'          => 'URL',
	'date'         => 'Date',
];

$type_hints = [
	'title'        => 'The primary name/title column of your database.',
	'rich_text'    => 'A plain-text paragraph or description field.',
	'select'       => 'A single-choice dropdown. Supports Notion badge colors.',
	'multi_select' => 'A multi-choice tag field. Supports Notion badge colors.',
	'url'          => 'A link / URL field.',
	'date'         => 'A date or date-range field.',
];
?>

<p class="jhub-tab-intro">
	Map each internal field key to the <strong>exact property name</strong> in your Notion database
	(names are case-sensitive). Change the type if your database uses a different column type.
	Tick <em>Admin Only</em> to hide a field from the public-facing output.
	<strong>Saving this form automatically flushes the cache.</strong>
</p>

<form method="post" action="">
	<?php wp_nonce_field( 'jetx_save_schema', 'jetx_save_schema_nonce' ); ?>
	<input type="hidden" name="jetx_save_schema" value="1">

	<table class="wp-list-table widefat fixed striped jhub-schema-table">
		<thead>
			<tr>
				<th class="jhub-schema-col-key">Internal Key</th>
				<th class="jhub-schema-col-label">Field Purpose</th>
				<th class="jhub-schema-col-notion">Notion Property Name <span class="jhub-required">*</span></th>
				<th class="jhub-schema-col-type">Type</th>
				<th class="jhub-schema-col-internal">Admin Only</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $props as $key => $def ) : ?>
			<tr>
				<td><code><?php echo esc_html( $key ); ?></code></td>
				<td class="jhub-schema-label-cell"><?php echo esc_html( $def['label'] ); ?></td>
				<td>
					<input
						type="text"
						name="schema[<?php echo esc_attr( $key ); ?>][notion]"
						value="<?php echo esc_attr( $def['notion'] ); ?>"
						class="regular-text"
						placeholder="e.g. <?php echo esc_attr( $def['notion'] ); ?>">
				</td>
				<td>
					<select name="schema[<?php echo esc_attr( $key ); ?>][type]" class="jhub-schema-type-select" title="<?php echo esc_attr( $type_hints[ $def['type'] ] ?? '' ); ?>">
						<?php foreach ( $type_options as $t_val => $t_label ) : ?>
						<option value="<?php echo esc_attr( $t_val ); ?>"<?php selected( $def['type'], $t_val ); ?>>
							<?php echo esc_html( $t_label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td class="jhub-schema-check-cell">
					<input
						type="checkbox"
						name="schema[<?php echo esc_attr( $key ); ?>][internal]"
						value="1"
						<?php checked( $def['internal'] ); ?>>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description jhub-schema-note">
		<strong>*</strong> Notion property names are case-sensitive and must match exactly what appears
		in your Notion database column headers. After saving, visit the
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection' ) ); ?>">Connection tab</a>
		and click <em>Refresh Now</em> to verify the data loads correctly.
	</p>

	<p class="submit">
		<?php submit_button( 'Save Schema', 'primary', 'submit', false ); ?>
	</p>
</form>

<hr class="jhub-hr">

<h3>Type Reference</h3>
<table class="widefat jhub-schema-ref-table">
	<thead>
		<tr><th>Type</th><th>When to use</th></tr>
	</thead>
	<tbody>
		<?php foreach ( $type_hints as $t => $hint ) : ?>
		<tr>
			<td><code><?php echo esc_html( $t ); ?></code></td>
			<td><?php echo esc_html( $hint ); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
