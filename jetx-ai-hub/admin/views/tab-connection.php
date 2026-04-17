<?php
/**
 * admin/views/tab-connection.php
 *
 * Tab 1 — Connection settings: Notion token, database ID, cache interval.
 * Also shows live status cards and cache control actions.
 *
 * Variables available from admin.php router: none (reads options directly).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$token      = get_option( 'jetx_hub_token', '' );
$db_id      = get_option( 'jetx_hub_db_id', JETX_HUB_DEFAULT_DB_ID );
$mins       = get_option( 'jetx_hub_cache_minutes', JETX_HUB_DEFAULT_CACHE_MINS );
$next_cron  = wp_next_scheduled( JETX_HUB_CRON_HOOK );
$cache_warm = false !== get_transient( JETX_HUB_CACHE_KEY );
$has_stale  = ! empty( get_option( JETX_HUB_STALE_KEY, [] ) );
?>

<!-- Status cards -->
<div class="jhub-status-row">
	<?php
	$cards = [
		[ 'label' => 'Cache',        'value' => $cache_warm ? '🟢 Warm'      : '🔴 Cold' ],
		[ 'label' => 'Stale Backup', 'value' => $has_stale  ? '🟢 Available' : '⚪ Not yet set' ],
		[ 'label' => 'Next Refresh', 'value' => $next_cron  ? human_time_diff( time(), $next_cron ) . ' from now' : '⚠️ Not scheduled' ],
		[ 'label' => 'Token',        'value' => ! empty( $token ) ? '🟢 Configured' : '🔴 Missing' ],
	];
	foreach ( $cards as $card ) :
	?>
	<div class="jhub-status-card">
		<div class="jhub-status-label"><?php echo esc_html( $card['label'] ); ?></div>
		<div class="jhub-status-value"><?php echo esc_html( $card['value'] ); ?></div>
	</div>
	<?php endforeach; ?>
</div>

<?php if ( empty( $token ) ) : ?>
<div class="jhub-setup-notice">
	<strong>⚠️ Setup required</strong> — create a Notion integration to get your token:
	<ol>
		<li>Go to <a href="https://www.notion.so/profile/integrations" target="_blank">notion.so → Integrations → + New integration</a></li>
		<li>Name it <em>JetX WordPress</em>, select your workspace, click Save</li>
		<li>Copy the <strong>Internal Integration Secret</strong> (starts with <code>secret_…</code>)</li>
		<li>In Notion, open your database → <strong>···</strong> → <strong>Connect to</strong> → select your integration</li>
	</ol>
</div>
<?php endif; ?>

<!-- Connection settings form -->
<form method="post" class="jhub-form" style="max-width:800px;">
	<?php wp_nonce_field( 'jetx_save_connection' ); ?>
	<input type="hidden" name="jetx_save_connection" value="1">

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="jhub_token">Notion Integration Token</label>
			</th>
			<td>
				<input type="password"
				       id="jhub_token"
				       name="jetx_hub_token"
				       value="<?php echo esc_attr( $token ); ?>"
				       class="regular-text"
				       autocomplete="off">
				<p class="description">
					Starts with <code>secret_…</code> — stored server-side only, never sent to browsers.
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_db">Notion Database ID</label>
			</th>
			<td>
				<input type="text"
				       id="jhub_db"
				       name="jetx_hub_db_id"
				       value="<?php echo esc_attr( $db_id ); ?>"
				       class="regular-text">
				<p class="description">
					Default: <code><?php echo esc_html( JETX_HUB_DEFAULT_DB_ID ); ?></code>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jhub_mins">Auto-refresh interval</label>
			</th>
			<td>
				<input type="number"
				       id="jhub_mins"
				       name="jetx_hub_cache_minutes"
				       value="<?php echo esc_attr( $mins ); ?>"
				       min="5"
				       max="1440"
				       class="small-text"> minutes
				<p class="description">
					WP-Cron refreshes data silently in the background.
					Use <strong>5</strong> for testing, <strong>60</strong> for production.
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( 'Save Connection Settings', 'primary', 'jetx_save_connection_btn' ); ?>
</form>

<hr class="jhub-hr">

<h2>Cache Controls</h2>
<div class="jhub-action-row">
	<form method="post">
		<?php wp_nonce_field( 'jetx_refresh_now' ); ?>
		<input type="hidden" name="jetx_refresh_now" value="1">
		<button class="button button-primary" type="submit">🔄 Refresh from Notion Now</button>
	</form>
	<form method="post">
		<?php wp_nonce_field( 'jetx_flush_cache' ); ?>
		<input type="hidden" name="jetx_flush_cache" value="1">
		<button class="button button-secondary" type="submit">🗑 Clear Cache</button>
	</form>
</div>

<hr class="jhub-hr">

<h2>Shortcode</h2>
<p>Add <code>[jetx_ai_hub]</code> to any page or post.</p>
<table class="widefat" style="max-width:700px;">
	<thead>
		<tr><th>Shortcode</th><th>Result</th></tr>
	</thead>
	<tbody>
		<tr><td><code>[jetx_ai_hub]</code></td><td>Full table, all entries</td></tr>
		<tr><td><code>[jetx_ai_hub limit="20"]</code></td><td>First 20 entries only</td></tr>
		<tr><td><code>[jetx_ai_hub category="AI Model"]</code></td><td>Pre-filtered to AI Models</td></tr>
		<tr><td><code>[jetx_ai_hub show_internal="true"]</code></td><td>Shows internal columns (admins only)</td></tr>
	</tbody>
</table>
