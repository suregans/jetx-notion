<?php
/**
 * admin/views/tab-settings.php
 *
 * Tab — ⚙️ Settings
 * Plugin branding, performance limits, and graph display configuration.
 * All values previously hardcoded in config.php are now editable here.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$s    = get_option( 'jetx_hub_settings', [] );
$props = jetx_hub_property_defs();

// Select-type fields (for graph dropdowns).
$select_fields = array_filter( $props, fn( $p ) => in_array( $p['type'], [ 'select', 'multi_select' ], true ) && ! $p['internal'] );

// Saved graph fields.
$disp = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );

// Helper: get setting with fallback to config.php constant.
$sv = fn( string $key, string $def = '' ): string => $s[ $key ] ?? $def;
?>

<form method="post" style="max-width:780px;">
	<?php wp_nonce_field( 'jetx_save_settings' ); ?>
	<input type="hidden" name="jetx_save_settings" value="1">

	<!-- ── Branding ──────────────────────────────────────────────────── -->
	<h2>Branding</h2>
	<p class="jhub-tab-intro">
		These values replace the constants that were previously hardcoded in
		<code>config.php</code>. They appear in the admin menu, page headings,
		and the public footer. Leave a field blank to use the built-in default.
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="jhub_branding_name">Brand / Agency Name</label>
			</th>
			<td>
				<input
					type="text"
					id="jhub_branding_name"
					name="branding_name"
					value="<?php echo esc_attr( $sv( 'branding_name' ) ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr( JETX_HUB_DEFAULT_BRANDING_NAME ); ?>"
				>
				<p class="description">Shown in the footer "Curated by …" link.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_branding_url">Brand URL</label>
			</th>
			<td>
				<input
					type="url"
					id="jhub_branding_url"
					name="branding_url"
					value="<?php echo esc_attr( $sv( 'branding_url' ) ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr( JETX_HUB_DEFAULT_BRANDING_URL ); ?>"
				>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_admin_title">Admin Page Heading</label>
			</th>
			<td>
				<input
					type="text"
					id="jhub_admin_title"
					name="admin_title"
					value="<?php echo esc_attr( $sv( 'admin_title' ) ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr( JETX_HUB_DEFAULT_ADMIN_TITLE ); ?>"
				>
				<p class="description">Shown at the top of the WP Admin settings page.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_menu_label">Settings Menu Label</label>
			</th>
			<td>
				<input
					type="text"
					id="jhub_menu_label"
					name="menu_label"
					value="<?php echo esc_attr( $sv( 'menu_label' ) ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr( JETX_HUB_DEFAULT_MENU_LABEL ); ?>"
				>
				<p class="description">
					Label shown in the Settings sub-menu.
					<strong>Note:</strong> A menu label change takes effect on the next page load.
				</p>
			</td>
		</tr>
	</table>

	<hr class="jhub-hr">

	<!-- ── Performance ───────────────────────────────────────────────── -->
	<h2>Performance</h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="jhub_max_pages">Max API Pages</label>
			</th>
			<td>
				<input
					type="number"
					id="jhub_max_pages"
					name="max_pages"
					value="<?php echo esc_attr( $sv( 'max_pages', (string) JETX_HUB_DEFAULT_MAX_PAGES ) ); ?>"
					min="1"
					max="100"
					class="small-text"
				>
				<span> pages × 100 rows = up to
					<?php echo esc_html( absint( $sv( 'max_pages', (string) JETX_HUB_DEFAULT_MAX_PAGES ) ) * 100 ); ?>
					entries
				</span>
				<p class="description">
					Maximum number of Notion API pages fetched per cache refresh. Each page returns 100 rows.
					Raise this for large databases. Default: <?php echo esc_html( JETX_HUB_DEFAULT_MAX_PAGES ); ?>.
				</p>
			</td>
		</tr>
	</table>

	<hr class="jhub-hr">

	<!-- ── Graph View Configuration ──────────────────────────────────── -->
	<h2>Graph View Configuration</h2>
	<p class="jhub-tab-intro">
		The Graph View builds a three-level network: <strong>Category → Sub-category → Tool</strong>.
		Select which Notion fields map to each level. Only select-type fields are listed.
		The tool name is always taken from the <strong>title</strong> field.
	</p>

	<?php if ( empty( $select_fields ) ) : ?>
	<div class="notice notice-warning inline">
		<p>No select-type fields detected yet. Go to the
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=jetx-ai-hub&tab=fields' ) ); ?>">🔍 Fields tab</a>
			and click "Detect Fields from Notion" first.
		</p>
	</div>
	<?php else : ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="jhub_graph_cat">Category Field</label>
			</th>
			<td>
				<select id="jhub_graph_cat" name="graph_category_field">
					<option value="">— None —</option>
					<?php foreach ( $select_fields as $key => $def ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"
					        <?php selected( $disp['graph_category_field'], $key ); ?>>
						<?php echo esc_html( $def['notion'] . ' (' . $def['type'] . ')' ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<p class="description">Top-level graph nodes (e.g. "Category").</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_graph_sub">Sub-category Field</label>
			</th>
			<td>
				<select id="jhub_graph_sub" name="graph_sub_category_field">
					<option value="">— None —</option>
					<?php foreach ( $select_fields as $key => $def ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"
					        <?php selected( $disp['graph_sub_category_field'], $key ); ?>>
						<?php echo esc_html( $def['notion'] . ' (' . $def['type'] . ')' ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<p class="description">Mid-level graph nodes (e.g. "Sub-category"). Optional.</p>
			</td>
		</tr>
	</table>

	<?php endif; ?>

	<?php submit_button( 'Save Settings' ); ?>
</form>
