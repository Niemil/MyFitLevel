/* Builds a shareable picture of a workout in the browser (nothing is uploaded). */
(function () {
  'use strict';
  var box = document.getElementById('sharebox');
  if (!box) return;
  var btn = document.getElementById('sharebtn'), img = document.getElementById('shareimg');
  var D = {};
  try { D = JSON.parse(box.getAttribute('data-share') || '{}'); } catch (e) { return; }

  function rr(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
  function fit(c, t, maxW) { while (c.measureText(t).width > maxW && t.length > 4) t = t.slice(0, -2); return t; }

  function draw(wolf) {
    var W = 1080, H = 1350, cv = document.createElement('canvas'); cv.width = W; cv.height = H;
    var c = cv.getContext('2d'), F = 'system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif';
    var g = c.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#1a2140'); g.addColorStop(1, '#0a0c16'); c.fillStyle = g; c.fillRect(0, 0, W, H);
    var r = c.createRadialGradient(W / 2, 330, 40, W / 2, 330, 620); r.addColorStop(0, 'rgba(98,204,255,.30)'); r.addColorStop(1, 'rgba(98,204,255,0)'); c.fillStyle = r; c.fillRect(0, 0, W, H);
    if (wolf) c.drawImage(wolf, W / 2 - 215, 70, 430, 538);
    c.textAlign = 'center'; c.fillStyle = '#fff';
    c.font = '800 150px ' + F; c.fillText('+' + D.pts, W / 2, 770);
    c.font = '600 40px ' + F; c.fillStyle = '#9bb0d0'; c.fillText('POINTS', W / 2, 822);
    c.font = '700 54px ' + F; c.fillStyle = '#fff'; c.fillText(fit(c, D.title || 'Workout', 900), W / 2, 900);
    c.font = '500 34px ' + F; c.fillStyle = '#9bb0d0'; c.fillText(D.date + '  ·  ' + D.sets + ' sets  ·  ' + D.vol, W / 2, 950);
    var y = 1010, list = (D.ex || []).slice(0, 4);
    list.forEach(function (e) {
      c.fillStyle = 'rgba(255,255,255,.07)'; rr(c, 90, y, 900, 66, 18); c.fill();
      c.textAlign = 'left'; c.fillStyle = '#fff'; c.font = '600 32px ' + F; c.fillText(fit(c, e.n, 520), 120, y + 44);
      c.textAlign = 'right'; c.fillStyle = e.pr ? '#ffd23f' : '#62ccff'; c.font = '700 32px ' + F; c.fillText((e.pr ? 'PR  ' : '') + e.s, 960, y + 44);
      y += 78;
    });
    c.textAlign = 'center'; c.fillStyle = '#62ccff'; c.font = '700 40px ' + F; c.fillText('myfitlevel.com', W / 2, 1300);
    return cv;
  }

  function make() {
    btn.disabled = true;
    var tpl = document.getElementById('sharefito'), svg = tpl ? tpl.innerHTML.trim() : '';
    var done = function (wolf) {
      var cv = draw(wolf);
      cv.toBlob(function (blob) {
        btn.disabled = false;
        if (!blob) return;
        var url = URL.createObjectURL(blob); img.src = url; img.hidden = false;
        var file = new File([blob], 'my-workout.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          navigator.share({ files: [file], text: 'My workout on MyFitLevel' }).catch(function () {});
        } else {
          var a = document.createElement('a'); a.href = url; a.download = 'my-workout.png'; document.body.appendChild(a); a.click(); a.remove();
        }
      }, 'image/png');
    };
    if (!svg) { done(null); return; }
    var im = new Image();
    im.onload = function () { done(im); };
    im.onerror = function () { done(null); };
    im.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }
  btn.addEventListener('click', make);
})();
