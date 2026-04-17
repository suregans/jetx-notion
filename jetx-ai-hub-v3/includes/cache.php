<?php
/**
 * includes/cache.php
 *
 * Caching strategy: WordPress transients (auto-uses object cache / Redis if available).
 *
 * Protections built in:
 *   1. Cache stampede mutex  — only one process fetches at a time; others get stale data.
 *   2. Stale-while-revalidate — if the fresh cache is empty and a fetch is in progress,
 *      serves the last known good data set from wp_options.
 *   3. WP-Cron background refresh — silently pre-warms the cache on a configurable schedule
 *      so end-users never trigger a blocking API call.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Cron schedule ─────────────────────────────────────────────────────────────

add_filter( 'cron_schedules', 'jetx_hub_add_cron_schedule' );

function jetx_hub_add_cron_schedule( array $schedules ): array {
	$minutes = jetx_hub_cache_minutes();
	$schedules['jetx_hub_interval'] = [
		'interval' => $minutes * 60,
		'display'  => sprintf( 'Every %d min (JetX AI Hub)', $minutes ),
	];
	return $schedules;
}

add_action( JETX_HUB_CRON_HOOK, 'jetx_hub_background_refresh' );

// Re-schedule cron whenever the cache interval option is changed.
add_action( 'update_option_jetx_hub_cache_minutes', 'jetx_hub_schedule_cron' );

/**
 * Schedule (or re-schedule) the WP-Cron background refresh event.
 */
function jetx_hub_schedule_cron(): void {
	wp_clear_scheduled_hook( JETX_HUB_CRON_HOOK );
	$minutes = jetx_hub_cache_minutes();
	wp_schedule_event( time() + $minutes * 60, 'jetx_hub_interval', JETX_HUB_CRON_HOOK );
}

/**
 * Background cron callback — silently refreshes cache without blocking a page load.
 */
function jetx_hub_background_refresh(): void {
	$result = jetx_hub_call_api();
	if ( ! isset( $result['error'] ) ) {
		set_transient( JETX_HUB_CACHE_KEY, $result, jetx_hub_cache_minutes() * 60 );
		update_option( JETX_HUB_STALE_KEY, $result, false );
	}
}

// ── Public data fetcher ───────────────────────────────────────────────────────

/**
 * Return the dataset, using cache where possible.
 *
 * Fetch order:
 *   1. Return fresh transient immediately if warm.
 *   2. If another process is already fetching (lock exists), return stale data.
 *   3. Otherwise: acquire lock, call API, release lock, store results.
 *   4. On API error, return stale data if available.
 *
 * @return array  Row array, stale row array, or ['error' => string].
 */
function jetx_hub_fetch_data(): array {
	$fresh = get_transient( JETX_HUB_CACHE_KEY );
	if ( false !== $fresh ) {
		return $fresh;
	}

	// Another process is already fetching — return stale rather than queue up.
	if ( get_transient( JETX_HUB_LOCK_KEY ) ) {
		$stale = get_option( JETX_HUB_STALE_KEY, null );
		return ! empty( $stale ) ? $stale : [ 'error' => 'refreshing' ];
	}

	// Acquire mutex lock (60s TTL as safety net).
	set_transient( JETX_HUB_LOCK_KEY, 1, 60 );

	$result = jetx_hub_call_api();

	delete_transient( JETX_HUB_LOCK_KEY );

	if ( isset( $result['error'] ) ) {
		$stale = get_option( JETX_HUB_STALE_KEY, null );
		return ! empty( $stale ) ? $stale : $result;
	}

	set_transient( JETX_HUB_CACHE_KEY, $result, jetx_hub_cache_minutes() * 60 );
	update_option( JETX_HUB_STALE_KEY, $result, false );

	return $result;
}

// ── Utility ───────────────────────────────────────────────────────────────────

/**
 * Return the configured cache interval (minimum 5 minutes).
 *
 * @return int  Minutes.
 */
function jetx_hub_cache_minutes(): int {
	return max( 5, (int) get_option( 'jetx_hub_cache_minutes', JETX_HUB_DEFAULT_CACHE_MINS ) );
}
