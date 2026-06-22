/* SEO Booster Pro – Core Web Vitals beacon (< 2 kB) */
/* global webVitals, seobCwv */
(function () {
  'use strict';

  if (typeof webVitals === 'undefined' || typeof seobCwv === 'undefined') return;
  if (typeof window === 'undefined' || !window.location) return;

  var endpoint = seobCwv.endpoint;
  var path     = window.location.pathname.replace(/\?[\s\S]*/, '').slice(0, 500) || '/';
  var device   = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent || '') ? 'mobile' : 'desktop';

  function send(m) {
    var payload = {
      metric: m.name,
      value:  Math.round(m.name === 'CLS' ? m.value * 10000 : m.value) / (m.name === 'CLS' ? 10000 : 1),
      rating: m.rating,
      path:   path,
      device: device,
    };

    if (m.name === 'LCP' && m.attribution) {
      var el = (m.attribution.lcpEntry && m.attribution.lcpEntry.element) || m.attribution.element;
      if (el && el.tagName) {
        payload.lcp_element = (el.tagName.toLowerCase() +
          (el.id ? '#' + el.id : '') +
          (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0,3).join('.') : '')
        ).slice(0, 200);
      }
    }

    var body = JSON.stringify(payload);
    if (navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
    } else {
      fetch(endpoint, { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, keepalive: true });
    }
  }

  webVitals.onLCP(send,  { reportAllChanges: false });
  webVitals.onINP(send,  { reportAllChanges: false });
  webVitals.onCLS(send,  { reportAllChanges: false });
  webVitals.onFCP(send,  { reportAllChanges: false });
  webVitals.onTTFB(send, { reportAllChanges: false });
})();
