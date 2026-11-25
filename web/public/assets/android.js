/* Android visitors get a one-time offer of the Android app. Not shown inside the app itself, in the installed PWA, or after "Not now". */
(function () {
  var ua = navigator.userAgent || '';
  if (!/Android/i.test(ua) || /MyFitLevelApp/.test(ua)) { return; }
  if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) { return; }
  if (/\/android$/.test(location.pathname) || /\/(install|admin)(\/|$)/.test(location.pathname)) { return; }
  var KEY = 'mfl_android_offer', now = Date.now(), seen = 0;
  try { seen = parseInt(localStorage.getItem(KEY) || '0', 10) || 0; } catch (e) {}
  if (seen === -1 || (seen && now - seen < 14 * 86400000)) { return; }
  var me = document.currentScript || document.querySelector('script[data-apk]');
  var apk = me ? me.getAttribute('data-apk') : '', page = me ? me.getAttribute('data-page') : '';
  if (!apk) { return; }
  function remember(v) { try { localStorage.setItem(KEY, String(v)); } catch (e) {} }
  setTimeout(function () {
    var box = document.createElement('div');
    box.className = 'apkoffer';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Android app');
    box.innerHTML = '<button class="apk-x" type="button" aria-label="Close">×</button>' +
      '<div class="apk-h"><span class="apk-ic">🤖</span><div><b>MyFitLevel for Android</b><small>Free · open source · steps via Health Connect</small></div></div>' +
      '<p>Sync your steps automatically and earn points and boss damage while you walk. Download the app straight from GitHub.</p>' +
      '<div class="apk-b"><a class="btn" href="' + apk + '">Download the app</a><a class="btn ghost" href="' + page + '">How to install</a></div>' +
      '<button class="apk-no" type="button">Don\'t show again</button>';
    document.body.appendChild(box);
    requestAnimationFrame(function () { box.classList.add('in'); });
    function close(v) { remember(v); box.classList.remove('in'); setTimeout(function () { box.remove(); }, 300); }
    box.querySelector('.apk-x').addEventListener('click', function () { close(now); });
    box.querySelector('.apk-no').addEventListener('click', function () { close(-1); });
    box.querySelector('.apk-b a').addEventListener('click', function () { remember(now); });
  }, 2500);
})();
