/**
 * assets/js/admin.js
 *
 * JetX AI Hub — WP Admin page interactions.
 *
 * Loaded only on the settings_page_jetx-ai-hub screen (see admin/admin.php).
 * PHP data is injected via wp_localize_script() under window.jetxHubAdmin:
 *
 *   jetxHubAdmin.filterOperators  {object}  Operator map keyed by Notion property type.
 *   jetxHubAdmin.propertyTypes    {object}  Property-key → type map.
 *   jetxHubAdmin.propertyLabels   {object}  Property-key → label string.
 *
 * Features:
 *   1. jQuery UI Sortable for the Columns tab drag-to-reorder.
 *   2. Dynamic operator dropdown update when a filter property is changed.
 *   3. Add / remove filter rows (clones <template id="jhub-filter-tpl">).
 *   4. Add / remove sort rows (clones <template id="jhub-sort-tpl">).
 *   5. Show / hide Board "group by" row on Display tab.
 */

/* global jQuery, jetxHubAdmin */
(function ( $ ) {
    'use strict';

    var ops    = ( window.jetxHubAdmin && jetxHubAdmin.filterOperators ) || {};
    var types  = ( window.jetxHubAdmin && jetxHubAdmin.propertyTypes  ) || {};

    // ── 1. Columns — drag-to-reorder ─────────────────────────────────────
    if ( $( '#jhub-sortable' ).length ) {
        $( '#jhub-sortable' ).sortable( {
            handle:  '.jhub-drag-handle',
            axis:    'y',
            opacity: 0.7,
            cursor:  'grabbing',
        } );
    }

    // ── 2. Filter — update operator dropdown when property changes ────────
    function updateOperators( propSelect ) {
        var key      = propSelect.value;
        var type     = types[ key ] || 'select';
        var opSelect = propSelect.closest( 'tr' ).querySelector( '.jhub-op-sel' );
        if ( ! opSelect ) return;

        var available = ops[ type ] || ops['select'] || {};
        opSelect.innerHTML = '';

        Object.entries( available ).forEach( function ( entry ) {
            var val = entry[0];
            var lbl = entry[1];
            var opt = document.createElement( 'option' );
            opt.value       = val;
            opt.textContent = lbl;
            opSelect.appendChild( opt );
        } );
    }

    // Listen for property select changes (event delegation — works on cloned rows too).
    document.addEventListener( 'change', function ( e ) {
        if ( e.target.classList.contains( 'jhub-prop-sel' ) ) {
            updateOperators( e.target );
        }
    } );

    // ── 3. Filter — add / remove rows ────────────────────────────────────
    document.addEventListener( 'click', function ( e ) {
        if ( e.target.classList.contains( 'jhub-remove-row' ) ) {
            e.target.closest( 'tr' ).remove();
        }
    } );

    var addFilterBtn = document.getElementById( 'jhub-add-filter' );
    if ( addFilterBtn ) {
        addFilterBtn.addEventListener( 'click', function () {
            var tbody = document.getElementById( 'jhub-filter-rows' );
            var tpl   = document.getElementById( 'jhub-filter-tpl' );
            if ( ! tbody || ! tpl ) return;

            var clone = tpl.content.cloneNode( true );
            var idx   = tbody.querySelectorAll( 'tr' ).length;

            clone.querySelectorAll( '[name]' ).forEach( function ( el ) {
                el.name = el.name.replace( '__IDX__', idx );
            } );

            tbody.appendChild( clone );
        } );
    }

    // ── 4. Sort — add / remove rows ──────────────────────────────────────
    var addSortBtn = document.getElementById( 'jhub-add-sort' );
    if ( addSortBtn ) {
        addSortBtn.addEventListener( 'click', function () {
            var tbody = document.getElementById( 'jhub-sort-rows' );
            var tpl   = document.getElementById( 'jhub-sort-tpl' );
            if ( ! tbody || ! tpl ) return;

            var clone = tpl.content.cloneNode( true );
            var idx   = tbody.querySelectorAll( 'tr' ).length;

            clone.querySelectorAll( '[name]' ).forEach( function ( el ) {
                el.name = el.name.replace( '__IDX__', idx );
            } );

            tbody.appendChild( clone );
        } );
    }

    // ── 5. Display — show/hide Board group-by row ─────────────────────────
    var layoutRadios = document.querySelectorAll( 'input[name="layout"]' );
    var boardRow     = document.getElementById( 'jhub-board-row' );

    if ( layoutRadios.length && boardRow ) {
        layoutRadios.forEach( function ( radio ) {
            radio.addEventListener( 'change', function () {
                boardRow.style.display = this.value === 'board' ? '' : 'none';
            } );
        } );
    }

}( jQuery ) );
