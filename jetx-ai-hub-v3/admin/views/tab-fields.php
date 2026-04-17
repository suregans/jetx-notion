<?php
/**
 * admin/views/tab-fields.php
 *
 * Tab — 🔍 Fields
 * Auto-detect all Notion database columns and let the admin toggle
 * which fields are active (displayed in the table / parsed from API).
 *
 * No manual entry required — click "Detect Fields from Notion" and the plugin
 * queries the Notion API, lists every property it finds, and saves the schema.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$detected     = jetx_hub_get_detected_schema();
$active_flags = get_option( 'jetx_hub_active_fields', [] );
$has_token    = ! empty( get_option( 'jetx_hub_token', '' ) );
$has_db_id    = ! empty( get_option( 'jetx_hub_db_id', '' ) );

// Type badges colour map for the UI.
$type_colors = [
	'title'        => '#60a5fa',
	'rich_text'    => '#94a3b8',
	'select'       => '#4ade80',
	'multi_select' => '#a78bfa',
	'url'          => '#fb923c',
	'date'         => '#fbbf24',
	'checkbox'     => '#f472b6',
	'number'       => '#34d399',
];
?>

<div style="max-width:860px;">

	<!-- ── Connection prerequisite notice ─────────────────────────────── -->
	<?php if ( ! $has_token || ! $has_db_id ) : ?>
	<div class="notice notice-warning inline" style="margin-bottom:20px;">
		<p>
			⚠️ You need to enter your <strong>Notion API Token</strong> and
			<strong>Database ID</strong> on the
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection' ) ); ?>">🔌 Connection tab</a>
			before detecting fields.
		</p>
	</div>
	<?php endif; ?>

	<!-- ── Detect button ──────────────────────────────────────────────── -->
	<form method="post">
		<?php wp_nonce_field( 'jetx_detect_fields' ); ?>
		<input type="hidden" name="jetx_detect_fields" value="1">

		<p class="jhub-tab-intro">
			Click <strong>Detect Fields from Notion</strong> to pull all column headers
			from your Notion database. The plugin will list every detected property so you
			can choose which ones to display. No PHP editing required.
		</p>

		<div class="jhub-action-row" style="margin-bottom:24px;">
			<?php submit_button(
				'🔄 Detect Fields from Notion',
				'primary',
				'submit',
				false,
				[ 'id' => 'jhub-detect-btn', 'disabled' => ( ! $has_token || ! $has_db_id ) ? 'disabled' : false ]
			); ?>
			<?php if ( ! empty( $detected ) ) : ?>
			<span style="color:#6b7280;font-size:13px;line-height:32px;">
				<?php echo count( $detected ); ?> fields detected. Click to refresh.
			</span>
			<?php endif; ?>
		</div>
	</form>

	<!-- ── Field toggle table ─────────────────────────────────────────── -->
	<?php if ( ! empty( $detected ) ) : ?>
	<form method="post">
		<?php wp_nonce_field( 'jetx_save_fields' ); ?>
		<input type="hidden" name="jetx_save_fields" value="1">

		<h2 style="margin-top:0;">Detected Fields</h2>
		<p class="description" style="margin-bottom:16px;">
			Toggle which fields are parsed from Notion and available for display.
			The <strong>title</strong> field is always on. Changes flush the data cache.
		</p>

		<table class="widefat striped" style="max-width:860px;">
			<thead>
				<tr>
					<th style="width:40px;">Active</th>
					<th>Field Key</th>
					<th>Notion Property Name</th>
					<th style="width:140px;">Type</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $detected as $key => $def ) :
				$is_title  = $def['type'] === 'title';
				$is_active = $is_title || ( $active_flags[ $key ] ?? true );
				$color     = $type_colors[ $def['type'] ] ?? '#94a3b8';
			?>
				<tr>
					<td style="text-align:center;">
						<input
							type="checkbox"
							name="active_fields[<?php echo esc_attr( $key ); ?>]"
							value="1"
							<?php checked( $is_active ); ?>
							<?php disabled( $is_title ); ?>
							title="<?php echo $is_title ? 'Title field is always active' : ''; ?>"
						>
					</td>
					<td>
						<code style="font-size:12px;"><?php echo esc_html( $key ); ?></code>
						<?php if ( $is_title ) : ?>
						<span style="color:#f59e0b;font-size:11px;margin-left:6px;">★ title</span>
						<?php endif; ?>
					</td>
					<td>
						<strong><?php echo esc_html( $def['notion'] ); ?></strong>
					</td>
					<td>
						<span style="
							background: <?php echo esc_attr( $color ); ?>22;
							color: <?php echo esc_attr( $color ); ?>;
							border: 1px solid <?php echo esc_attr( $color ); ?>44;
							padding: 2px 10px;
							border-radius: 12px;
							font-size: 11px;
							font-weight: 600;
							letter-spacing: 0.03em;
						"><?php echo esc_html( $def['type'] ); ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:16px;">
			<?php submit_button( 'Save Field Selections', 'primary', 'submit', false ); ?>
		</p>

	</form>

	<?php else : ?>

	<div style="
		background: #1e293b;
		border: 1px dashed #334155;
		border-radius: 8px;
		padding: 32px;
		text-align: center;
		color: #94a3b8;
		margin-top: 8px;
	">
		<p style="font-size:15px;margin-bottom:8px;">No fields detected yet.</p>
		<p style="font-size:13px;">
			Make sure your Notion API Token and Database ID are saved on the
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=jetx-ai-hub&tab=connection' ) ); ?>">Connection tab</a>,
			then click <strong>Detect Fields from Notion</strong> above.
		</p>
	</div>

	<?php endif; ?>

</div>
