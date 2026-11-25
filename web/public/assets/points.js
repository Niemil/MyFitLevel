(function () {
  var box = document.getElementById('calc');
  if (!box) return;
  var scale = parseFloat(box.getAttribute('data-scale')) || 0.2085;
  var w = document.getElementById('c_w'), u = document.getElementById('c_u'), r = document.getElementById('c_r'), c = document.getElementById('c_c'), out = document.getElementById('c_out');
  function n(v) { v = parseFloat(String(v).replace(',', '.')); return isNaN(v) ? 0 : v; }
  function go() {
    var kg = u.value === 'lb' ? n(w.value) / 2.2046226218 : n(w.value);
    var reps = Math.max(0, n(r.value));
    out.textContent = reps >= 1 ? Math.max(1, Math.round(n(c.value) * Math.pow(kg * (1 + Math.min(reps, 12) / 30), 1.3) * scale)) : 0;
  }
  [w, u, r, c].forEach(function (x) { x.addEventListener('input', go); x.addEventListener('change', go); });
  go();
})();
