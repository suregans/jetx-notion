<?php
/**
 * public/views/layout-gallery.php
 *
 * Gallery layout — CSS grid of cards. Fully dynamic in v4.0.
 * Header badges use the first two active select fields found in the schema.
 *
 * Variables in scope (from shortcode.php require):
 *   $items             array
 *   $disp              array
 *   $use_notion_colors bool
 *   $theme             string
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Dynamically find up to two select fields for the card header badges.
$props       = jetx_hub_property_defs();
$select_keys = [];
foreach ( $props as $k => $def ) {
	if ( in_array( $def['type'], [ 'select', 'multi_select' ], true ) && ! $def['internal'] ) {
		$select_keys[] = $k;
		if ( count( $select_keys ) >= 2 ) break;
	}
}
$badge_key_1 = $select_keys[0] ?? null;
$badge_key_2 = $select_keys[1] ?? null;
?>

<div class="jhub-gallery" id="jhub-table">
	<?php foreach ( $items as $item ) :
		$b1 = $badge_key_1 ? ( $item[ $badge_key_1 ] ?? [] ) : [];
		$b2 = $badge_key_2 ? ( $item[ $badge_key_2 ] ?? [] ) : [];
		// multi_select: show first item only in card header.
		if ( is_array( $b1 ) && isset( $b1[0] ) ) $b1 = $b1[0];
		if ( is_array( $b2 ) && isset( 