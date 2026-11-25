(function () {
  'use strict';
  var base = (document.querySelector('meta[name=app-base]') || {}).content || '';
  var csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';

  /* ---------- confirm dialogs ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset && f.dataset.confirm && !window.confirm(f.dataset.confirm)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  /* ---------- ajax forms: props + follow ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.hasAttribute || !f.hasAttribute('data-ajax') || e.defaultPrevented) return;
    e.preventDefault();
    var fd = new FormData(f);
    fd.append('ajax', '1');
    fetch(f.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { alert(d.error || 'Something went wrong'); return; }
        var pb = f.querySelector('[data-propbtn]');
        if (pb) {
          pb.classList.toggle('on', !!d.on);
          var c = pb.querySelector('[data-count]');
          if (c) c.textContent = d.count;
        }
        var fb = f.querySelector('[data-followbtn]');
        if (fb) {
          fb.textContent = d.on ? 'Following' : 'Follow';
          fb.classList.toggle('ghost', !!d.on);
        }
      })
      .catch(function () { f.removeAttribute('data-ajax'); f.submit(); });
  });

  /* ---------- service worker + install button ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(function () {});
    });
  }
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    var pop = document.querySelector('.menu-pop');
    if (pop && !pop.querySelector('[data-install]')) {
      var b = document.createElement('button');
      b.type = 'button'; b.setAttribute('data-install', '1'); b.textContent = '📲 Install app';
      b.addEventListener('click', function () { deferredPrompt.prompt(); deferredPrompt = null; b.remove(); });
      pop.insertBefore(b, pop.firstChild);
    }
  });

  /* ---------- light / dark ---------- */
  var tb = document.getElementById('themebtn');
  if (tb) tb.addEventListener('click', function () {
    var cur = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    var nx = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', nx);
    try { localStorage.setItem('mfl_theme', nx); } catch (e) {}
    var m = document.querySelector('meta[name=theme-color]');
    if (m) m.setAttribute('content', nx === 'light' ? '#2c3e50' : '#22303e');
  });

  /* ---------- beta banner (dismissible) ---------- */
  var bb = document.getElementById('betabar');
  if (bb) {
    var gone = false;
    try { gone = localStorage.getItem('mfl_beta_hidden') === '1'; } catch (e) {}
    if (!gone) bb.hidden = false;
    var bc = document.getElementById('betaclose');
    if (bc) bc.addEventListener('click', function () { bb.hidden = true; try { localStorage.setItem('mfl_beta_hidden', '1'); } catch (e) {} });
  }

  /* ---------- confetti on level up ---------- */
  function confetti() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var c = document.createElement('canvas');
    c.className = 'confetti';
    c.width = window.innerWidth; c.height = window.innerHeight;
    document.body.appendChild(c);
    var g = c.getContext('2d');
    var cols = ['#9a6bff', '#2ad4a1', '#ffd23f', '#ff7aa8', '#5b8cff'];
    var ps = [];
    for (var i = 0; i < 140; i++) {
      ps.push({ x: c.width / 2 + (Math.random() - .5) * 120, y: c.height * .35, vx: (Math.random() - .5) * 14, vy: -Math.random() * 14 - 4,
        s: 4 + Math.random() * 6, r: Math.random() * 6, vr: (Math.random() - .5) * .4, c: cols[i % cols.length] });
    }
    var t0 = Date.now();
    (function tick() {
      var dt = Date.now() - t0;
      g.clearRect(0, 0, c.width, c.height);
      ps.forEach(function (p) {
        p.vy += .42; p.x += p.vx; p.y += p.vy; p.vx *= .99; p.r += p.vr;
        g.save(); g.translate(p.x, p.y); g.rotate(p.r); g.fillStyle = p.c; g.globalAlpha = Math.max(0, 1 - dt / 3200);
        g.fillRect(-p.s / 2, -p.s / 2, p.s, p.s * .6); g.restore();
      });
      if (dt < 3200) requestAnimationFrame(tick); else c.remove();
    })();
  }
  var wins = document.querySelectorAll('.flash.win');
  for (var w = 0; w < wins.length; w++) { if (/LEVEL UP/.test(wins[w].textContent)) { confetti(); break; } }
  /* ---------- generic helpers (CSP-safe, no inline JS) ---------- */
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t && t.matches && t.matches('select[data-autosubmit]') && t.form) t.form.submit();
  });
  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (b && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });
})();
