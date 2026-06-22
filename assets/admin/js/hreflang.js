( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var groupsEl     = document.getElementById( 'seob-hreflang-groups' );
	var statusEl     = document.getElementById( 'seob-hreflang-status' );
	var addBtn       = document.getElementById( 'seob-hreflang-add' );
	var validateBtn  = document.getElementById( 'seob-hreflang-validate' );
	var validationEl = document.getElementById( 'seob-hreflang-validation-result' );
	var overlay      = document.getElementById( 'seob-hreflang-modal-overlay' );
	var modalTitle   = document.getElementById( 'seob-modal-title' );
	var modalGroupId = document.getElementById( 'seob-modal-group-id' );
	var modalName    = document.getElementById( 'seob-modal-name' );
	var modalMembers = document.getElementById( 'seob-modal-members' );
	var modalSave    = document.getElementById( 'seob-modal-save' );
	var modalCancel  = document.getElementById( 'seob-modal-cancel' );
	var modalClose   = document.getElementById( 'seob-modal-close' );
	var modalError   = document.getElementById( 'seob-modal-error' );
	var addMemberBtn = document.getElementById( 'seob-modal-add-member' );

	if ( ! groupsEl || ! addBtn || ! overlay ) {
		return;
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	function ajax( action, pairs ) {
		var fd = new FormData();
		fd.append( 'action', action );
		fd.append( 'nonce', seobData.nonce );
		( pairs || [] ).forEach( function ( p ) { fd.append( p[ 0 ], p[ 1 ] ); } );
		return fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd,
		} ).then( function ( r ) { return r.json(); } );
	}

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	// ── Status banner ─────────────────────────────────────────────────────────

	function showStatus( html, color ) {
		statusEl.innerHTML = html;
		statusEl.style.cssText = 'display:block;padding:10px 14px;border-left:4px solid ' + color + ';background:#f6f7f7;margin-bottom:16px';
	}

	// ── Load & render groups ──────────────────────────────────────────────────

	function loadGroups() {
		groupsEl.innerHTML = '<p class="description">Načítám skupiny…</p>';

		ajax( 'seob_hreflang_load' ).then( function ( r ) {
			if ( ! r.success ) {
				groupsEl.innerHTML = '<p style="color:#d63638">Chyba načítání skupin.</p>';
				return;
			}

			var d = r.data;

			if ( d.conflict ) {
				showStatus(
					'⚠ Jiný plugin (Rank Math Pro nebo Yoast Premium) spravuje hreflang. Výstup tohoto modulu je deaktivován – skupiny slouží jako záloha dat.',
					'#996800'
				);
			} else if ( ! d.multilingual.active ) {
				showStatus(
					'ℹ Na tomto webu nebyl detekován vícejazyčný plugin (WPML, Polylang, TranslatePress). Ujistěte se, že web skutečně nabízí obsah ve více jazycích.',
					'#2271b1'
				);
			} else {
				showStatus(
					'✓ Detekován vícejazyčný plugin: <strong>' + esc( d.multilingual.plugin ) + '</strong>. Hreflang tagy se vkládají automaticky.',
					'#00a32a'
				);
			}

			renderGroups( d.groups );
		} ).catch( function () {
			groupsEl.innerHTML = '<p style="color:#d63638">Chyba připojení.</p>';
		} );
	}

	function renderGroups( groups ) {
		groupsEl.innerHTML = '';

		if ( ! groups || ! groups.length ) {
			groupsEl.innerHTML = '<div class="seob-empty-groups" style="padding:16px 0;color:#646970">Žádné hreflang skupiny. Klikněte na „+ Nová skupina".</div>';
			return;
		}

		groups.forEach( function ( group ) {
			groupsEl.appendChild( buildGroupCard( group ) );
		} );
	}

	function buildGroupCard( group ) {
		var card = document.createElement( 'div' );
		card.style.cssText = 'margin-bottom:10px;border-radius:3px;overflow:hidden';
		card.dataset.groupId = String( group.id );

		var header = document.createElement( 'div' );
		header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f6f7f7;border:1px solid #dcdcde;cursor:pointer;user-select:none';

		var leftSide = document.createElement( 'span' );

		var titleSpan = document.createElement( 'strong' );
		titleSpan.textContent = group.name || '(bez názvu)';

		var countSpan = document.createElement( 'span' );
		countSpan.style.cssText = 'font-size:12px;color:#646970;margin-left:10px;font-weight:normal';
		countSpan.textContent = group.members.length + ' ' + ( group.members.length === 1 ? 'jazyková verze' : 'jazykové verze' );

		leftSide.appendChild( titleSpan );
		leftSide.appendChild( countSpan );

		var actions = document.createElement( 'span' );
		actions.style.cssText = 'display:flex;gap:6px';

		var editBtn = document.createElement( 'button' );
		editBtn.type = 'button';
		editBtn.className = 'button button-small';
		editBtn.textContent = 'Upravit';
		editBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			openModal( group );
		} );

		var delBtn = document.createElement( 'button' );
		delBtn.type = 'button';
		delBtn.className = 'button button-small';
		delBtn.style.color = '#d63638';
		delBtn.textContent = 'Smazat';
		delBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( window.confirm( 'Opravdu smazat skupinu „' + group.name + '"?' ) ) {
				deleteGroup( group.id, card );
			}
		} );

		actions.appendChild( editBtn );
		actions.appendChild( delBtn );
		header.appendChild( leftSide );
		header.appendChild( actions );

		var body = document.createElement( 'div' );
		body.style.cssText = 'border:1px solid #dcdcde;border-top:none';

		if ( group.members.length ) {
			var table = document.createElement( 'table' );
			table.className = 'wp-list-table widefat';
			table.style.borderRadius = '0';

			var thead = document.createElement( 'thead' );
			thead.innerHTML = '<tr><th>Stránka</th><th style="width:90px">Locale</th><th style="width:80px;text-align:center">x-default</th><th style="width:80px">Stav</th></tr>';
			table.appendChild( thead );

			var tbody = document.createElement( 'tbody' );
			group.members.forEach( function ( m ) {
				var tr = document.createElement( 'tr' );

				var tdTitle = document.createElement( 'td' );
				var a = document.createElement( 'a' );
				a.href = m.view_link || '#';
				a.target = '_blank';
				a.rel = 'noopener';
				a.textContent = m.title;
				tdTitle.appendChild( a );
				if ( m.edit_link ) {
					tdTitle.appendChild( document.createTextNode( ' ' ) );
					var eLink = document.createElement( 'a' );
					eLink.href = m.edit_link;
					eLink.textContent = '(upravit)';
					eLink.style.cssText = 'font-size:11px;color:#646970';
					tdTitle.appendChild( eLink );
				}

				var tdLocale = document.createElement( 'td' );
				tdLocale.innerHTML = '<code>' + esc( m.locale ) + '</code>';

				var tdXd = document.createElement( 'td' );
				tdXd.style.textAlign = 'center';
				tdXd.textContent = m.is_x_default ? '✓' : '';

				var tdSt = document.createElement( 'td' );
				if ( m.post_status === 'publish' ) {
					tdSt.style.color = '#00a32a';
					tdSt.textContent = '✓ OK';
				} else {
					tdSt.style.color = '#d63638';
					tdSt.textContent = m.post_status || '?';
				}

				tr.appendChild( tdTitle );
				tr.appendChild( tdLocale );
				tr.appendChild( tdXd );
				tr.appendChild( tdSt );
				tbody.appendChild( tr );
			} );

			table.appendChild( tbody );
			body.appendChild( table );
		} else {
			body.style.padding = '10px 14px';
			body.style.color = '#646970';
			body.textContent = 'Skupina nemá žádné členy.';
		}

		var expanded = true;
		header.addEventListener( 'click', function () {
			expanded = ! expanded;
			body.style.display = expanded ? '' : 'none';
		} );

		card.appendChild( header );
		card.appendChild( body );

		return card;
	}

	// ── Delete group ──────────────────────────────────────────────────────────

	function deleteGroup( id, cardEl ) {
		ajax( 'seob_hreflang_delete', [ [ 'id', id ] ] ).then( function ( r ) {
			if ( r.success ) {
				cardEl.remove();
				if ( ! groupsEl.querySelector( '[data-group-id]' ) ) {
					groupsEl.innerHTML = '<div class="seob-empty-groups" style="padding:16px 0;color:#646970">Žádné hreflang skupiny. Klikněte na „+ Nová skupina".</div>';
				}
			}
		} );
	}

	// ── Modal ─────────────────────────────────────────────────────────────────

	function openModal( group ) {
		modalGroupId.value = group ? String( group.id ) : '';
		modalName.value    = group ? group.name : '';
		modalTitle.textContent = group ? 'Upravit skupinu' : 'Nová skupina';
		modalError.textContent = '';
		modalMembers.innerHTML = '';

		if ( group && group.members.length ) {
			group.members.forEach( function ( m ) {
				addMemberRow( m.page_id, m.title, m.locale, m.is_x_default );
			} );
		} else {
			addMemberRow( 0, '', '', false );
		}

		overlay.style.display = 'flex';
		modalName.focus();
	}

	function closeModal() {
		overlay.style.display = 'none';
	}

	// ── Member rows ───────────────────────────────────────────────────────────

	function addMemberRow( pageId, pageTitle, locale, isXDefault ) {
		var tr = document.createElement( 'tr' );

		// Page search cell
		var tdPage = document.createElement( 'td' );
		var wrap = document.createElement( 'div' );
		wrap.style.position = 'relative';

		var searchInput = document.createElement( 'input' );
		searchInput.type = 'text';
		searchInput.className = 'widefat';
		searchInput.placeholder = 'Hledat stránku…';
		searchInput.value = pageTitle || '';
		searchInput.style.fontSize = '12px';

		var hiddenId = document.createElement( 'input' );
		hiddenId.type = 'hidden';
		hiddenId.className = 'seob-member-page-id';
		hiddenId.value = pageId ? String( pageId ) : '';

		var dropdown = document.createElement( 'div' );
		dropdown.style.cssText = 'position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid #dcdcde;z-index:100;display:none;max-height:180px;overflow-y:auto;box-shadow:0 2px 8px rgba(0,0,0,.1)';

		searchInput.addEventListener( 'input', function () {
			var term = searchInput.value.trim();
			if ( term.length < 2 ) {
				dropdown.style.display = 'none';
				return;
			}
			hiddenId.value = '';

			ajax( 'seob_hreflang_search', [ [ 'term', term ] ] ).then( function ( r ) {
				dropdown.innerHTML = '';
				if ( ! r.success || ! r.data.posts.length ) {
					dropdown.style.display = 'none';
					return;
				}
				r.data.posts.forEach( function ( post ) {
					var item = document.createElement( 'div' );
					item.style.cssText = 'padding:7px 10px;cursor:pointer;font-size:12px;border-bottom:1px solid #f0f0f0';
					item.textContent = post.title;
					item.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault();
						searchInput.value = post.title;
						hiddenId.value    = String( post.id );
						dropdown.style.display = 'none';
					} );
					item.addEventListener( 'mouseenter', function () { item.style.background = '#f0f6fc'; } );
					item.addEventListener( 'mouseleave', function () { item.style.background = ''; } );
					dropdown.appendChild( item );
				} );
				dropdown.style.display = 'block';
			} );
		} );

		searchInput.addEventListener( 'blur', function () {
			setTimeout( function () { dropdown.style.display = 'none'; }, 200 );
		} );

		wrap.appendChild( searchInput );
		wrap.appendChild( hiddenId );
		wrap.appendChild( dropdown );
		tdPage.appendChild( wrap );

		// Locale cell
		var tdLocale = document.createElement( 'td' );
		var localeInput = document.createElement( 'input' );
		localeInput.type = 'text';
		localeInput.className = 'seob-member-locale small-text';
		localeInput.placeholder = 'cs';
		localeInput.value = locale || '';
		localeInput.style.width = '70px';
		tdLocale.appendChild( localeInput );

		// x-default cell
		var tdXd = document.createElement( 'td' );
		tdXd.style.textAlign = 'center';
		var xCb = document.createElement( 'input' );
		xCb.type = 'checkbox';
		xCb.className = 'seob-member-xdefault';
		xCb.checked = !! isXDefault;
		xCb.addEventListener( 'change', function () {
			if ( xCb.checked ) {
				Array.from( modalMembers.querySelectorAll( '.seob-member-xdefault' ) ).forEach( function ( cb ) {
					if ( cb !== xCb ) { cb.checked = false; }
				} );
			}
		} );
		tdXd.appendChild( xCb );

		// Remove cell
		var tdRm = document.createElement( 'td' );
		var rmBtn = document.createElement( 'button' );
		rmBtn.type = 'button';
		rmBtn.className = 'button-link';
		rmBtn.style.color = '#d63638';
		rmBtn.textContent = '×';
		rmBtn.title = 'Odebrat';
		rmBtn.addEventListener( 'click', function () { tr.remove(); } );
		tdRm.appendChild( rmBtn );

		tr.appendChild( tdPage );
		tr.appendChild( tdLocale );
		tr.appendChild( tdXd );
		tr.appendChild( tdRm );

		modalMembers.appendChild( tr );
	}

	// ── Save group ────────────────────────────────────────────────────────────

	modalSave.addEventListener( 'click', function () {
		modalError.textContent = '';

		var name = modalName.value.trim();
		if ( ! name ) {
			modalError.textContent = 'Název skupiny je povinný.';
			return;
		}

		var members = [];
		var valid   = true;

		Array.from( modalMembers.querySelectorAll( 'tr' ) ).forEach( function ( row ) {
			var pageIdEl = row.querySelector( '.seob-member-page-id' );
			var localeEl = row.querySelector( '.seob-member-locale' );
			var xdEl     = row.querySelector( '.seob-member-xdefault' );

			var pid    = pageIdEl ? parseInt( pageIdEl.value, 10 ) : 0;
			var locale = localeEl ? localeEl.value.trim() : '';

			if ( ! pid || ! locale ) {
				valid = false;
				return;
			}

			members.push( {
				page_id:      pid,
				locale:       locale,
				is_x_default: ( xdEl && xdEl.checked ) ? 1 : 0,
			} );
		} );

		if ( ! valid ) {
			modalError.textContent = 'Každý člen musí mít vybranou stránku a locale.';
			return;
		}

		if ( ! members.length ) {
			modalError.textContent = 'Přidejte alespoň jednu jazykovou verzi.';
			return;
		}

		modalSave.disabled    = true;
		modalSave.textContent = 'Ukládám…';

		ajax( 'seob_hreflang_save', [
			[ 'id',      modalGroupId.value ],
			[ 'name',    name ],
			[ 'members', JSON.stringify( members ) ],
		] ).then( function ( r ) {
			modalSave.disabled    = false;
			modalSave.textContent = 'Uložit skupinu';

			if ( ! r.success ) {
				modalError.textContent = ( r.data && r.data.message ) ? r.data.message : 'Chyba ukládání.';
				return;
			}

			closeModal();
			loadGroups();
		} ).catch( function () {
			modalSave.disabled    = false;
			modalSave.textContent = 'Uložit skupinu';
			modalError.textContent = 'Chyba připojení.';
		} );
	} );

	addMemberBtn.addEventListener( 'click', function () { addMemberRow( 0, '', '', false ); } );
	modalClose.addEventListener( 'click', closeModal );
	modalCancel.addEventListener( 'click', closeModal );
	overlay.addEventListener( 'click', function ( e ) {
		if ( e.target === overlay ) { closeModal(); }
	} );

	addBtn.addEventListener( 'click', function () { openModal( null ); } );

	// ── Validation ────────────────────────────────────────────────────────────

	validateBtn.addEventListener( 'click', function () {
		validateBtn.disabled    = true;
		validateBtn.textContent = 'Validuji…';
		validationEl.style.display = 'none';

		ajax( 'seob_hreflang_validate' ).then( function ( r ) {
			validateBtn.disabled    = false;
			validateBtn.textContent = 'Spustit validaci';

			if ( ! r.success ) { return; }

			var d = r.data;
			validationEl.style.display = 'block';

			if ( d.ok ) {
				validationEl.style.borderLeftColor = '#00a32a';
				validationEl.innerHTML = '<strong style="color:#00a32a">✓ Validace proběhla bez problémů.</strong>';
			} else {
				validationEl.style.borderLeftColor = '#d63638';
				var html = '<strong style="color:#d63638">Nalezeny problémy:</strong><ul style="margin:8px 0 0">';
				d.issues.forEach( function ( issue ) {
					html += '<li>' + esc( issue ) + '</li>';
				} );
				html += '</ul>';
				validationEl.innerHTML = html;
			}
		} ).catch( function () {
			validateBtn.disabled    = false;
			validateBtn.textContent = 'Spustit validaci';
		} );
	} );

	// ── Init ──────────────────────────────────────────────────────────────────

	loadGroups();
}() );
