/**
 * JS Render Gap beacon – snapshottuje rendered DOM po DOMContentLoaded
 * a odesílá na REST endpoint. Velmi lehký script (< 1.5 kB min).
 *
 * Rate limit: max 1x za 7 dní na URL (localStorage).
 */
(function () {
  'use strict';

  if (typeof seobJsGap === 'undefined') return;

  var STORAGE_KEY = 'seob_jsgap_';
  var TTL = 7 * 24 * 3600 * 1000; // 7 dní v ms

  function alreadySent(path) {
    try {
      var key = STORAGE_KEY + btoa(path).slice(0, 20);
      var ts  = parseInt(localStorage.getItem(key) || '0', 10);
      return Date.now() - ts < TTL;
    } catch (e) { return false; }
  }

  function markSent(path) {
    try {
      var key = STORAGE_KEY + btoa(path).slice(0, 20);
      localStorage.setItem(key, String(Date.now()));
    } catch (e) {}
  }

  function capture() {
    var d    = document;
    var path = location.pathname + location.search;

    if (alreadySent(path)) return;

    var h1s      = [].slice.call(d.querySelectorAll('h1')).map(function (el) { return (el.innerText || el.textContent || '').trim().slice(0, 200); });
    var headings = [].slice.call(d.querySelectorAll('h1,h2,h3,h4,h5,h6')).slice(0, 30).map(function (el) {
      return { tag: el.tagName.toLowerCase(), text: (el.innerText || el.textContent || '').trim().slice(0, 150) };
    });
    var metaEl   = d.querySelector('meta[name="description"]');
    var jsonLds  = d.querySelectorAll('script[type="application/ld+json"]');
    var textLen  = (d.body && (d.body.innerText || d.body.textContent)) ? (d.body.innerText || d.body.textContent).length : 0;
    var links    = d.querySelectorAll('a[href]').length;

    var payload = JSON.stringify({
      path:           path.slice(0, 500),
      title:          d.title.slice(0, 200),
      h1:             h1s,
      headings:       headings,
      meta_desc:      metaEl ? (metaEl.getAttribute('content') || '').slice(0, 300) : '',
      json_ld_count:  jsonLds.length,
      text_len:       textLen,
      links_count:    links,
    });

    if (navigator.sendBeacon) {
      var blob = new Blob([JSON.stringify({ payload: payload, nonce: seobJsGap.nonce })], { type: 'application/json' });
      if (navigator.sendBeacon(seobJsGap.endpoint, blob)) {
        markSent(path);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', capture);
  } else {
    // Mírné zpoždění – page buildery dokončí renderování
    setTimeout(capture, 800);
  }
}());
