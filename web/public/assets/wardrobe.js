/* Wardrobe: try on, buy and wear update the page in place, so you never lose your scroll position. */
(function () {
  'use strict';
  if (!document.getElementById('wpreview')) { return; }
  var wide = window.matchMedia && window.matchMedia('(min-width: 900px)').matches;
  var on;
  try { var v = localStorage.getItem('mfl_wsticky'); on = v === null ? wide : v === '1'; } catch (e) { on = wide; }

  function sticky() {
    var box = document.getElementById('wpreview'), btn = document.getElementById('wsticky');
    if (!box || !btn) { return; }
    box.classList.toggle('wsticky', on);
    btn.textContent = on ? 'Stop keeping on screen' : 'Keep on screen';
  }
  sticky();

  function swap(html, url, push) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    ['wpreview', 'items'].forEach(function (id) {
      var a = document.getElementById(id), b = doc.getElementById(id);
      if (a && b) { a.replaceWith(document.importNode(b, true)); }
    });
    var f = doc.querySelector('main .flash');
    var old = document.querySelector('main .flash.live');
    if (old) { old.remove(); }
    if (f) {
      var n = document.importNode(f, true); n.classList.add('live');
      var main = document.querySelector('main.wrap'); main.insertBefore(n, main.firstChild);
      setTimeout(function () { if (n.parentNode) { n.remove(); } }, 4000);
    }
    sticky();
    try { history.replaceState(null, '', url.replace(/#.*$/, '')); } catch (e) {}
  }
  function load(url, opts) {
    var y = window.scrollY;
    fetch(url, opts).then(function (r) { return r.text().then(function (t) { return { t: t, u: r.url }; }); })
      .then(function (x) { swap(x.t, x.u); window.scrollTo(0, y); })
      .catch(function () { location.href = url; });
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('#wsticky');
    if (t) {
      on = !on;
      try { localStorage.setItem('mfl_wsticky', on ? '1' : '0'); } catch (e) {}
      sticky(); return;
    }
    var a = ev.target.closest('#wpreview a.btn, #items a');
    if (a && a.href && a.href.indexOf('wardrobe') > -1 && !ev.metaKey && !ev.ctrlKey) {
      ev.preventDefault(); load(a.href);
    }
  });
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.closest || !f.closest('#wpreview, #items')) { return; }
    ev.preventDefault();
    var btn = f.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; }
    load(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin' });
  });
})();
