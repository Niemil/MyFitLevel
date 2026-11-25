(function () {
  'use strict';
  var inp = document.getElementById('rtname'), box = document.getElementById('rtresults'), d = document.getElementById('rtdata');
  var fT = document.getElementById('rtfl-tier'), fM = document.getElementById('rtfl-muscle');
  if (!inp || !box || !d) return;
  var ex = [];
  try { ex = JSON.parse(d.textContent); } catch (e) {}
  var TL = { beginner: 'Beginner', intermediate: 'Intermediate', advanced: 'Advanced', elite: 'Elite' };
  function show() {
    var q = inp.value.trim().toLowerCase(), t = fT ? fT.value : '', m = fM ? fM.value : '';
    box.innerHTML = '';
    if (!q && !t && !m) { box.hidden = true; return; }
    var words = q ? (window.MFLSearch ? window.MFLSearch.terms(q) : q.split(/\s+/)) : [], hits = [];
    for (var i = 0; i < ex.length && hits.length < 12; i++) {
      var x = ex[i], n = (x.n + ' ' + x.e + ' ' + x.m).toLowerCase(), ok = true;
      if (t && x.t !== t) continue;
      if (m && x.m !== m) continue;
      for (var w = 0; w < words.length; w++) { if (n.indexOf(words[w]) === -1) { ok = false; break; } }
      if (ok) hits.push(x);
    }
    hits.forEach(function (h) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'rtopt';
      var l = document.createElement('span'); l.textContent = h.n;
      var r = document.createElement('small'); r.textContent = h.m + ' · ' + h.e + ' · ' + TL[h.t];
      b.appendChild(l); b.appendChild(r);
      b.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
      b.addEventListener('click', function () { inp.value = h.n; box.hidden = true; });
      box.appendChild(b);
    });
    box.hidden = !hits.length;
  }
  inp.addEventListener('input', show);
  inp.addEventListener('focus', show);
  if (fT) fT.addEventListener('change', function () { inp.focus(); show(); });
  if (fM) fM.addEventListener('change', function () { inp.focus(); show(); });
  document.addEventListener('click', function (ev) { if (ev.target !== inp && !box.contains(ev.target) && ev.target !== fT && ev.target !== fM) box.hidden = true; });
})();
