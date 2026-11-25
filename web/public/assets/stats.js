(function () {
  'use strict';
  var base = (document.querySelector('meta[name=app-base]') || {}).content || '';
  var ep = base + '/stats/b';
  var sv = (document.querySelector('meta[name=sv]') || {}).content || '';
  var sc = parseInt((document.querySelector('meta[name=sc]') || {}).content || '0', 10);
  var parts = sv.split('.');

  function send(data) {
    var body = new URLSearchParams(data).toString();
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(ep, new Blob([body], { type: 'application/x-www-form-urlencoded' }));
        return;
      }
    } catch (e) {}
    try { fetch(ep, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }); } catch (e) {}
  }
  function hit(e, extra) {
    if (parts.length !== 2) return;
    var d = { e: e, i: parts[0], t: parts[1] };
    for (var k in extra || {}) d[k] = extra[k];
    send(d);
  }
  function cookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setCookie(name, val, days) {
    document.cookie = name + '=' + encodeURIComponent(val) + '; Max-Age=' + (days * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  function delCookie(name) { document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax'; }
  function randId() {
    var a = new Uint8Array(8);
    (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.prototype.map.call(a, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
  }

  /* ---- page signals: JS ran, first real interaction, time on page ---- */
  hit('load');
  var start = Date.now(), human = false, sent = false;
  function onHuman() {
    if (human) return;
    human = true;
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) { window.removeEventListener(ev, onHuman, true); });
    hit('human');
  }
  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) { window.addEventListener(ev, onHuman, { capture: true, passive: true }); });
  function dwell() {
    if (sent && Date.now() - start < 1000) return;
    sent = true;
    hit('dwell', { d: Math.round((Date.now() - start) / 1000) });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') dwell(); });
  window.addEventListener('pagehide', dwell);

  /* ---- consent banner: equal buttons, no dark patterns, never shown with DNT/GPC ---- */
  function closeBanner() { var b = document.getElementById('cbar'); if (b) b.remove(); }
  function choose(yes) {
    try { localStorage.setItem('fit_consent_seen', '1'); } catch (e) {}
    if (yes) {
      setCookie('fit_consent', 'yes', 365);
      if (!/^[a-f0-9]{16}$/.test(cookie('fit_vid'))) setCookie('fit_vid', randId(), 365);
    } else {
      setCookie('fit_consent', 'no', 365);
      delCookie('fit_vid');
    }
    send({ e: yes ? 'accept' : 'reject' });
    closeBanner();
  }
  function showBanner(force) {
    if (document.getElementById('cbar')) return;
    var bar = document.createElement('div');
    bar.id = 'cbar';
    bar.className = 'cbar';
    bar.setAttribute('role', 'dialog');
    bar.setAttribute('aria-label', 'Visitor statistics');
    bar.innerHTML = '<span>Anonymous visit stats. Accept to also recognise return visits. <a href="' + base + '/privacy#choices">Info</a></span>' +
      '<span class="cbtns"><button type="button" data-c="1">Accept</button><button type="button" data-c="0">Decline</button></span>';
    bar.addEventListener('click', function (e) {
      var c = e.target && e.target.getAttribute && e.target.getAttribute('data-c');
      if (c === '1' || c === '0') choose(c === '1');
    });
    document.body.appendChild(bar);
    if (!force) {
      try { if (!sessionStorage.getItem('cshown')) { sessionStorage.setItem('cshown', '1'); send({ e: 'shown' }); } } catch (e) { send({ e: 'shown' }); }
    }
  }
  var seen = false; try { seen = !!localStorage.getItem('fit_consent_seen'); } catch (e) {}
  if (sc === 0 && !cookie('fit_consent') && !seen) {
    setTimeout(function () { showBanner(false); }, 1200);
  }
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('[data-stats-prefs]') : null;
    if (t) { e.preventDefault(); closeBanner(); showBanner(true); }
  });
})();
