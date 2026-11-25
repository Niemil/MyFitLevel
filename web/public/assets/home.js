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
  function fmt(n) { return n.toLocaleString('en-US'); }
  function bigFmt(n) { return n >= 1e6 ? (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M' : n >= 1e4 ? (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'k' : fmt(n); }
  function countUp(el, to, big) {
    if (calm || to < 2) return;
    var t0 = null, dur = 1400;
    function step(t) {
      if (t0 === null) t0 = t;
      var k = Math.min(1, (t - t0) / dur), v = Math.round(to * (1 - Math.pow(1 - k, 3)));
      el.textContent = big ? bigFmt(v) : fmt(v);
      if (k < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  document.querySelectorAll('[data-count]').forEach(function (el, idx) {
    countUp(el, Number(el.getAttribute('data-count')) || 0, idx === 2);
  });

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
  function reset() {
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
