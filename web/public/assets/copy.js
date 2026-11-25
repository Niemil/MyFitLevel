(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-copy]') : null;
    if (!b) { return; }
    var text = b.getAttribute('data-copy') || '';
    var done = function () { var o = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = o; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallback(); });
    } else { fallback(); }
    function fallback() {
      var t = document.createElement('textarea'); t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
      document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
      document.body.removeChild(t);
    }
  });
})();
