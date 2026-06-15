( function () {
	'use strict';

	function statusIcon( status, text ) {
		var span = document.createElement( 'span' );
		var cls = 'seob-warn';
		var glyph = '⚠';

		if ( 'good' === status ) {
			cls = 'seob-ok';
			glyph = '✔';
		} else if ( 'critical' === status ) {
			cls = 'seob-bad';
			glyph = '✖';
		}

		span.className = 'seob-status-icon ' + cls;
		span.textContent = glyph + ( text ? ' ' + text : '' );

		return span;
	}

	function renderGeneralChecks( list, checks ) {
		list.innerHTML = '';

		checks.forEach( function ( check ) {
			var li = document.createElement( 'li' );
			li.appendChild( statusIcon( check.status, check.label + ': ' + check.message ) );
			list.appendChild( li );
		} );
	}

	function renderModules( tbody, modules, onToggle ) {
		tbody.innerHTML = '';

		Object.keys( modules ).forEach( function ( moduleId ) {
			var module = modules[ moduleId ];
			var tr = document.createElement( 'tr' );

			var nameTd = document.createElement( 'td' );
			nameTd.textContent = module.label;
			tr.appendChild( nameTd );

			var descTd = document.createElement( 'td' );
			descTd.textContent = module.description;
			tr.appendChild( descTd );

			var statusTd = document.createElement( 'td' );

			if ( ! module.enabled ) {
				statusTd.appendChild( statusIcon( 'warning', 'Vypnuto' ) );
			} else if ( ! module.dependency_ok ) {
				statusTd.appendChild( statusIcon( 'critical', 'Chybí závislost' ) );
			} else {
				statusTd.appendChild( statusIcon( 'good', 'Aktivní' ) );
			}

			tr.appendChild( statusTd );

			var actionTd = document.createElement( 'td' );
			var toggleBtn = document.createElement( 'button' );
			toggleBtn.type = 'button';
			toggleBtn.className = 'button';
			toggleBtn.textContent = module.enabled ? 'Vypnout' : 'Zapnout';
			toggleBtn.addEventListener( 'click', function () {
				onToggle( moduleId, toggleBtn );
			} );
			actionTd.appendChild( toggleBtn );
			tr.appendChild( actionTd );

			tbody.appendChild( tr );
		} );
	}

	function renderChecks( container, modules, checks ) {
		container.innerHTML = '';

		Object.keys( checks ).forEach( function ( moduleId ) {
			var moduleChecks = checks[ moduleId ];

			if ( ! moduleChecks.length ) {
				return;
			}

			var heading = document.createElement( 'h3' );
			heading.textContent = modules[ moduleId ] ? modules[ moduleId ].label : moduleId;
			container.appendChild( heading );

			var ul = document.createElement( 'ul' );
			ul.className = 'seob-status-list';

			moduleChecks.forEach( function ( check ) {
				var li = document.createElement( 'li' );
				li.appendChild( statusIcon( check.status, check.label + ': ' + check.message ) );

				if ( check.action_label && check.action_url ) {
					var link = document.createElement( 'a' );
					link.href = check.action_url;
					link.textContent = check.action_label;
					li.appendChild( document.createTextNode( ' – ' ) );
					li.appendChild( link );
				}

				ul.appendChild( li );
			} );

			container.appendChild( ul );
		} );
	}

	function renderTrends( trends ) {
		document.querySelectorAll( '.seob-sparkline' ).forEach( function ( el ) {
			var moduleId = el.dataset.module;
			var key = el.dataset.key;
			var label = el.dataset.label || '';
			var data = ( trends[ moduleId ] && trends[ moduleId ][ key ] ) || [];

			seobRenderSparkline( el, data, 'Zatím nejsou k dispozici žádná data pro „' + label + '“.' );
		} );
	}

	function ajax( action, extra ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', seobData.nonce );

		Object.keys( extra || {} ).forEach( function ( key ) {
			body.append( key, extra[ key ] );
		} );

		return fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( json ) {
			if ( ! json.success ) {
				throw new Error( ( json.data && json.data.message ) || 'Neznámá chyba' );
			}

			return json.data;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var loading = document.getElementById( 'seob-status-loading' );
		var error = document.getElementById( 'seob-status-error' );
		var content = document.getElementById( 'seob-status-content' );

		if ( ! content ) {
			return;
		}

		function handleToggle( moduleId, button ) {
			button.disabled = true;

			ajax( 'seob_status_toggle_module', { module: moduleId } )
				.then( function ( data ) {
					renderModules( document.getElementById( 'seob-status-modules-body' ), data.modules, handleToggle );
					return loadStatus( false );
				} )
				.catch( function ( err ) {
					button.disabled = false;
					window.alert( 'Změna modulu selhala: ' + err.message );
				} );
		}

		function loadStatus( showLoading ) {
			if ( showLoading ) {
				loading.hidden = false;
				error.hidden = true;
				content.hidden = true;
			}

			return ajax( 'seob_status_data' )
				.then( function ( data ) {
					renderGeneralChecks( document.getElementById( 'seob-status-general' ), data.general_checks );
					renderModules( document.getElementById( 'seob-status-modules-body' ), data.modules, handleToggle );
					renderChecks( document.getElementById( 'seob-status-checks' ), data.modules, data.checks );
					renderTrends( data.trends );

					loading.hidden = true;
					content.hidden = false;
				} )
				.catch( function ( err ) {
					loading.hidden = true;
					error.hidden = false;
					error.textContent = 'Načtení stavu systému selhalo: ' + err.message;
				} );
		}

		loadStatus( true );
	} );
}() );
