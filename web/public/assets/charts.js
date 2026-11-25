(function () {
  function go() { document.querySelectorAll('.calwrap').forEach(function (e) { e.scrollLeft = e.scrollWidth; }); }
  go();
  document.querySelectorAll('details.fold').forEach(function (d) { d.addEventListener('toggle', go); });
})();
