<?php
/**
 * public/views/controls.php
 *
 * Frontend search bar and filter dropdown controls. Fully dynamic in v4.0.
 *
 * Dropdowns are built from the first 4 active select fields (not hardcoded
 * to category/status/pricing/traction). Each dropdown gets a data-findex
 * attribute (0–3) so frontend.js can read the correct data-f* value from rows.
 *
 * Variables in scope (from shortcode.php require):
 *   $disp   array   Display settings
 *   $items  array   Parsed Notion rows
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Find the first 4 active select fields to use as filter axes.
$props        = jetx_hub_property_defs();
$filter_props = []; // [index => [key, label]]
$fi           = 0;
foreach ( $props as $k => $def ) {
	if ( ! in_array( $def['type'], [ 'select', 'multi_select' ], true ) ) continue;
	if ( $def['internal'] ) continue;
	$filter_props[ $fi ] = [ 'key' => $k, 'label' => $def['label'], 'type' => $def['type'] ];
	$fi++;
	if ( $fi >= 4 ) break;
}
?>
<div class="jhub-controls">

	<?php if ( $disp['show_search'] ) : ?>
	<div class="jhub-search-wrap">
		<svg class="jhub-search-icon"
		     xmlns="http://www.w3.org/2000/svg"
		     viewBox="0 0 20 20"
		     fill="currentColor"
		     width="16" height="16"
		     aria-hidden="true">
			<path fill-rule="evenodd"
			      d="M9 3a6 6 0 1 0 3.73 10.74l3.27 3.27 1.06-1.06-3.27-3.27A6 6 0 0 0 9 3zm-4 6a4 4 0 1 1 8 0 4 4 0 0 1-8 0z"
			      clip-rule="evenodd"/>
		</svg>
		<input type="text"
		       id="jhub-search"
		       class="jhub-search"
		       placeholder="Search…"
		       aria-label="Search entries">
	</div>
	<?php endif; ?>

	<?php if ( $disp['show_filters'] && ! empty( $filter_props ) ) : ?>
	<div class="jhub-filters">

		<?php foreach ( $filter_props as $findex => $fp ) :
			$key   = $fp['key'];
			$label = $fp['label'];
			$type  = $fp['type'];

			// Build unique sorted values for this field from live data.
			$values = [];
			foreach ( $items as $row ) {
				$rv = $row[ $key ] ?? null;
				if ( $rv === null || $rv === '' ) continue;
				if ( is_array( $rv ) ) {
					if ( isset( $rv['name'] ) ) {
						$values[] = $rv['name'];
					} else {
						// multi_select: array of {name, color}
						foreach ( $rv as $v ) {
							if ( ! empty( $v['name'] ) ) $values[] = $v['name'];
						}
					}
				} else {
					$values[] = (string) $rv;
				}
			}
			$values = array_values( array_unique( array_filter( $values ) ) );
			sort( $values );

			if ( empty( $values ) ) continue;
		?>

		<select class="jhub-select jhub-filter-select"
		        data-findex="<?php echo esc_attr( $findex ); ?>"
		        aria-label="Filter by <?php echo esc_attr( $label ); ?>">
			<option value="">All <?php echo esc_html( $label ); ?></option>
			<?php foreach ( $values as $v ) : ?>
			<option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $v ); ?></option>
			<?php endforeach; ?>
		</select>

		<?php endforeach; ?>

	</div>
	<?php endif; ?>

	<span id="jhub-count" class="jhub-count" aria-live="polite"></span>

</div><!--