(function () {
  try {
    var y = sessionStorage.getItem('keepY');
    if (y !== null) {
      sessionStorage.removeItem('keepY'); y = parseInt(y, 10);
      var go = function () { window.scrollTo(0, y); };
      go(); window.addEventListener('load', go); setTimeout(go, 150); setTimeout(go, 600);
    }
  } catch (e) {}
  document.addEventListener('submit', function () { try { sessionStorage.setItem('keepY', String(window.scrollY)); } catch (e) {} }, true);
})();
