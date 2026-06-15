( function () {
	'use strict';

	var settingsForm = document.getElementById( 'seob-smart-indexing-settings-form' );
	if ( ! settingsForm ) {
		return;
	}

	var saveBtn      = document.getElementById( 'seob-si-save-settings' );
	var saveStatus   = document.getElementById( 'seob-si-settings-status' );
	var scanBtn      = document.getElementById( 'seob-si-run-scan' );
	var scanStatus   = document.getElementById( 'seob-si-scan-status' );
	var resultsBody  = document.getElementById( 'seob-si-results-body' );
	var rowTemplate  = document.getElementById( 'seob-si-row-template' );

	var PAGE_TYPE_LABELS = {
		company_detail: 'Detail firmy',
		category: 'Obor',
		category_city: 'Obor + lokalita',
		service_city: 'Služba + lokalita'
	};

	var RECOMMENDATIONS = {
		'A:rule_category': 'Indexovat (hlavní obor)',
		'A:rule_min_companies': 'Indexovat',
		'A:rule_complete_profile': 'Indexovat (kompletní profil)',
		'A:approved_manual': 'Indexovat (schváleno ručně)',
		'B:candidate': 'Kandidát – schválit ručně?',
		'B:candidate_manual_review': 'Kandidát (služba × lokalita) – schválit ručně?',
		'B:too_few': 'Noindex (málo firem)',
		'B:rule_thin_profile': 'Noindex (nekompletní profil)',
		'B:demoted_manual': 'Noindex (odmítnuto ručně)'
	};

	function ajax( action, data ) {
		var body = new URLSearchParams( Object.assign( { action: action, nonce: seobData.nonce }, data || {} ) );

		return fetch( seobData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function fillSelect( select, options ) {
		var current = select.getAttribute( 'data-current' ) || '';
		select.innerHTML = '';

		var emptyOption = document.createElement( 'option' );
		emptyOption.value = '';
		emptyOption.textContent = '— nevybráno —';
		select.appendChild( emptyOption );

		options.forEach( function ( option ) {
			var el = document.createElement( 'option' );
			el.value = option.value;
			el.textContent = option.label;
			if ( option.value === current ) {
				el.selected = true;
			}
			select.appendChild( el );
		} );
	}

	function loadOptions() {
		ajax( 'seob_smart_index_options' ).then( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			fillSelect( document.getElementById( 'seob-si-company-post-type' ), response.data.post_types );
			fillSelect( document.getElementById( 'seob-si-category-taxonomy' ), response.data.taxonomies );
			fillSelect( document.getElementById( 'seob-si-location-taxonomy' ), response.data.taxonomies );
			fillSelect( document.getElementById( 'seob-si-service-taxonomy' ), response.data.taxonomies );
		} );
	}

	function saveSettings() {
		saveStatus.textContent = 'Ukládám…';

		var data = {
			profile: document.getElementById( 'seob-si-profile' ).value,
			mode: document.getElementById( 'seob-si-mode' ).value,
			company_post_type: document.getElementById( 'seob-si-company-post-type' ).value,
			category_taxonomy: document.getElementById( 'seob-si-category-taxonomy' ).value,
			location_taxonomy: document.getElementById( 'seob-si-location-taxonomy' ).value,
			service_taxonomy: document.getElementById( 'seob-si-service-taxonomy' ).value,
			min_companies: document.getElementById( 'seob-si-min-companies' ).value,
			completeness_threshold: document.getElementById( 'seob-si-completeness' ).value,
			max_depth: document.getElementById( 'seob-si-max-depth' ).value,
			blacklist_params: document.getElementById( 'seob-si-blacklist' ).value
		};

		ajax( 'seob_smart_index_save_settings', data ).then( function ( response ) {
			saveStatus.textContent = response.success ? 'Uloženo.' : 'Chyba při ukládání.';
			setTimeout( function () { saveStatus.textContent = ''; }, 3000 );
		} );
	}

	function renderResults( results ) {
		resultsBody.innerHTML = '';

		if ( ! results || ! results.length ) {
			var emptyRow = document.createElement( 'tr' );
			emptyRow.className = 'seob-empty-row';
			var emptyCell = document.createElement( 'td' );
			emptyCell.colSpan = 6;
			emptyCell.textContent = 'Žádné návrhy. Zkontrolujte mapování a spusťte analýzu.';
			emptyRow.appendChild( emptyCell );
			resultsBody.appendChild( emptyRow );
			return;
		}

		results.forEach( function ( item ) {
			var row = rowTemplate.content.cloneNode( true ).querySelector( 'tr' );

			var link = document.createElement( 'a' );
			link.href = item.url;
			link.target = '_blank';
			link.rel = 'noopener';
			link.textContent = item.url;
			row.querySelector( '.seob-col-url' ).appendChild( link );

			row.querySelector( '.seob-si-type' ).textContent = PAGE_TYPE_LABELS[ item.page_type ] || item.page_type;
			row.querySelector( '.seob-si-count' ).textContent = null === item.result_count ? '—' : item.result_count;
			row.querySelector( '.seob-si-score' ).textContent = null === item.score ? '—' : item.score;

			var key = item.tier + ':' + item.tier_reason;
			row.querySelector( '.seob-si-recommendation' ).textContent = RECOMMENDATIONS[ key ] || ( item.tier === 'A' ? 'Indexovat' : 'Noindex' );

			var approveBtn = row.querySelector( '.seob-si-approve' );
			var demoteBtn  = row.querySelector( '.seob-si-demote' );

			approveBtn.disabled = 'A' === item.tier;
			demoteBtn.disabled  = 'B' === item.tier;

			approveBtn.addEventListener( 'click', function () {
				ajax( 'seob_smart_index_approve', { id: item.id } ).then( function ( response ) {
					if ( response.success ) {
						renderResults( response.data.results );
					}
				} );
			} );

			demoteBtn.addEventListener( 'click', function () {
				ajax( 'seob_smart_index_demote', { id: item.id } ).then( function ( response ) {
					if ( response.success ) {
						renderResults( response.data.results );
					}
				} );
			} );

			resultsBody.appendChild( row );
		} );
	}

	function loadResults() {
		ajax( 'seob_smart_index_results' ).then( function ( response ) {
			if ( response.success ) {
				renderResults( response.data.results );
			}
		} );
	}

	function runScan() {
		scanStatus.textContent = 'Analyzuji…';
		scanBtn.disabled = true;

		ajax( 'seob_smart_index_scan' ).then( function ( response ) {
			scanBtn.disabled = false;

			if ( response.success ) {
				scanStatus.textContent = 'Hotovo – ' + response.data.scanned + ' návrhů.';
				renderResults( response.data.results );
			} else {
				scanStatus.textContent = 'Chyba při analýze.';
			}

			setTimeout( function () { scanStatus.textContent = ''; }, 4000 );
		} );
	}

	saveBtn.addEventListener( 'click', saveSettings );
	scanBtn.addEventListener( 'click', runScan );

	loadOptions();
	loadResults();
} )();
