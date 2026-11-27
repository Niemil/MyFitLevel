(function () {
  'use strict';
  var box = document.getElementById('dressdemo'); if (!box) { return; }
  var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var pick = box.querySelector('.dd-pick'), stage = document.getElementById('ddstage');
  var layers = stage.querySelectorAll('.dd-layer'), burst = stage.querySelector('.dd-burst');
  var base = pick.getAttribute('data-base');
  var cur = { o: 'cha', w: 'angel_gold', a: 'ring_gold' }, front = 0, token = 0, auto = true, seen = false;
  var cache = {};

  function setOn(k, v) {
    var row = pick.querySelector('.dd-row[data-k="' + k + '"]'); if (!row) { return; }
    [].forEach.call(row.querySelectorAll('button'), function (b) { b.classList.toggle('on', b.getAttribute('data-v') === v); });
  }
  function show() {
    var url = base + '?o=' + encodeURIComponent(cur.o) + '&w=' + encodeURIComponent(cur.w) + '&a=' + encodeURIComponent(cur.a), my = ++token;
    var go = function (svg) {
      if (my !== token) { return; }
      var nxt = layers[1 - front];
      nxt.innerHTML = svg;
      nxt.classList.add('on'); layers[front].classList.remove('on');
      front = 1 - front;
      if (!calm) { burst.classList.remove('go'); void burst.offsetWidth; burst.classList.add('go'); }
    };
    if (cache[url]) { go(cache[url]); return; }
    fetch(url).then(function (r) { return r.text(); }).then(function (t) { cache[url] = t; go(t); }).catch(function () {});
  }
  pick.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-v]'); if (!b) { return; }
    var k = b.parentNode.getAttribute('data-k'); if (!k) { return; }
    auto = false;
    cur[k] = b.getAttribute('data-v'); setOn(k, cur[k]); show();
  });
  show();

  /* the demo changes outfit by itself until the visitor takes over */
  var outfits = ['wan', 'iro', 'cha', 'myt', 'sto', 'sam', 'vik', 'mag', 'nin', 'pir', 'dra', 'rea'];
  var wings = ['', 'angel_gold', 'bat_obsidian', 'dragon_crimson', 'fairy_emerald'];
  var auras = ['', 'ring_gold', 'flame_lava', 'sparks_sakura', 'glow_mythril'];
  var step = 0;
  function visible() { var r = box.getBoundingClientRect(); return r.top < window.innerHeight && r.bottom > 0; }
  if (!calm) {
    setInterval(function () {
      if (!auto || !visible() || document.hidden) { return; }
      step++;
      cur.o = outfits[(outfits.indexOf(cur.o) + 1) % outfits.length];
      if (step % 2 === 0) { cur.w = wings[(step / 2) % wings.length]; }
      if (step % 3 === 0) { cur.a = auras[(step / 3) % auras.length]; }
      setOn('o', cur.o); setOn('w', cur.w); setOn('a', cur.a); show();
    }, 3200);
  }

  /* muscle bars fill, one muscle levels up, then the week fills and the bonus counts up */
  var mus = [].slice.call(document.querySelectorAll('#ddmus > div')), dots = [].slice.call(document.querySelectorAll('#ddweek .dd-dots span'));
  var bonusEl = document.getElementById('ddbonus');
  function setBar(m, lv) { m.querySelector('b').style.width = (lv * 10) + '%'; m.querySelector('u').textContent = lv; }
  function reset() {
    mus.forEach(function (m) { m.classList.remove('lu'); m.querySelector('b').style.width = '0%'; m.querySelector('u').textContent = m.getAttribute('data-lv'); });
    dots.forEach(function (d) { d.classList.remove('on'); }); bonusEl.textContent = '0';
  }
  function count(to, ms) {
    var t0 = null;
    function f(t) { if (t0 === null) { t0 = t; } var p = Math.min(1, (t - t0) / ms); bonusEl.textContent = Math.round(to * p); if (p < 1) { requestAnimationFrame(f); } }
    requestAnimationFrame(f);
  }
  var runId = 0;
  function cycle() {
    var id = ++runId, t = 0;
    function at(ms, fn) { setTimeout(function () { if (id === runId) { fn(); } }, ms); }
    reset();
    if (calm) {
      mus.forEach(function (m) { setBar(m, +m.getAttribute('data-lv')); }); dots.forEach(function (d) { d.classList.add('on'); }); bonusEl.textContent = '12'; return;
    }
    at(150, function () { mus.forEach(function (m) { setBar(m, +m.getAttribute('data-lv')); }); });
    var pickI = Math.floor(Math.random() * mus.length);
    at(1900, function () { var m = mus[pickI]; m.classList.add('lu'); setBar(m, +m.getAttribute('data-lv') + 1); });
    dots.forEach(function (d, i) { at(3200 + i * 380, function () { d.classList.add('on'); }); });
    at(3200, function () { count(12, 2300); });
    at(9500, cycle);
  }
  if ('IntersectionObserver' in window && !calm) {
    new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting && !seen) { seen = true; cycle(); } }); }, { threshold: 0.25 }).observe(box);
  } else { cycle(); }
})();
