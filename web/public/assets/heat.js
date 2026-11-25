(function () {
  var box = document.getElementById('heatbox');
  var raw = document.getElementById('heat-data');
  if (!box || !raw || !window.MFLBody) return;
  try { var d = JSON.parse(raw.textContent); window.MFLBody.render(box, { heat: d.heat, tips: d.tips }); } catch (e) {}
})();
