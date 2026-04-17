<?php
/**
 * admin/views/tab-filters.php
 *
 * Tab 3 — Filter rules + Sort rules.
 * Dynamic operator dropdowns are driven by admin.js (via wp_localize_script).
 * Template rows are rendered server-side via <template> tags; JS clones them.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$filters = get_option( 'jetx_hub_filters', [] );
$sorts   = get_option( 'jetx_hub_sorts', jetx_hub_default_sorts() );
$props   = jetx_hub_property_defs();

// Operator map (mirrors jetxHubAdmin.filterOperators set in admin.php enqueue).
$filter_operators = [
	'select'       => [ 'equals' => 'is', 'does_not_equal' => 'is not', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
	'multi_select' => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
	'title'        => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
	'rich_text'    => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'equals' => 'equals', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
	'url'          => [ 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
	'date'         => [ 'after' => 'after', 'before' => 'before', 'on_or_after' => 'on or after', 'on_or_before' => 'on or before', 'is_empty' => 'is empty' ],
];
?>

<!-- ── FILTER RULES ──────────────────────────────────────────────────────── -->
<h2>Filter Rules</h2>
<p class="jhub-tab-intro" style="max-width:700px;">
	Filter rules are sent directly to the Notion API — only matching entries are fetched.
	Adding or removing rules automatically clears the local cache.
	All rules use <strong>AND</strong> logic.
</p>

<form method="post" style="max-width:820px;">
	<?php wp_nonce_field( 'jetx_save_filters' ); ?>
	<input type="hidden" name="jetx_save_filters" value="1">

	<table class="wp-list-table widefat" id="jhub-filter-table">
		<thead>
			<tr>
				<th>Property</th>
				<th style="width:180px;">Operator</th>
				<th>Value</th>
				<th style="width:56px;"></th>
			</tr>
		</thead>
		<tbody id="jhub-filter-rows">
		<?php foreach ( $filters as $i => $f ) :
			$ptype = $props[ $f['property'] ]['type'] ?? 'select';
			$ops   = $filter_operators[ $ptype ] ?? $filter_operators['select'];
		?>
			<tr class="jhub-filter-row">
				<td>
					<select name="filters[<?php echo $i; ?>][property]"
					        class="jhub-prop-sel"
					        style="width:100%;">
						<option value="">— Select property —</option>
						<?php foreach ( $props as $key => $def ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"
						        <?php selected( $f['property'], $key ); ?>>
							<?php echo esc_html( $def['label'] . ' (' . $def['type'] . ')' ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<select name="filters[<?php echo $i; ?>][operator]"
					        class="jhub-op-sel"
					        style="width:100%;">
						<?php foreach ( $ops as $val => $lbl ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>"
						        <?php selected( $f['operator'], $val ); ?>>
							<?php echo esc_html( $lbl ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<input type="text"
					       name="filters[<?php echo $i; ?>][value]"
					       value="<?php echo esc_attr( $f['value'] ?? '' ); ?>"
					       style="width:100%;">
				</td>
				<td>
					<button type="button" class="button jhub-remove-row" aria-label="Remove rule">✕</button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="jhub-table-actions">
		<button type="button" class="button" id="jhub-add-filter">+ Add Filter Rule</button>
		<p class="description">
			Leave Value blank for "is empty" / "is not empty" operators.
		</p>
	</div>

	<?php submit_button( 'Save Filter Rules', 'primary', 'jhub_save_filter_btn', false ); ?>
</form>

<hr class="jhub-hr">

<!-- ── SORT RULES ───────────────────────────────────────────────────────── -->
<h2>Sort Rules</h2>
<p class="jhub-tab-intro" style="max-width:700px;">
	Sort rules are applied to the Notion API query in the order listed — the first rule
	has the highest priority.
</p>

<form method="post" style="max-width:600px;">
	<?php wp_nonce_field( 'jetx_save_sorts' ); ?>
	<input type="hidden" name="jetx_save_sorts" value="1">

	<table class="wp-list-table widefat" id="jhub-sort-table">
		<thead>
			<tr>
				<th>Property</th>
				<th style="width:160px;">Direction</th>
				<th style="width:56px;"></th>
			</tr>
		</thead>
		<tbody id="jhub-sort-rows">
		<?php foreach ( $sorts as $i => $s ) : ?>
			<tr class="jhub-sort-row">
				<td>
					<select name="sorts[<?php echo $i; ?>][property]" style="width:100%;">
						<option value="">— Select property —</option>
						<?php foreach ( $props as $key => $def ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"
						        <?php selected( $s['property'], $key ); ?>>
							<?php echo esc_html( $def['label'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<select name="sorts[<?php echo $i; ?>][direction]" style="width:100%;">
						<option value="descending" <?php selected( $s['direction'], 'descending' ); ?>>↓ Descending</option>
						<option value="ascending"  <?php selected( $s['direction'], 'ascending'  ); ?>>↑ Ascending</option>
					</select>
				</td>
				<td>
					<button type="button" class="button jhub-remove-row" aria-label="Remove rule">✕</button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="jhub-table-actions">
		<button type="button" class="button" id="jhub-add-sort">+ Add Sort Rule</button>
	</div>

	<?php submit_button( 'Save Sort Rules', 'primary', 'jhub_save_sort_btn', false ); ?>
</form>

<!-- ── HIDDEN TEMPLATE ROWS (cloned by admin.js) ────────────────────────── -->
<template id="jhub-filter-tpl">
	<tr class="jhub-filter-row">
		<td>
			<select name="filters[__IDX__][property]" class="jhub-prop-sel" style="width:100%;">
				<option value="">— Select property —</option>
				<?php foreach ( $props as $key => $def ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $def['label'] . ' (' . $def['type'] . ')' ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select name="filters[__IDX__][operator]" class="jhub-op-sel" style="width:100%;">
				<option value="contains">contains</option>
			</select>
		</td>
		<td>
			<input type="text" name="filters[__IDX__][value]" style="width:100%;">
		</td>
		<td>
			<button type="button" class="button jhub-remove-row" aria-label="Remove rule">✕</button>
		</td>
	</tr>
</template>

<template id="jhub-sort-tpl">
	<tr class="jhub-sort-row">
		<td>
			<select name="sorts[__IDX__][property]" style="width:100%;">
				<option value="">— Select property —</option>
				<?php foreach ( $props as $key => $def ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $def['label'] ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select name="sorts[__IDX__][direction]" style="width:100%;">
				<option value="descending">↓ Descending</option>
				<option value="ascending">↑ Ascending</option>
			</select>
		</td>
		<td>
			<button type="button" class="button jhub-remove-row" aria-label="Remove rule">✕</button>
		</td>
	</tr>
</template>
