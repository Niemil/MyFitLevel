/* Sets light/dark before the page paints (no flash). Preference: saved choice, else system, else light. */
(function () {
  var t = null;
  try { t = localStorage.getItem('mfl_theme'); } catch (e) {}
  if (t !== 'light' && t !== 'dark') {
    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-theme', t);
  var m = document.querySelector('meta[name=theme-color]');
  if (m) m.setAttribute('content', t === 'light' ? '#2c3e50' : '#22303e');
})();
