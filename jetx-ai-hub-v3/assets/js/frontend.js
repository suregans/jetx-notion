/**
 * assets/js/frontend.js
 *
 * JetX AI Hub — Frontend search and filter interactions.
 *
 * Pure static file — no PHP interpolation, no inline data.
 * Enqueued via wp_enqueue_script() in includes/shortcode.php.
 *
 * Works across all four layout types:
 *   table   — filters <tr class="jhub-row"> inside #jhub-table tbody
 *   gallery — filters <div class="jhub-row"> inside .jhub-gallery
 *   list    — filters <div class="jhub-row"> inside .jhub-list
 *   board   — filters <div class="jhub-board-card jhub-row">; hides empty columns
 *
 * Each filterable element carries data attributes written by jetx_hub_row_attrs():
 *   data-search    Lowercase concatenated blob of all searchable text fields.
 *   data-category  Category name.
 *   data-status    Status name.
 *   data-pricing   Pricing name.
 *   data-traction  Traction name.
 */

( function () {
    'use strict';

    var wrap   = document.getElementById( 'jetx-ai-hub' );
    if ( ! wrap ) return;

    var layout  = wrap.dataset.layout || 'table';
    var search  = document.getElementById( 'jhub-search' );
    var selCat  = document.getElementById( 'jhub-cat' );
    var selSt   = document.getElementById( 'jhub-status' );
    var selPr   = document.getElementById( 'jhub-pricing' );
    var selTr   = document.getElementById( 'jhub-traction' );
    var countEl = document.getElementById( 'jhub-count' );
    var noRes   = document.getElementById( 'jhub-no-results' );
    var resetBtn= document.getElementById( 'jhub-reset' );

    /**
     * Return all filterable row elements for the current layout.
     *
     * @returns {NodeList}
     */
    function getRows() {
        if ( layout === 'board' ) {
            return document.querySelectorAll( '.jhub-board-card.jhub-row' );
        }
        return document.querySelectorAll( '#jhub-table .jhub-row' );
    }

    /**
     * Apply current search + filter values to all rows.
     * Adds/removes .jhub-hidden. Updates count display and no-results banner.
     */
    function applyFilters() {
        var q   = search ? search.value.toLowerCase().trim() : '';
        var cat = selCat ? selCat.value  : '';
        var st  = selSt  ? selSt.value   : '';
        var pr  = selPr  ? selPr.value   : '';
        var tr  = selTr  ? selTr.value   : '';

        var rows    = getRows();
        var visible = 0;

        rows.forEach( function ( row ) {
            var match =
                ( ! q   || row.dataset.search.indexOf( q ) !== -1 ) &&
                ( ! cat || row.dataset.category === cat ) &&
                ( ! st  || row.dataset.status   === st  ) &&
                ( ! pr  || row.dataset.pricing  === pr  ) &&
                ( ! tr  || row.dataset.traction === tr  );

            row.classList.toggle( 'jhub-hidden', ! match );
            if ( match ) visible++;
        } );

        if ( countEl ) {
            countEl.textContent = visible + ' of ' + rows.length;
        }

        if ( noRes ) {
            noRes.style.display = visible === 0 ? 'block' : 'none';
        }

        // For board layout: hide columns that have no visible cards.
        if ( layout === 'board' ) {
            document.querySelectorAll( '.jhub-board-col' ).forEach( function ( col ) {
                var visibleCards = col.querySelectorAll( '.jhub-board-card:not(.jhub-hidden)' ).length;
                col.style.display = visibleCards === 0 ? 'none' : '';
            } );
        }
    }

    /**
     * Clear all filter inputs and re-apply (shows all rows).
     */
    function clearFilters() {
        if ( search ) search.value = '';
        if ( selCat ) selCat.value = '';
        if ( selSt  ) selSt.value  = '';
        if ( selPr  ) selPr.value  = '';
        if ( selTr  ) selTr.value  = '';
        applyFilters();
    }

    // Attach event listeners.
    [ search, selCat, selSt, selPr, selTr ].forEach( function ( el ) {
        if ( ! el ) return;
        var event = el.tagName === 'INPUT' ? 'input' : 'change';
        el.addEventListener( event, applyFilters );
    } );

    if ( resetBtn ) {
        resetBtn.addEventListener( 'click', clearFilters );
    }

    // Run once on load to initialise the count display.
    applyFilters();

} )();
