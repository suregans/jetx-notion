/**
 * assets/js/graph.js
 *
 * JetX AI Hub — Graph View (Sigma.js v2 + graphology)
 *
 * Reads serialized graph data from #jhub-graph-data <script> tag,
 * then initializes a Sigma instance in #jhub-sigma-container.
 *
 * Three-level hierarchy: Category (large) → Sub-category (medium) → Tool (small)
 *
 * Graph is laid out using a force-atlas2-like circular arrangement:
 *   - Category nodes spaced evenly in a large circle
 *   - Sub-category nodes clustered near their parent category
 *   - Tool nodes clustered near their sub-category (or category)
 *
 * Requires (loaded by shortcode.php):
 *   graphology         — https://cdn.jsdelivr.net/npm/graphology@0.25.4/dist/graphology.umd.min.js
 *   sigma              — https://cdn.jsdelivr.net/npm/sigma@2.4.0/build/sigma.min.js
 */

( function () {
	'use strict';

	// ── Wait for DOM ─────────────────────────────────────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		const dataEl = document.getElementById( 'jhub-graph-data' );
		const container = document.getElementById( 'jhub-sigma-container' );

		if ( ! dataEl || ! container ) return;

		let graphData;
		try {
			graphData = JSON.parse( dataEl.textContent );
		} catch ( e ) {
			console.error( 'JetX Graph: failed to parse graph data', e );
			return;
		}

		if ( ! window.graphology || ! window.Sigma ) {
			container.innerHTML = '<p style="color:#f87171;padding:20px;">Graph library failed to load. Check your internet connection.</p>';
			return;
		}

		const { nodes, edges, theme } = graphData;

		if ( ! nodes || nodes.length === 0 ) {
			container.innerHTML = '<p style="padding:20px;color:#94a3b8;">No data to display in graph view.</p>';
			return;
		}

		// ── Build graphology graph ────────────────────────────────────────────
		const graph = new graphology.Graph( { multi: false, allowSelfLoops: false } );

		// ── Layout: position nodes in concentric rings ────────────────────────
		const catNodes  = nodes.filter( n => n.type === 'category' );
		const subNodes  = nodes.filter( n => n.type === 'subcategory' );
		const toolNodes = nodes.filter( n => n.type === 'tool' );

		// Positions map: node_id → {x, y}
		const pos = {};

		// Category nodes: outer ring.
		const CAT_R = 400;
		catNodes.forEach( ( n, i ) => {
			const angle = ( 2 * Math.PI * i ) / Math.max( catNodes.length, 1 ) - Math.PI / 2;
			pos[ n.id ] = { x: Math.cos( angle ) * CAT_R, y: Math.sin( angle ) * CAT_R };
		} );

		// Build edge lookup for sub → cat and tool → sub/cat.
		const parentOf = {}; // child_id → parent_id
		edges.forEach( e => {
			parentOf[ e.target ] = e.source;
		} );

		// Sub-category nodes: mid ring, clustered near parent category.
		const subGrouped = {};
		subNodes.forEach( n => {
			const catId = parentOf[ n.id ];
			if ( ! subGrouped[ catId ] ) subGrouped[ catId ] = [];
			subGrouped[ catId ].push( n );
		} );

		Object.entries( subGrouped ).forEach( ( [ catId, subs ] ) => {
			const cp   = pos[ catId ] || { x: 0, y: 0 };
			const baseAngle = Math.atan2( cp.y, cp.x );
			const spread = Math.min( Math.PI / 3, ( Math.PI * 0.6 ) / Math.max( subs.length, 1 ) );
			const SUB_R  = 180;
			subs.forEach( ( n, i ) => {
				const offset = ( i - ( subs.length - 1 ) / 2 ) * spread;
				const angle  = baseAngle + offset;
				pos[ n.id ]  = {
					x: cp.x + Math.cos( angle ) * SUB_R,
					y: cp.y + Math.sin( angle ) * SUB_R,
				};
			} );
		} );

		// Sub-nodes with no parent category: place on inner ring.
		const orphanSubs = subNodes.filter( n => ! parentOf[ n.id ] || ! pos[ parentOf[ n.id ] ] );
		orphanSubs.forEach( ( n, i ) => {
			const angle = ( 2 * Math.PI * i ) / Math.max( orphanSubs.length, 1 );
			pos[ n.id ] = { x: Math.cos( angle ) * 250, y: Math.sin( angle ) * 250 };
		} );

		// Tool nodes: innermost cluster near parent sub/cat.
		const toolGrouped = {};
		toolNodes.forEach( n => {
			const pid = parentOf[ n.id ];
			if ( ! toolGrouped[ pid ] ) toolGrouped[ pid ] = [];
			toolGrouped[ pid ].push( n );
		} );

		Object.entries( toolGrouped ).forEach( ( [ pid, tools ] ) => {
			const pp     = pos[ pid ] || { x: 0, y: 0 };
			const baseA  = Math.atan2( pp.y, pp.x );
			const spread = Math.min( Math.PI / 4, ( Math.PI * 0.5 ) / Math.max( tools.length, 1 ) );
			const TOOL_R = 90;
			tools.forEach( ( n, i ) => {
				const offset = ( i - ( tools.length - 1 ) / 2 ) * spread;
				const angle  = baseA + offset;
				pos[ n.id ]  = {
					x: pp.x + Math.cos( angle ) * TOOL_R + ( Math.random() - 0.5 ) * 20,
					y: pp.y + Math.sin( angle ) * TOOL_R + ( Math.random() - 0.5 ) * 20,
				};
			} );
		} );

		// Orphan tool nodes (no parent found).
		toolNodes.filter( n => ! pos[ n.id ] ).forEach( ( n, i ) => {
			pos[ n.id ] = { x: ( Math.random() - 0.5 ) * 300, y: ( Math.random() - 0.5 ) * 300 };
		} );

		// ── Add nodes to graphology ───────────────────────────────────────────
		const isDark = theme === 'dark';
		const edgeColor = isDark ? '#334155' : '#cbd5e1';
		const labelColor = isDark ? '#e2e8f0' : '#1e293b';
		const labelBg   = isDark ? '#0f172a' : '#ffffff';

		nodes.forEach( n => {
			const p = pos[ n.id ] || { x: 0, y: 0 };
			graph.addNode( n.id, {
				x:             p.x,
				y:             p.y,
				size:          n.size,
				color:         n.color,
				label:         n.label,
				nodeType:      n.type,
				url:           n.url || '',
				borderColor:   n.color,
			} );
		} );

		// ── Add edges ─────────────────────────────────────────────────────────
		edges.forEach( e => {
			if ( graph.hasNode( e.source ) && graph.hasNode( e.target ) && ! graph.hasEdge( e.id ) ) {
				graph.addEdgeWithKey( e.id, e.source, e.target, {
					color: edgeColor,
					size:  1,
				} );
			}
		} );

		// ── Initialize Sigma ──────────────────────────────────────────────────
		const renderer = new Sigma( graph, container, {
			renderEdgeLabels: false,
			defaultEdgeColor:          edgeColor,
			defaultNodeColor:          '#60a5fa',
			labelColor:                { color: labelColor },
			labelBackgroundColor:      labelBg,
			labelSize:                 11,
			labelWeight:               '500',
			labelThreshold:            6,
			minCameraRatio:            0.08,
			maxCameraRatio:            5,
			nodeProgramClasses:        {},
		} );

		// ── Interactivity: click tool node → open URL ─────────────────────────
		renderer.on( 'clickNode', ( { node } ) => {
			const attrs = graph.getNodeAttributes( node );
			if ( attrs.nodeType === 'tool' && attrs.url ) {
				window.open( attrs.url, '_blank', 'noopener,noreferrer' );
			}
		} );

		// Change cursor on hover over tool nodes with URLs.
		renderer.on( 'enterNode', ( { node } ) => {
			const attrs = graph.getNodeAttributes( node );
			if ( attrs.nodeType === 'tool' && attrs.url ) {
				container.style.cursor = 'pointer';
			}
		} );
		renderer.on( 'leaveNode', () => {
			container.style.cursor = 'grab';
		} );

		// ── Tooltip on hover ──────────────────────────────────────────────────
		let tooltip = document.getElementById( 'jhub-graph-tooltip' );
		if ( ! tooltip ) {
			tooltip = document.createElement( 'div' );
			tooltip.id    = 'jhub-graph-tooltip';
			tooltip.style.cssText = [
				'position:absolute', 'pointer-events:none', 'display:none',
				'padding:6px 12px', 'border-radius:6px', 'font-size:12px',
				'font-weight:500', 'z-index:100', 'max-width:200px', 'word-break:break-word',
				isDark
					? 'background:#1e293b;color:#e2e8f0;border:1px solid #334155;'
					: 'background:#fff;color:#1e293b;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.12);',
			].join( ';' );
			container.appendChild( tooltip );
		}

		renderer.on( 'enterNode', ( { node, event } ) => {
			const attrs  = graph.getNodeAttributes( node );
			tooltip.textContent = attrs.label;
			tooltip.style.display = 'block';
		} );
		renderer.on( 'leaveNode', () => {
			tooltip.style.display = 'none';
		} );
		renderer.on( 'moveBody', ( e ) => {
			const rect = container.getBoundingClientRect();
			if ( e && e.original ) {
				tooltip.style.left = ( e.original.clientX - rect.left + 14 ) + 'px';
				tooltip.style.top  = ( e.original.clientY - rect.top  - 10 ) + 'px';
			}
		} );

		// Expose renderer for external toggle button.
		window.jhubSigma = renderer;
	} );

} )();
