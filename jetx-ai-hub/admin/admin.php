<?php
/**
 * admin/admin.php
 *
 * All WP Admin hooks for the JetX AI Hub settings page.
 * Loaded only when is_admin() === true (see jetx-ai-hub.php bootstrap).
 *
 * Responsibilities:
 *   - Register the Settings sub-menu page.
 *   - Enqueue admin-specific scripts and styles.
 *   - Route requests to the correct tab view.
 *   - Display admin notices (saved / refreshed / flushed).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Menu registration ─────────────────────────────────────────────────────────

add_action( 'admin_menu', 'jetx_hub_register_menu' );

function jetx_hub_register_menu(): void {
	add_options_page(
		JETX_HUB_MENU_LABEL,   // Page title (browser tab)
		JETX_HUB_MENU_LABEL,   // Menu label
		'manage_options',      // Capability
		'jetx-ai-hub',         // Menu slug
		'jetx_hub_admin_page'  // Callback
	);
}

// ── Asset enqueue (admin page only) ───────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'jetx_hub_admin_enqueue' );

function jetx_hub_admin_enqueue( string $hook ): void {
	if ( $hook !== 'settings_page_jetx-ai-hub' ) return;

	// jQuery UI Sortable (bundled with WordPress core — no external CDN needed).
	wp_enqueue_script( 'jquery-ui-sortable' );

	// Admin stylesheet.
	wp_enqueue_style(
		'jetx-hub-admin',
		JETX_HUB_URL . 'assets/css/admin.css',
		[],
		JETX_HUB_VERSION
	);

	// Admin JS (filter builder, sort builder).
	// Data is passed via wp_localize_script so no PHP is needed inside the JS file.
	wp_enqueue_script(
		'jetx-hub-admin',
		JETX_HUB_URL . 'assets/js/admin.js',
		[ 'jquery', 'jquery-ui-sortable' ],
		JETX_HUB_VERSION,
		true
	);

	$props = jetx_hub_property_defs();

	// Operator map keyed by Notion property type.
	$filter_operators = [
		'select'       => [ 'equals' => 'is', 'does_not_equal' => 'is not', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
		'multi_select' => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
		'title'        => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
		'rich_text'    => [ 'contains' => 'contains', 'does_not_contain' => 'does not contain', 'equals' => 'equals', 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
		'url'          => [ 'is_empty' => 'is empty', 'is_not_empty' => 'is not empty' ],
		'date'         => [ 'after' => 'after', 'before' => 'before', 'on_or_after' => 'on or after', 'on_or_before' => 'on or before', 'is_empty' => 'is empty' ],
	];

	wp_localize_script( 'jetx-hub-admin', 'jetxHubAdmin', [
		'filterOperators' => $filter_operators,
		'propertyTypes'   => array_map( fn( $p ) => $p['type'], $props ),
		'propertyLabels'  => array_map( fn( $p ) => $p['label'] . ' (' . $p['type'] . ')', $props ),
	] );
}


// ── Page router ───────────────────────────────────────────────────────────────

/**
 * Main admin page callback. Renders outer wrap, notices, tab nav, then includes
 * the appropriate tab view file.
 */
function jetx_hub_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$tab  = sanitize_key( $_GET['tab'] ?? 'connection' );
	$tabs = [
		'connection' => '🔌 Connection',
		'schema'     => '🗂️ Schema',
		'columns'    => '📋 Columns',
		'filters'    => '🔍 Filters & Sort',
		'display'    => '🎨 Display',
	];
	?>
	<div class="wrap jhub-admin-wrap">
		<h1><?php echo esc_html( JETX_HUB_ADMIN_TITLE ); ?></h1>

		<?php jetx_hub_admin_notices(); ?>

		<nav class="nav-tab-wrapper jhub-tab-nav" style="margin-bottom:20px;">
			<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=jetx-ai-hub&tab=' . $key ) ); ?>"
			   class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>

		<?php
		switch ( $tab ) {
			case 'schema':  require JETX_HUB_PATH . 'admin/views/tab-schema.php';    break;
			case 'columns': require JETX_HUB_PATH . 'admin/views/tab-columns.php';   break;
			case 'filters': require JETX_HUB_PATH . 'admin/views/tab-filters.php';   break;
			case 'display': require JETX_HUB_PATH . 'admin/views/tab-display.php';   break;
			default:        require JETX_HUB_PATH . 'admin/views/tab-connection.php'; break;
		}
		?>

	</div><!-- .wrap -->
	<?php
}

/**
 * Render dismissable admin notice banners for query-string status flags.
 */
function jetx_hub_admin_notices(): void {
	$notices = [
		'saved'     => [ 'success', 'Settings saved.' ],
		'refreshed' => [ 'success', 'Data refreshed from Notion.' ],
		'flushed'   => [ 'success', 'Cache cleared.' ],
	];

	foreach ( $notices as $key => $config ) {
		if ( isset( $_GET[ $key ] ) ) {
			echo '