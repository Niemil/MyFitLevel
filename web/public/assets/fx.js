/* MyFitLevel effects: floating gym objects that react to the mouse, cursor glow, card spotlight, click bursts. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
  /* the logger and routine editor get a still background so nothing moves while you train */
  if (/\/(log|routines)(\/|$)/.test(location.pathname)) reduce = true;
  var W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 1.5);
  var mx = -9999, my = -9999, gx = -9999, gy = -9999, tick = 0, running = false;
  var cv = document.createElement('canvas');
  cv.id = 'fxbg'; cv.setAttribute('aria-hidden', 'true');
  cv.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;z-index:-1;pointer-events:none';
  document.body.insertBefore(cv, document.body.firstChild);
  var ctx = cv.getContext('2d');
  var glow = null;
  if (fine && !reduce) {
    glow = document.createElement('div'); glow.id = 'fxglow'; glow.setAttribute('aria-hidden', 'true');
    document.body.appendChild(glow);
  }
  var COLORS = ['#6c5cff', '#22d3ee', '#ff5cae', '#a78bfa'];
  function readColors() {
    var cs = getComputedStyle(document.documentElement), a = cs.getPropertyValue('--accent').trim(), b = cs.getPropertyValue('--accent2').trim(), c = cs.getPropertyValue('--fx3').trim();
    if (a && b) { COLORS = [a, b, c && c.charAt(0) === '#' ? c : a, a]; items.forEach(function (o) { o.c = COLORS[Math.floor(Math.random() * 4)]; }); }
  }
  var items = [], sil = [];

  function rnd(a, b) { return a + Math.random() * (b - a); }
  function make(init) {
    var depth = rnd(0.3, 1.2);
    return {
      x: rnd(0, W), y: init ? rnd(0, H) : H + 20, d: depth,
      s: rnd(1.2, 3.6) * depth, vx: rnd(-0.08, 0.08), vy: -rnd(0.12, 0.5) * depth,
      c: COLORS[Math.floor(Math.random() * COLORS.length)], ph: rnd(0, 6.28), ox: 0, oy: 0
    };
  }
  /* big, soft, out-of-focus gym silhouettes drawn once into small canvases */
  function silhouette(kind) {
    var c = document.createElement('canvas'); c.width = c.height = 360;
    var g = c.getContext('2d'); g.translate(180, 180);
    var gr = g.createLinearGradient(-150, -100, 150, 100); gr.addColorStop(0, '#6c5cff'); gr.addColorStop(1, '#ff5cae');
    g.fillStyle = gr;
    if (g.filter !== undefined) g.filter = 'blur(5px)';
    if (kind === 'plate') {
      g.beginPath(); g.arc(0, 0, 140, 0, 6.28); g.arc(0, 0, 38, 0, 6.28, true); g.fill('evenodd');
      g.globalAlpha = .5; g.lineWidth = 10; g.strokeStyle = '#fff'; g.beginPath(); g.arc(0, 0, 105, 0, 6.28); g.stroke();
    } else {
      g.fillRect(-150, -12, 300, 24); g.fillRect(-150, -70, 36, 140); g.fillRect(114, -70, 36, 140);
      g.fillRect(-190, -44, 30, 88); g.fillRect(160, -44, 30, 88);
    }
    return c;
  }
  function resize() {
    W = window.innerWidth; H = window.innerHeight;
    cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var n = Math.max(30, Math.min(80, Math.round(W * H / 22000)));
    while (items.length < n) items.push(make(true));
    items.length = n;
    if (!sil.length) {
      sil = [
        { c: silhouette('bar'), x: .12, y: .22, sc: 1.5, r: -.35, vr: .0004, a: .10 },
        { c: silhouette('plate'), x: .88, y: .62, sc: 1.8, r: 0, vr: -.0006, a: .09 },
        { c: silhouette('bar'), x: .7, y: .1, sc: 1.1, r: .5, vr: -.0003, a: .07 }
      ];
    }
  }

  var lastScroll = 0, skip = 0;
  window.addEventListener('scroll', function () { lastScroll = Date.now(); }, { passive: true });
  function frame() {
    if (!running) return;
    /* stand still while scrolling, and run at half rate on touch screens: keeps scrolling smooth */
    if (Date.now() - lastScroll < 160 || (!fine && (skip ^= 1))) { requestAnimationFrame(frame); return; }
    tick++;
    ctx.clearRect(0, 0, W, H);
    var light = document.documentElement.getAttribute('data-theme') === 'light';
    var mpx = mx > -999 ? (mx - W / 2) : 0, mpy = my > -999 ? (my - H / 2) : 0;
    for (var k = 0; k < sil.length; k++) {
      var q = sil[k]; q.r += q.vr;
      ctx.save();
      ctx.translate(q.x * W - mpx * .02 * (k + 1), q.y * H - mpy * .02 * (k + 1)); ctx.rotate(q.r); ctx.scale(q.sc, q.sc);
      ctx.globalAlpha = light ? q.a * .7 : q.a; ctx.drawImage(q.c, -180, -180); ctx.restore();
    }
    for (var i = 0; i < items.length; i++) {
      var o = items[i];
      o.x += o.vx + Math.sin(tick * .01 + o.ph) * .12; o.y += o.vy;
      if (fine) {
        var dx = o.x + o.ox - mx, dy = o.y + o.oy - my, dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 170 && dist > 1) { var f = (170 - dist) / 170; o.ox += dx / dist * f * 2.2; o.oy += dy / dist * f * 2.2; }
        o.ox *= 0.95; o.oy *= 0.95;
      }
      if (o.y < -20) { items[i] = make(false); continue; }
      var tw = .55 + .45 * Math.sin(tick * .03 + o.ph);
      ctx.globalAlpha = (light ? .45 : .75) * tw * Math.min(1, o.d);
      ctx.fillStyle = o.c;
      if (!light && fine) { ctx.shadowColor = o.c; ctx.shadowBlur = 10; }
      ctx.beginPath(); ctx.arc(o.x + o.ox, o.y + o.oy, o.s, 0, 6.28); ctx.fill();
      ctx.shadowBlur = 0;
    }
    ctx.globalAlpha = 1;
    if (glow) {
      gx += (mx - gx) * 0.1; gy += (my - gy) * 0.1;
      glow.style.transform = 'translate(' + (gx - 260) + 'px,' + (gy - 260) + 'px)';
    }
    requestAnimationFrame(frame);
  }
  function start() { if (running || reduce) return; running = true; requestAnimationFrame(frame); }
  function stop() { running = false; }

  resize();
  readColors();
  window.addEventListener('resize', resize);
  new MutationObserver(readColors).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'data-skin'] });
  document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
  if (reduce) { sil.forEach(function (q) { ctx.save(); ctx.globalAlpha = q.a; ctx.translate(q.x * W, q.y * H); ctx.rotate(q.r); ctx.scale(q.sc, q.sc); ctx.drawImage(q.c, -180, -180); ctx.restore(); }); } else { start(); }

  document.addEventListener('mousemove', function (e) {
    mx = e.clientX; my = e.clientY;
    var c = e.target && e.target.closest ? e.target.closest('.card') : null;
    if (c) { var r = c.getBoundingClientRect(); c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px'); }
  }, { passive: true });
  document.addEventListener('mouseleave', function () { mx = my = -9999; });

  /* click burst on buttons */
  document.addEventListener('click', function (e) {
    if (reduce || !e.target.closest) return;
    var b = e.target.closest('.btn,.fab');
    if (!b) return;
    var rect = b.getBoundingClientRect(), cx = e.clientX || rect.left + rect.width / 2, cy = e.clientY || rect.top;
    for (var i = 0; i < 10; i++) {
      var p = document.createElement('i'); p.className = 'fxp'; p.textContent = '•';
      var a = rnd(0, 6.28), d = rnd(30, 80);
      p.style.cssText = 'left:' + cx + 'px;top:' + cy + 'px;color:' + COLORS[i % 5] + ';--dx:' + Math.cos(a) * d + 'px;--dy:' + (Math.sin(a) * d - 30) + 'px';
      document.body.appendChild(p); setTimeout(function (n) { n.remove(); }.bind(null, p), 750);
    }
  });
})();
