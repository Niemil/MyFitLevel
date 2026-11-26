/* MyFitLevel motion layer 2: scroll progress, reveal on scroll, 3D tilt, button ripple.
   Does nothing for reduced-motion users, and stays out of the way on the logger and routine editor. */
(function () {
  'use strict';
  var mm = window.matchMedia;
  if (mm && mm('(prefers-reduced-motion: reduce)').matches) return;
  var de = document.documentElement;
  var calm = /\/(log|routines)(\/|$)/.test(location.pathname);
  var nav = navigator, conn = nav.connection || {};
  var lite = !!conn.saveData || (nav.deviceMemory && nav.deviceMemory <= 2) || (nav.hardwareConcurrency && nav.hardwareConcurrency <= 2);
  var fine = mm && mm('(pointer: fine)').matches;

  de.classList.add('fx2');
  if (calm) de.classList.add('fx-calm');
  if (lite) de.classList.add('fx-lite');
  if (calm) return; /* nothing else runs while logging */

  /* header shadow + scroll progress */
  var bar = document.createElement('div');
  bar.id = 'fxprog'; bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);
  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var h = de.scrollHeight - de.clientHeight, y = window.scrollY || de.scrollTop || 0;
      bar.style.setProperty('--p', h > 0 ? Math.min(1, y / h).toFixed(4) : 0);
      de.classList.toggle('fx-scrolled', y > 8);
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* reveal on scroll */
  var SEL = 'main .card, main .band, main .faq details, main .sc, main .tabs, main .heroStats, main .blogcard, main .blogpost > *';
  var SKIP = '.rv, .stagger > *, dialog *, .menu-pop *, [hidden], .fx-r';
  function pick(root) {
    var list = [];
    root.querySelectorAll(SEL).forEach(function (el) {
      if (el.closest(SKIP) && !el.matches('.fx-r')) return;
      if (el.closest('.fx-r')) return; /* parent already reveals */
      list.push(el);
    });
    return list;
  }
  if ('IntersectionObserver' in window) {
    var vh = window.innerHeight, seen = 0;
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target;
        io.unobserve(el);
        el.classList.add('in');
        setTimeout(function () { el.classList.remove('fx-r', 'in'); el.style.removeProperty('--fd'); }, 1200 + (parseFloat(el.style.getPropertyValue('--fd')) || 0) * 1000);
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
    var arm = function (root) {
      pick(root).forEach(function (el) {
        var r = el.getBoundingClientRect();
        el.classList.add('fx-r');
        if (r.top < vh) { el.style.setProperty('--fd', Math.min(seen++, 7) * 0.07 + 's'); }
        io.observe(el);
      });
    };
    arm(document);
    /* safety net: never leave anything hidden */
    setTimeout(function () { document.querySelectorAll('.fx-r').forEach(function (el) { el.classList.add('in'); }); }, 3500);
    /* cards added later (infinite feed, tabs) */
    var main = document.querySelector('main');
    if (main && 'MutationObserver' in window) {
      new MutationObserver(function (ms) {
        ms.forEach(function (m) {
          m.addedNodes.forEach(function (n) {
            if (n.nodeType !== 1) return;
            if (n.matches && n.matches(SEL) && !n.closest(SKIP)) { n.classList.add('fx-r'); io.observe(n); }
            else if (n.querySelectorAll) arm(n);
          });
        });
      }).observe(main, { childList: true, subtree: true });
    }
  }

  /* 3D tilt on link tiles and stat tiles (mouse only) */
  if (fine && !lite) {
    var cur = null;
    document.addEventListener('pointermove', function (e) {
      if (e.pointerType !== 'mouse') return;
      var t = e.target.closest ? e.target.closest('a.card, .sc, .h2-demo') : null;
      if (cur && cur !== t) { cur.classList.remove('fx-hot'); cur.style.setProperty('--rx', '0deg'); cur.style.setProperty('--ry', '0deg'); }
      cur = t;
      if (!t) return;
      var r = t.getBoundingClientRect();
      if (r.width > 520 || r.height > 420) { cur = null; return; } /* big blocks stay still */
      var px = (e.clientX - r.left) / r.width - 0.5, py = (e.clientY - r.top) / r.height - 0.5;
      t.classList.add('fx-tilt', 'fx-hot');
      t.style.setProperty('--rx', (-py * 7).toFixed(2) + 'deg');
      t.style.setProperty('--ry', (px * 9).toFixed(2) + 'deg');
    }, { passive: true });
    document.addEventListener('pointerleave', function () {
      if (cur) { cur.classList.remove('fx-hot'); cur.style.setProperty('--rx', '0deg'); cur.style.setProperty('--ry', '0deg'); cur = null; }
    });
  }

  /* tap ripple on buttons */
  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest ? e.target.closest('.btn:not(.ghost)') : null;
    if (!b) return;
    var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height) * 2;
    var s = document.createElement('span');
    s.className = 'fx-rip';
    s.style.cssText = 'width:' + d + 'px;height:' + d + 'px;left:' + (e.clientX - r.left - d / 2) + 'px;top:' + (e.clientY - r.top - d / 2) + 'px';
    b.appendChild(s);
    setTimeout(function () { s.remove(); }, 650);
  }, { passive: true });
})();
