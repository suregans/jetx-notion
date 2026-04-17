/**
 * assets/js/frontend.js
 *
 * JetX AI Hub — Frontend interactions: search, filter, view toggle.
 *
 * v4.0 changes:
 *   - Filter dropdowns use dynamic data-f0..f3 attributes (set by jetx_hub_row_attrs())
 *     instead of hardcoded data-category/status/pricing/traction.
 *   - View toggle button switches between Table panel and Graph panel.
 *   - jhub-hidden CSS class used for both rows and panels.
 */

( function () {
    'use strict';

    var wrap = document.getElementById( 'jetx-ai-hub' );
    if ( ! wrap ) return;

    var layout   = wrap.dataset.layout || 'table';
    var search   = document.getElementById( 'jhub-search' );
    var countEl  = document.getElementById( 'jhub-count' );
    var noRes    = document.getElementById( 'jhub-no-results' );
    var resetBtn = document.getElementById( 'jhub-reset' );

    // Filter dropdowns — up to 4 (f0..f3), mapped from controls.php selects.
    var selects  = wrap.querySelectorAll( '.jhub-filter-select' );
    // selects[i] gets data-findex="i" set in controls.php.

    // ── View toggle ───────────────────────────────────────────────────────────
    var toggleBtns  = wrap.querySelectorAll( '.jhub-toggle-btn' );
    var tablePanel  = document.getElementById( 'jhub-panel-table' );
    var graphPanel  = document.getElementById( 'jhub-panel-graph' );

    toggleBtns.forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            var view = btn.dataset.view;

            toggleBtns.forEach( function ( b ) {
                b.classList.toggle( 'active', b.dataset.view === view );
                b.setAttribute( 'aria-pressed', b.dataset.view === view ? 'true' : 'false' );
            } );

            if ( tablePanel ) tablePanel.classList.toggle( 'jhub-hidden', view === 'graph' );
            if ( graphPanel ) graphPanel.classList.toggle( 'jhub-hidden', view !== 'graph' );

            wrap.dataset.layout = view;
            layout = view;

            // Re-run applyFilters so count updates for the visible panel.
            applyFilters();
        } );
    } );

    // ── Row query ─────────────────────────────────────────────────────────────
    function getRows() {
        if ( layout === 'board' ) {
            return wrap.querySelectorAll( '.jhub-board-card.jhub-row' );
        }
        if ( layout === 'graph' ) {
            // Filtering doesn't apply to the graph panel — return empty.
            return [];
        }
        return wrap.querySelectorAll( '#jhub-table .jhub-row' );
    }

    // ── Apply filters ─────────────────────────────────────────────────────────
    function applyFilters() {
        var q = search ? search.value.toLowerCase().trim() : '';

        // Collect active filter values keyed by findex.
        var filterValues = {};
        selects.forEach( function ( sel ) {
            var fi = sel.dataset.findex;
            filterValues[ fi ] = sel.value;
        } );

        var rows    = getRows();
        var visible = 0;

        rows.forEach( function ( row ) {
            // Text search.
            var textMatch = ! q || ( row.dataset.search || '' ).indexOf( q ) !== -1;

            // Dropdown filters.
            var filterMatch = true;
            Object.keys( filterValues ).forEach( function ( fi ) {
                var val = filterValues[ fi ];
                if ( ! val ) return;
                var rowVal = row.dataset[ 'f' + fi ] || '';
                // Support multi-value (comma-separated from multi_select).
                if ( rowVal.indexOf( ',' ) !== -1 ) {
                    if ( rowVal.split( ',' ).indexOf( val ) === -1 ) filterMatch = false;
                } else {
                    if ( rowVal !== val ) filterMatch = false;
                }
            } );

            var match = textMatch && filterMatch;
            row.classList.toggle(