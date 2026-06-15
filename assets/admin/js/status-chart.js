/**
 * Jednoduchý SVG sparkline graf bez závislostí.
 */

/**
 * Vykreslí sparkline graf do daného kontejneru.
 *
 * @param {HTMLElement} containerEl Element, do kterého se vykreslí SVG.
 * @param {Array<{recorded_at: string, value: number}>} dataPoints Datové body (vzestupně podle času).
 * @param {string} [emptyLabel] Text zobrazený, pokud nejsou žádná data.
 */
function seobRenderSparkline( containerEl, dataPoints, emptyLabel ) {
	containerEl.innerHTML = '';

	if ( ! dataPoints || dataPoints.length === 0 ) {
		var empty = document.createElement( 'p' );
		empty.className = 'description';
		empty.textContent = emptyLabel || 'Zatím nejsou k dispozici žádná data.';
		containerEl.appendChild( empty );
		return;
	}

	var width = 320;
	var height = 60;
	var padding = 4;

	var values = dataPoints.map( function ( point ) {
		return point.value;
	} );

	var min = Math.min.apply( null, values );
	var max = Math.max.apply( null, values );
	var range = max - min;

	if ( range === 0 ) {
		range = 1;
	}

	var step = dataPoints.length > 1 ? ( width - padding * 2 ) / ( dataPoints.length - 1 ) : 0;

	var points = dataPoints.map( function ( point, index ) {
		var x = padding + index * step;
		var y = height - padding - ( ( point.value - min ) / range ) * ( height - padding * 2 );
		return x + ',' + y;
	} );

	var svgNS = 'http://www.w3.org/2000/svg';
	var svg = document.createElementNS( svgNS, 'svg' );
	svg.setAttribute( 'viewBox', '0 0 ' + width + ' ' + height );
	svg.setAttribute( 'width', String( width ) );
	svg.setAttribute( 'height', String( height ) );
	svg.classList.add( 'seob-sparkline-svg' );

	var polyline = document.createElementNS( svgNS, 'polyline' );
	polyline.setAttribute( 'points', points.join( ' ' ) );
	polyline.setAttribute( 'fill', 'none' );
	polyline.setAttribute( 'stroke', '#2271b1' );
	polyline.setAttribute( 'stroke-width', '2' );
	svg.appendChild( polyline );

	containerEl.appendChild( svg );

	var last = dataPoints[ dataPoints.length - 1 ];
	var caption = document.createElement( 'p' );
	caption.className = 'description';
	caption.textContent = 'Poslední hodnota: ' + last.value;
	containerEl.appendChild( caption );
}
