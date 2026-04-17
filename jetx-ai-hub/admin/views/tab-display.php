<?php
/**
 * admin/views/tab-display.php
 *
 * Tab 4 — Display settings: layout type, theme, Notion colors, visibility toggles.
 * Board-row show/hide is handled by admin.js.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$d            = wp_parse_args( get_option( 'jetx_hub_display', [] ), jetx_hub_default_display() );
$props        = jetx_hub_property_defs();
$select_props = array_filter( $props, fn( $p ) => $p['type'] === 'select' && ! $p['internal'] );
?>

<form method="post" style="max-width:780px;">
	<?php wp_nonce_field( 'jetx_save_display' ); ?>
	<input type="hidden" name="jetx_save_display" value="1">

	<!-- ── Layout ──────────────────────────────────────────────────────── -->
	<h2>Layout</h2>
	<p class="jhub-tab-intro">
		<strong>Table</strong>, <strong>Gallery</strong>, <strong>List</strong>, and
		<strong>Board</strong> are built from Notion raw data.
		Notion's Timeline, Calendar, Chart, Map, and Dashboard views are not available —
		those are Notion's own frontend renderers and cannot be extracted via the API.
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">Layout type</th>
			<td>
				<?php
				$layouts = [
					'table'   => '📋 Table',
					'gallery' => '🖼 Gallery',
					'list'    => '📄 List',
					'board'   => '📌 Board',
				];
				foreach ( $layouts as $val => $lbl ) :
				?>
				<label style="margin-right:20px;">
					<input type="radio"
					       name="layout"
					       value="<?php echo esc_attr( $val ); ?>"
					       <?php checked( $d['layout'], $val ); ?>>
					<?php echo esc_html( $lbl ); ?>
				</label>
				<?php endforeach; ?>
				<p class="description">
					Board groups entries into kanban-style columns by the select property chosen below.
				</p>
			</td>
		</tr>

		<tr id="jhub-board-row" <?php echo $d['layout'] !== 'board' ? 'style="display:none;"' : ''; ?>>
			<th scope="row">
				<label for="jhub_group">Board — group by</label>
			</th>
			<td>
				<select id="jhub_group" name="board_group_by">
					<?php foreach ( $select_props as $key => $def ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"
					        <?php selected( $d['board_group_by'], $key ); ?>>
						<?php echo esc_html( $def['label'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row">Theme</th>
			<td>
				<label style="margin-right:20px;">
					<input type="radio" name="theme" value="dark"
					       <?php checked( $d['theme'], 'dark' ); ?>> 🌙 Dark
				</label>
				<label>
					<input type="radio" name="theme" value="light"
					       <?php checked( $d['theme'], 'light' ); ?>> ☀️ Light
				</label>
			</td>
		</tr>
	</table>

	<!-- ── Notion Colors ────────────────────────────────────────────────── -->
	<h2 style="margin-top:32px;">Notion Colors</h2>
	<p class="jhub-tab-intro">
		The Notion API returns the color for every select and multi-select tag.
		When enabled, badges are rendered with their exact Notion colors.
		When disabled, a uniform neutral style is used.
	</p>

	<div class="jhub-color-preview-box">
		<label style="font-weight:600;display:block;margin-bottom:8px;">
			<input type="checkbox"
			       name="use_notion_colors"
			       value="1"
			       <?php checked( $d['use_notion_colors'] ); ?>>
			Use Notion color-coded tag styling
		</label>
		<p class="description">
			Applies to Category, Status, Pricing, Traction, Platform, Capability Tags, and Era badges.
		</p>
		<!-- Color swatches preview -->
		<div class="jhub-color-swatches">
			<?php foreach ( jetx_hub_notion_colors() as $color => $vals ) : ?>
			<span style="background:<?php echo esc_attr( $vals['dark']['bg'] ); ?>;
			             color:<?php echo esc_attr( $vals['dark']['text'] ); ?>;
			             padding:3px 10px;border-radius:5px;font-size:12px;font-weight:500;">
				<?php echo esc_html( ucfirst( $color ) ); ?>
			</span>
			<?php endforeach; ?>
		</div>
		<p class="description" style="margin-top:6px;">Preview shown in dark theme. Light theme uses lighter backgrounds.</p>
	</div>

	<!-- ── Visible Elements ─────────────────────────────────────────────── -->
	<h2 style="margin-top:32px;">Visible Elements</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">Search bar</th>
			<td>
				<label>
					<input type="checkbox" name="show_search" value="1"
					       <?php checked( $d['show_search'] ); ?>>
					Show live search input above the layout
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">Filter dropdowns</th>
			<td>
				<label>
					<input type="checkbox" name="show_filters" value="1"
					       <?php checked( $d['show_filters'] ); ?>>
					Show Category / Status / Pricing / Traction filter dropdowns
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">Summary text</th>
			<td>
				<label>
					<input type="checkbox" name="show_summary" value="1"
					       <?php checked( $d['show_summary'] ); ?>>
					Show summary snippet below tool name
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">Capability tags</th>
			<td>
				<label>
					<input type="checkbox" name="show_tags" value="1"
					       <?php checked( $d['show_tags'] ); ?>>
					Show capability tag chips below tool name
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">GitHub link</th>
			<td>
				<label>
					<input type="checkbox" name="show_github" value="1"
					       <?php checked( $d['show_github'] ); ?>>
					Show GitHub repo link below tool name (when available)
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">Footer</th>
			<td>
				<label>
					<input type="checkbox" name="show_footer" value="1"
					       <?php checked( $d['show_footer'] ); ?>>
					Show "Powered by Notion / <?php echo esc_html( JETX_HUB_BRANDING_NAME ); ?>" footer
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_limit">Row limit</label>
			</th>
			<td>
				<input type="number"
				       id="jhub_limit"
				       name="items_limit"
				       value="<?php echo esc_attr( $d['items_limit'] ); ?>"
				       min="0"
				       max="2000"
				       class="small-text">
				<span> entries (0 = no limit)</span>
				<p class="description">Applied after API filters. Useful for "Top 10" or limiting gallery cards.</p>
			</td>
		</tr>
	</table>

	<?php submit_button( 'Save Display Settings' ); ?>
</form>
