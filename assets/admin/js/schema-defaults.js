( function () {
	'use strict';

	if ( typeof seobData === 'undefined' ) {
		return;
	}

	var TIMEOUT_MS = 20000;

	function ajax( action, data, timeoutMs ) {
		var formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', seobData.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			formData.append( key, data[ key ] );
		} );

		var fetchOptions = {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		};

		var timeoutId = null;

		if ( timeoutMs ) {
			var controller = new AbortController();
			fetchOptions.signal = controller.signal;
			timeoutId = window.setTimeout( function () {
				controller.abort();
			}, timeoutMs );
		}

		return fetch( seobData.ajaxUrl, fetchOptions ).then( function ( response ) {
			window.clearTimeout( timeoutId );

			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}

			return response.json();
		} ).catch( function ( error ) {
			window.clearTimeout( timeoutId );
			throw error;
		} );
	}

	/**
	 * Inicializuje jednu tabulku „výchozí schéma“ (kategorie / typy obsahu).
	 *
	 * @param {Object} config
	 */
	function initTable( config ) {
		var tbody    = document.getElementById( config.tbodyId );
		var template = document.getElementById( config.templateId );

		if ( ! tbody || ! template ) {
			return;
		}

		var progress     = document.getElementById( config.progressId );
		var progressFill = document.getElementById( config.progressFillId );
		var progressText = document.getElementById( config.progressTextId );
		var saveAllBtn    = document.getElementById( config.saveAllId );
		var saveAllStatus = document.getElementById( config.saveAllStatusId );
		var rows = [];

		function saveRow( item, select, status ) {
			status.textContent = 'Ukládám…';

			var saveData = {};
			saveData[ config.itemKey ] = item[ config.itemKey ];
			saveData.value = select.value;

			return ajax( config.saveAction, saveData ).then( function ( response ) {
				status.textContent = response.success ? 'Uloženo.' : 'Chyba při ukládání.';
				return !! ( response && response.success );
			} ).catch( function () {
				status.textContent = 'Chyba při ukládání.';
				return false;
			} );
		}

		function buildRow( item, types, descriptions ) {
			var fragment = template.content.cloneNode( true );
			var row       = fragment.querySelector( 'tr' );
			var select    = row.querySelector( config.selectClass );
			var status    = row.querySelector( '.seob-save-status' );

			var nameCell = row.querySelector( config.nameClass );

			if ( item.edit_url ) {
				var nameLink = document.createElement( 'a' );
				nameLink.href   = item.edit_url;
				nameLink.target = '_blank';
				nameLink.rel    = 'noopener noreferrer';
				nameLink.textContent = item.name;
				nameCell.appendChild( nameLink );
			} else {
				nameCell.textContent = item.name;
			}

			row.querySelector( config.countClass ).textContent = item.count;

			var suggestedCell = row.querySelector( config.suggestedClass );
			suggestedCell.textContent = types[ item.suggested ] || item.suggested;

			if ( descriptions && descriptions[ item.suggested ] ) {
				suggestedCell.title = descriptions[ item.suggested ];
			}

			var defaultOption = document.createElement( 'option' );
			defaultOption.value = '';
			defaultOption.textContent = config.defaultLabel( item, types );
			if ( '' === ( item.current || '' ) ) {
				defaultOption.selected = true;
			}
			select.appendChild( defaultOption );

			Object.keys( types ).forEach( function ( key ) {
				var option = document.createElement( 'option' );
				option.value = key;
				option.textContent = types[ key ];
				if ( key === item.current ) {
					option.selected = true;
				}
				select.appendChild( option );
			} );

			var info = document.createElement( 'span' );
			info.className = 'seob-type-info dashicons dashicons-editor-help';

			function updateInfo() {
				info.title = ( select.value && descriptions && descriptions[ select.value ] ) || '';
			}

			updateInfo();
			select.addEventListener( 'change', updateInfo );
			select.insertAdjacentElement( 'afterend', info );

			row.querySelector( config.saveClass ).addEventListener( 'click', function () {
				saveRow( item, select, status );
			} );

			var resetBtn = row.querySelector( config.resetClass );

			if ( resetBtn ) {
				resetBtn.addEventListener( 'click', function () {
					select.value = '';
					updateInfo();
					saveRow( item, select, status );
				} );
			}

			rows.push( { item: item, select: select, status: status } );

			return row;
		}

		function showMessage( message ) {
			tbody.innerHTML = '';
			var row  = document.createElement( 'tr' );
			row.className = 'seob-empty-row';
			var cell = document.createElement( 'td' );
			cell.colSpan = 5;
			cell.textContent = message;
			row.appendChild( cell );
			tbody.appendChild( row );
		}

		var startTime = Date.now();

		var tickId = window.setInterval( function () {
			var elapsed = Date.now() - startTime;
			var percent = Math.min( 100, Math.round( ( elapsed / TIMEOUT_MS ) * 100 ) );

			if ( progressFill ) {
				progressFill.style.width = percent + '%';
			}

			if ( progressText ) {
				progressText.textContent = Math.round( elapsed / 1000 ) + ' s / ' + Math.round( TIMEOUT_MS / 1000 ) + ' s';
			}
		}, 250 );

		function stopProgress() {
			window.clearInterval( tickId );

			if ( progress ) {
				progress.hidden = true;
			}
		}

		ajax( config.listAction, {}, TIMEOUT_MS ).then( function ( response ) {
			stopProgress();

			var items = response && response.success ? response.data[ config.listDataKey ] : null;

			if ( ! items || ! items.length ) {
				showMessage( config.emptyMessage );
				return;
			}

			tbody.innerHTML = '';

			items.forEach( function ( item ) {
				tbody.appendChild( buildRow( item, response.data.types, response.data.descriptions ) );
			} );

			if ( saveAllBtn ) {
				saveAllBtn.disabled = false;
				saveAllBtn.addEventListener( 'click', function () {
					saveAllBtn.disabled = true;

					if ( saveAllStatus ) {
						saveAllStatus.textContent = 'Ukládám vše…';
					}

					Promise.all( rows.map( function ( row ) {
						return saveRow( row.item, row.select, row.status );
					} ) ).then( function ( results ) {
						var ok = results.filter( Boolean ).length;

						if ( saveAllStatus ) {
							saveAllStatus.textContent = 'Uloženo ' + ok + ' / ' + results.length + '.';
						}

						saveAllBtn.disabled = false;
					} );
				} );
			}
		} ).catch( function ( error ) {
			stopProgress();

			if ( 'AbortError' === error.name ) {
				showMessage( 'Vypršel časový limit požadavku (20 s). Server na požadavek vůbec neodpověděl – zkontrolujte, zda něco neblokuje admin-ajax.php (např. plugin kontrolující licenci přes internet bez připojení).' );
				return;
			}

			showMessage( config.errorPrefix + error.message );
			// eslint-disable-next-line no-console
			console.error( config.listAction, error );
		} );
	}

	initTable( {
		tbodyId:        'seob-posttype-body',
		templateId:     'seob-posttype-row-template',
		progressId:     'seob-posttype-progress',
		progressFillId: 'seob-posttype-progress-fill',
		progressTextId: 'seob-posttype-progress-text',
		listAction:     'seob_schema_post_types_list',
		saveAction:     'seob_schema_post_type_save',
		itemKey:        'post_type',
		listDataKey:    'post_types',
		nameClass:      '.seob-posttype-name',
		countClass:     '.seob-posttype-count',
		selectClass:    '.seob-posttype-select',
		suggestedClass: '.seob-posttype-suggested',
		saveClass:      '.seob-posttype-save',
		resetClass:     '.seob-posttype-reset',
		saveAllId:       'seob-posttype-save-all',
		saveAllStatusId: 'seob-posttype-save-all-status',
		emptyMessage:   'Žádné typy obsahu nenalezeny.',
		errorPrefix:    'Chyba při načítání typů obsahu: ',
		defaultLabel: function ( item, types ) {
			var rmLabel = types[ item.rm_default ] || item.rm_default;
			return 'Výchozí (dle Rank Math: ' + rmLabel + ')';
		}
	} );

	initTable( {
		tbodyId:        'seob-schema-categories-body',
		templateId:     'seob-schema-category-row-template',
		progressId:     'seob-schema-progress',
		progressFillId: 'seob-schema-progress-fill',
		progressTextId: 'seob-schema-progress-text',
		listAction:     'seob_schema_categories_list',
		saveAction:     'seob_schema_category_save',
		itemKey:        'term_id',
		listDataKey:    'categories',
		nameClass:      '.seob-schema-cat-name',
		countClass:     '.seob-schema-cat-count',
		selectClass:    '.seob-schema-cat-select',
		suggestedClass: '.seob-schema-cat-suggested',
		saveClass:      '.seob-schema-cat-save',
		resetClass:     '.seob-schema-cat-reset',
		saveAllId:       'seob-schema-cat-save-all',
		saveAllStatusId: 'seob-schema-cat-save-all-status',
		emptyMessage:   'Žádné kategorie nenalezeny.',
		errorPrefix:    'Chyba při načítání kategorií: ',
		defaultLabel: function () {
			return 'Výchozí (dle typu obsahu / Rank Math)';
		}
	} );
}() );
