/* Front page: count-up numbers, scroll reveal and the looping demo workout. */
(function () {
  'use strict';
  var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* reveal on scroll */
  var rv = document.querySelectorAll('.rv');
  if (calm || !('IntersectionObserver' in window)) {
    for (var i = 0; i < rv.length; i++) rv[i].classList.add('in');
  } else {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    for (var j = 0; j < rv.length; j++) io.observe(rv[j]);
  }

  /* count-up */
  function compact(n) {
    if (n < 10000) return Math.round(n).toLocaleString('en-US');
    var d = [[1e9, 'B'], [1e6, 'M'], [1e3, 'K']];
    for (var i = 0; i < d.length; i++) {
      if (n >= d[i][0] * 0.9995) {
        var v = n / d[i][0], dec = v < 10 ? 2 : (v < 100 ? 1 : 0);
        var t = v.toFixed(dec);
        if (t.indexOf('.') > -1) t = t.replace(/0+$/, '').replace(/\.$/, '');
        return t + d[i][1];
      }
    }
    return String(Math.round(n));
  }
  function show(el, v, mode) { el.textContent = mode === 'd1' ? (v / 10).toFixed(1) : compact(v); }
  function countUp(el, to, mode) {
    if (calm || to < 2) return;
    var t0 = null, dur = 1600;
    function step(t) {
      if (t0 === null) t0 = t;
      var k = Math.min(1, (t - t0) / dur), v = Math.round(to * (1 - Math.pow(1 - k, 3)));
      show(el, v, mode);
      if (k < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  document.querySelectorAll('[data-count]').forEach(function (el, idx) {
    var go = function () { countUp(el, Number(el.getAttribute('data-count')) || 0, el.getAttribute('data-fmt')); };
    if ('IntersectionObserver' in window) {
      var o = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { go(); o.disconnect(); } }); });
      o.observe(el);
    } else go();
  });

  /* kg <-> lb swap card */
  var wt = document.getElementById('wt'), wtu = document.getElementById('wtu');
  if (wt) {
    var wsw = document.querySelectorAll('.sc-sw button'), cur = 'kg', timer = null;
    var setUnit = function (u, anim) {
      cur = u; var to = Number(wt.getAttribute('data-' + u)) || 0;
      wtu.textContent = u;
      for (var i = 0; i < wsw.length; i++) wsw[i].classList.toggle('on', wsw[i].getAttribute('data-u') === u);
      if (calm || !anim) { wt.textContent = compact(to); return; }
      wt.classList.remove('flip'); void wt.offsetWidth; wt.classList.add('flip');
      var t0 = null;
      (function st(t) { if (t0 === null) t0 = t; var k = Math.min(1, (t - t0) / 900); wt.textContent = compact(Math.round(to * (1 - Math.pow(1 - k, 3)))); if (k < 1) requestAnimationFrame(st); })(performance.now());
    };
    var cycle = function () { timer = setInterval(function () { setUnit(cur === 'kg' ? 'lb' : 'kg', true); }, 3800); };
    for (var q = 0; q < wsw.length; q++) wsw[q].addEventListener('click', function () { clearInterval(timer); setUnit(this.getAttribute('data-u'), true); cycle(); });
    if (!calm) { setUnit('kg', true); cycle(); }
  }

  /* demo workout loop */
  var demo = document.getElementById('demo');
  if (!demo) return;
  var rows = demo.querySelectorAll('.drow'), pts = document.getElementById('dpts'), bar = document.getElementById('dbar'), up = document.getElementById('dup');
  var gain = [31, 38, 44], total = 0, stage = 0;
  function setPts(to) {
    var from = total, t0 = null;
    total = to;
    function s(t) {
      if (t0 === null) t0 = t;
      var k = Math.min(1, (t - t0) / 500);
      pts.textContent = String(Math.round(from + (to - from) * k));
      if (k < 1) requestAnimationFrame(s);
    }
    requestAnimationFrame(s);
  }
  var MOVES = [
    { k: 'bench', title: 'Push day', name: 'Bench Press', sets: ['60 kg × 8', '70 kg × 6', '75 kg × 5'] },
    { k: 'squat', title: 'Leg day', name: 'Back Squat', sets: ['80 kg × 8', '90 kg × 6', '100 kg × 5'] },
    { k: 'curl', title: 'Pull day', name: 'Dumbbell Curl', sets: ['14 kg × 10', '16 kg × 8', '18 kg × 6'] }
  ], mi = 0, exEl = demo.querySelector('.dex'), figs = demo.querySelectorAll('.mv');
  function showMove() {
    var m = MOVES[mi % MOVES.length];
    if (exEl) exEl.textContent = m.name;
    var ttl = demo.querySelector('.dhead span'); if (ttl) ttl.textContent = m.title;
    for (var a = 0; a < rows.length; a++) { var sp = rows[a].querySelector('span'); if (sp) sp.textContent = m.sets[a]; }
    for (var b = 0; b < figs.length; b++) figs[b].classList.toggle('on', figs[b].classList.contains('p-' + m.k));
  }
  function reset() {
    mi++; showMove();
    for (var i = 0; i < rows.length; i++) rows[i].classList.remove('done');
    total = 0; stage = 0; pts.textContent = '0'; bar.style.width = '30%'; up.classList.remove('show');
  }
  function tick() {
    if (stage < rows.length) {
      rows[stage].classList.add('done');
      setPts(total + gain[stage]);
      stage++;
      bar.style.width = (30 + stage * 23) + '%';
      setTimeout(tick, 1200);
    } else if (stage === rows.length) {
      stage++;
      up.classList.add('show');
      setTimeout(tick, 2600);
    } else {
      reset();
      setTimeout(tick, 900);
    }
  }
  if (calm) {
    for (var r = 0; r < rows.length; r++) rows[r].classList.add('done');
    pts.textContent = '113'; bar.style.width = '99%';
  } else {
    setTimeout(tick, 900);
  }
})();
