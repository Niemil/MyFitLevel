(function () {
  'use strict';
  Array.prototype.forEach.call(document.querySelectorAll('[data-ba]'), function (box) {
    var rng = box.querySelector('.barange');
    function set() { box.style.setProperty('--pos', rng.value + '%'); }
    rng.addEventListener('input', set); set();
  });
})();
