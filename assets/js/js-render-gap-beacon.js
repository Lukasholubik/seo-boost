/**
 * JS Render Gap beacon – snapshottuje rendered DOM a odesílá na REST endpoint.
 *
 * Rate limit: max 1× za 24h na URL (localStorage).
 * Timing: 800ms po DOMContentLoaded – dá čas page builderům dorenderovat obsah.
 */
(function () {
  'use strict';

  if (typeof seobJsGap === 'undefined') return;

  var STORAGE_KEY = 'seob_jsgap_';
  var TTL = 24 * 3600 * 1000; // 24 hodin

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

    var h1s     = [].slice.call(d.querySelectorAll('h1')).map(function (el) { return (el.innerText || el.textContent || '').trim().slice(0, 200); });
    var headings = [].slice.call(d.querySelectorAll('h1,h2,h3,h4,h5,h6')).slice(0, 30).map(function (el) {
      return { tag: el.tagName.toLowerCase(), text: (el.innerText || el.textContent || '').trim().slice(0, 150) };
    });
    var metaEl  = d.querySelector('meta[name="description"]');
    var jsonLds = d.querySelectorAll('script[type="application/ld+json"]');
    var textLen = (d.body && (d.body.innerText || d.body.textContent)) ? (d.body.innerText || d.body.textContent).length : 0;
    var links   = d.querySelectorAll('a[href]').length;

    var payload = JSON.stringify({
      path:          path.slice(0, 500),
      title:         d.title.slice(0, 200),
      h1:            h1s,
      headings:      headings,
      meta_desc:     metaEl ? (metaEl.getAttribute('content') || '').slice(0, 300) : '',
      json_ld_count: jsonLds.length,
      text_len:      textLen,
      links_count:   links,
    });

    var body = JSON.stringify({ payload: payload, nonce: seobJsGap.nonce });

    // fetch s keepalive je spolehlivější než sendBeacon mimo unload event.
    // markSent voláme až po potvrzeném 204 – nevzniká situace kde localStorage
    // označí URL jako odeslanou, ale HTTP request se nikdy nedoručil.
    if (typeof fetch !== 'undefined') {
      fetch(seobJsGap.endpoint, {
        method:    'POST',
        headers:   { 'Content-Type': 'application/json' },
        body:      body,
        keepalive: true,
      }).then(function (r) {
        if (r.status === 204 || r.ok) markSent(path);
      }).catch(function () {});
      return;
    }

    // Fallback: sendBeacon (starší prohlížeče bez fetch API)
    if (navigator.sendBeacon) {
      var blob = new Blob([body], { type: 'application/json' });
      if (navigator.sendBeacon(seobJsGap.endpoint, blob)) {
        markSent(path);
      }
    }
  }

  // 800ms zpoždění vždy – dá čas Elementoru a dalším page builderům dorenderovat obsah
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(capture, 800); });
  } else {
    setTimeout(capture, 800);
  }
}());
