(function () {
  var box = document.getElementById('wpreview'), btn = document.getElementById('wsticky');
  if (!box || !btn) { return; }
  var wide = window.matchMedia && window.matchMedia('(min-width: 900px)').matches;
  var on;
  try { var v = localStorage.getItem('mfl_wsticky'); on = v === null ? wide : v === '1'; } catch (e) { on = wide; }
  function apply() {
    box.classList.toggle('wsticky', on);
    btn.textContent = on ? 'Stop keeping on screen' : 'Keep on screen';
  }
  btn.addEventListener('click', function () {
    on = !on;
    try { localStorage.setItem('mfl_wsticky', on ? '1' : '0'); } catch (e) {}
    apply();
  });
  apply();
})();
