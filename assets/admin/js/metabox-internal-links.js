( function () {
	'use strict';

	if ( typeof seobLinksMetabox === 'undefined' ) {
		return;
	}

	var L            = seobLinksMetabox.i18n;
	var findBtn      = document.getElementById( 'seob-links-find-btn' );
	var candidatesEl = document.getElementById( 'seob-links-candidates' );
	var resultEl     = document.getElementById( 'seob-links-insert-result' );

	if ( ! findBtn || ! candidatesEl || ! resultEl ) {
		return;
	}

	var postId          = findBtn.dataset.postId;
	var isElementor     = false;
	var awaitingConfirm = false;
	var currentOffset   = 0;
	var allCandidates   = []; // akumulace kandidátů pro load more

	// ── Tlačítka možností ───────────────────────────────────────────────────

	function optNewWindow() {
		var el = document.getElementById( 'seob-opt-new-window' );
		return el ? el.checked : true;
	}

	function optNofollow() {
		var el = document.getElementById( 'seob-opt-nofollow' );
		return el ? el.checked : false;
	}

	// ── Fáze 1: Najít návrhy ────────────────────────────────────────────────

	findBtn.addEventListener( 'click', function () {
		allCandidates = [];
		currentOffset = 0;
		isElementor   = false;
		doFind( true );
	} );

	function doFind( reset ) {
		findBtn.disabled    = true;
		findBtn.textContent = L.finding;
		resultEl.style.display = 'none';
		awaitingConfirm = false;

		if ( reset ) {
			candidatesEl.innerHTML     = '';
			candidatesEl.style.display = 'none';
		}

		ajaxPost( 'seob_links_find', { post_id: postId, offset: currentOffset } )
			.then( function ( response ) {
				findBtn.disabled    = false;
				findBtn.textContent = L.find;

				if ( ! response.success ) {
					showResult( 'error', errMsg( response ) );
					return;
				}

				var d = response.data;
				if ( reset ) {
					isElementor = !! d.is_elementor;
				}

				// Akumulujeme kandidáty (load more nepřepíše předchozí)
				allCandidates = allCandidates.concat( d.candidates || [] );
				renderPanel( d.has_more );
			} )
			.catch( function () {
				findBtn.disabled    = false;
				findBtn.textContent = L.find;
				showResult( 'error', 'Chyba připojení.' );
			} );
	}

	// ── Fáze 2: Checklist kandidátů ─────────────────────────────────────────

	function renderPanel( hasMore ) {
		candidatesEl.innerHTML = '';

		if ( ! allCandidates.length ) {
			candidatesEl.innerHTML     = '<p class="description" style="margin:0">' + esc( L.noMatch ) + '</p>';
			candidatesEl.style.display = 'block';
			return;
		}

		// Elementor varování
		if ( isElementor ) {
			var warn = document.createElement( 'p' );
			warn.className    = 'description';
			warn.style.color  = '#996800';
			warn.style.margin = '0 0 6px';
			warn.textContent  = L.elementorWarn;
			candidatesEl.appendChild( warn );
		}

		// Checklist – všechny akumulované kandidáty
		var list = document.createElement( 'div' );
		list.className = 'seob-links-list';

		allCandidates.forEach( function ( c ) {
			var wrap = document.createElement( 'div' );
			wrap.className    = 'seob-links-candidate';
			wrap.style.cssText = 'margin-bottom:6px;padding:6px 8px;background:#f6f7f7;border-radius:3px;border:1px solid #dcdcde';

			var label = document.createElement( 'label' );
			label.style.cssText = 'display:flex;gap:7px;cursor:pointer;align-items:flex-start';

			var cb = document.createElement( 'input' );
			cb.type      = 'checkbox';
			cb.checked   = true;
			cb.value     = String( c.id );
			cb.style.cssText = 'margin-top:3px;flex-shrink:0';
			cb.addEventListener( 'change', updateInsertBtn );

			var info = document.createElement( 'span' );
			info.style.cssText = 'font-size:12px;line-height:1.4';

			var strong = document.createElement( 'strong' );
			strong.textContent = c.title;
			info.appendChild( strong );

			if ( c.context ) {
				var ctx = document.createElement( 'span' );
				ctx.style.cssText = 'display:block;color:#646970;margin-top:2px';
				ctx.innerHTML = esc( c.context ).replace(
					new RegExp( '(' + escRegex( esc( c.title ) ) + ')', 'gi' ),
					'<mark style="background:#fff3cd;padding:0 1px">$1</mark>'
				);
				info.appendChild( ctx );
			}

			label.appendChild( cb );
			label.appendChild( info );
			wrap.appendChild( label );
			list.appendChild( wrap );
		} );

		candidatesEl.appendChild( list );

		// Akce: Vybrat vše / Zrušit výběr · [Načíst více] · Vložit vybrané (X)
		var actions = document.createElement( 'div' );
		actions.style.cssText = 'display:flex;align-items:center;gap:6px;margin-top:8px;flex-wrap:wrap';

		var toggleAll = document.createElement( 'button' );
		toggleAll.type        = 'button';
		toggleAll.className   = 'button button-small';
		toggleAll.textContent = L.deselectAll;
		toggleAll.dataset.allSelected = '1';
		toggleAll.addEventListener( 'click', function () {
			var allSel = toggleAll.dataset.allSelected === '1';
			getCheckboxes().forEach( function ( cb ) { if ( ! cb.disabled ) cb.checked = ! allSel; } );
			toggleAll.dataset.allSelected = allSel ? '0' : '1';
			toggleAll.textContent         = allSel ? L.selectAll : L.deselectAll;
			updateInsertBtn();
		} );
		actions.appendChild( toggleAll );

		if ( hasMore ) {
			var loadMore = document.createElement( 'button' );
			loadMore.type      = 'button';
			loadMore.className = 'button button-small';
			loadMore.textContent = L.loadMore;
			loadMore.addEventListener( 'click', function () {
				loadMore.disabled    = true;
				loadMore.textContent = L.finding;
				currentOffset += 10;
				doFind( false );
			} );
			actions.appendChild( loadMore );
		}

		var insertBtn = document.createElement( 'button' );
		insertBtn.type      = 'button';
		insertBtn.id        = 'seob-links-insert-btn';
		insertBtn.className = 'button button-primary';
		insertBtn.style.marginLeft = 'auto';
		updateBtnLabel( insertBtn, getCheckedCount() );
		insertBtn.addEventListener( 'click', function () {
			doInsert( insertBtn );
		} );
		actions.appendChild( insertBtn );

		candidatesEl.appendChild( actions );
		candidatesEl.style.display = 'block';
	}

	// ── Fáze 3: Vložit vybrané ──────────────────────────────────────────────

	function doInsert( insertBtn ) {
		if ( isElementor && ! awaitingConfirm ) {
			awaitingConfirm     = true;
			insertBtn.className = 'button button-secondary';
			insertBtn.textContent = L.insertConfirm;
			return;
		}

		var selectedIds = getCheckboxes()
			.filter( function ( cb ) { return cb.checked && ! cb.disabled; } )
			.map( function ( cb ) { return parseInt( cb.value, 10 ); } );

		if ( ! selectedIds.length ) {
			return;
		}

		insertBtn.disabled    = true;
		insertBtn.textContent = L.inserting;
		awaitingConfirm       = false;

		ajaxPost( 'seob_links_insert', {
			post_id:    postId,
			target_ids: JSON.stringify( selectedIds ),
			force:      isElementor ? '1' : '',
			new_window: optNewWindow() ? '1' : '0',
			nofollow:   optNofollow()  ? '1' : '0',
		} )
			.then( function ( response ) {
				insertBtn.disabled = false;
				updateBtnLabel( insertBtn, getCheckedCount() );

				if ( ! response.success ) {
					showResult( 'error', errMsg( response ) );
					return;
				}

				var d = response.data;

				if ( d.inserted && d.inserted.length ) {
					// Synchronizovat editor (Gutenberg/Classic) – zabrání přepsání změn při uložení
					if ( d.new_content ) {
						syncEditorContent( d.new_content );
					}

					// Aktualizovat počty odkazů v metaboxu
					if ( typeof d.fresh_inlinks !== 'undefined' ) {
						var inEl = document.getElementById( 'seob-metabox-inlinks' );
						if ( inEl ) inEl.textContent = String( d.fresh_inlinks );
					}
					if ( typeof d.fresh_outlinks !== 'undefined' ) {
						var outEl = document.getElementById( 'seob-metabox-outlinks' );
						if ( outEl ) outEl.textContent = String( d.fresh_outlinks );
						updateLinkStatus( d.fresh_outlinks );
					}

					var names = d.inserted.map( function ( i ) { return '„' + i.title + '"'; } ).join( ', ' );
					showResult( 'success', d.message + ' (' + names + ').' );

					// Přeškrtnout vložené v checklistu
					var insertedIds = d.inserted.map( function ( i ) { return String( i.id ); } );
					getCheckboxes().forEach( function ( cb ) {
						if ( insertedIds.indexOf( cb.value ) !== -1 ) {
							cb.closest( '.seob-links-candidate' ).style.opacity = '0.4';
							cb.disabled = true;
							cb.checked  = false;
						}
					} );
					updateInsertBtn();
				} else {
					showResult( 'info', d.message || L.noMatch );
				}
			} )
			.catch( function () {
				insertBtn.disabled = false;
				updateBtnLabel( insertBtn, getCheckedCount() );
				showResult( 'error', 'Chyba připojení.' );
			} );
	}

	/**
	 * Synchronizuje stav editoru s nově uloženým obsahem, aby Gutenberg/Classic
	 * nepřepsal naše změny při dalším kliknutí Uložit.
	 */
	function syncEditorContent( content ) {
		// Gutenberg (Block Editor)
		try {
			if (
				typeof wp !== 'undefined' &&
				wp.data && wp.blocks &&
				wp.data.dispatch( 'core/block-editor' )
			) {
				wp.data.dispatch( 'core/block-editor' ).resetBlocks( wp.blocks.parse( content ) );
				return;
			}
		} catch ( e ) {}

		// Classic Editor – TinyMCE
		try {
			if ( typeof tinyMCE !== 'undefined' ) {
				var ed = tinyMCE.get( 'content' );
				if ( ed && ! ed.isHidden() ) {
					ed.setContent( content );
					return;
				}
			}
		} catch ( e ) {}

		// Classic Editor – textarea fallback
		var ta = document.getElementById( 'content' );
		if ( ta ) { ta.value = content; }
	}

	// ── SEO link status indikátor ────────────────────────────────────────────

	function updateLinkStatus( outlinks ) {
		var countsEl  = document.getElementById( 'seob-metabox-counts' );
		var statusEl  = document.getElementById( 'seob-metabox-link-status' );
		if ( ! countsEl || ! statusEl ) { return; }

		var linkMin   = parseInt( countsEl.dataset.linkMin,   10 );
		var linkMax   = parseInt( countsEl.dataset.linkMax,   10 );
		var wordCount = parseInt( countsEl.dataset.wordCount, 10 );
		var color, text;

		if ( outlinks >= linkMin && outlinks <= linkMax ) {
			color = '#00a32a';
			text  = '✓ V pořádku (doporučeno ' + linkMin + '–' + linkMax + ' pro ~' + wordCount + ' slov)';
		} else if ( outlinks < linkMin ) {
			var missing = linkMin - outlinks;
			color = '#d63638';
			text  = '↑ Chybí ' + missing + ' ' + ( missing === 1 ? 'odkaz' : 'odkazů' ) +
				' (doporučeno ' + linkMin + '–' + linkMax + ' pro ~' + wordCount + ' slov)';
		} else {
			var excess = outlinks - linkMax;
			color = '#996800';
			text  = '↓ Přebývá ' + excess + ' ' + ( excess === 1 ? 'odkaz' : 'odkazů' ) +
				' (max. ' + linkMax + ' pro ~' + wordCount + ' slov)';
		}

		statusEl.style.color = color;
		statusEl.textContent = text;
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	function getCheckboxes() {
		return Array.from( candidatesEl.querySelectorAll( 'input[type=checkbox]' ) );
	}

	function getCheckedCount() {
		return getCheckboxes().filter( function ( cb ) { return cb.checked && ! cb.disabled; } ).length;
	}

	function updateInsertBtn() {
		var btn = document.getElementById( 'seob-links-insert-btn' );
		if ( btn ) {
			var cnt = getCheckedCount();
			updateBtnLabel( btn, cnt );
			btn.disabled = cnt === 0;
		}
	}

	function updateBtnLabel( btn, count ) {
		btn.textContent = L.insertSelected.replace( '%d', count );
	}

	function ajaxPost( action, data ) {
		var fd = new FormData();
		fd.append( 'action', action );
		fd.append( 'nonce', seobLinksMetabox.nonce );
		Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
		return fetch( seobLinksMetabox.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd,
		} ).then( function ( r ) { return r.json(); } );
	}

	function showResult( type, text ) {
		resultEl.textContent   = text;
		resultEl.style.display = 'block';
		resultEl.style.color   =
			type === 'error'   ? '#d63638' :
			type === 'success' ? '#00a32a' :
			type === 'warning' ? '#996800' : '#646970';
	}

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	function escRegex( str ) {
		return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	}

	function errMsg( r ) {
		return ( r.data && r.data.message ) ? r.data.message : 'Chyba.';
	}
}() );
