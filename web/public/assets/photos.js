/* Photo helpers: shrink big phone photos before upload, preview them, and a simple full-screen viewer. */
(function () {
  'use strict';
  var MAX = 1600;

  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < 350000) { resolve(file); return; }
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        var k = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
        var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) {
          if (!b || b.size >= file.size) { resolve(file); return; }
          resolve(new File([b], (file.name || 'photo').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.86);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  document.addEventListener('change', function (e) {
    var inp = e.target;
    if (!inp.matches || !inp.matches('input[data-photos]')) return;
    var form = inp.form, prev = form && form.querySelector('[data-preview]');
    var files = Array.prototype.slice.call(inp.files, 0, inp.dataset.max ? Number(inp.dataset.max) : 4);
    if (!files.length) { if (prev) { prev.innerHTML = ''; prev.hidden = true; } return; }
    Promise.all(files.map(shrink)).then(function (out) {
      try {
        var dt = new DataTransfer();
        out.forEach(function (f) { dt.items.add(f); });
        inp.files = dt.files;
      } catch (err) { /* old browser: upload originals */ }
      if (prev) {
        prev.innerHTML = ''; prev.hidden = false;
        out.forEach(function (f) {
          var im = document.createElement('img'); im.alt = ''; im.src = URL.createObjectURL(f); prev.appendChild(im);
        });
      }
      var auto = form && form.hasAttribute('data-autosubmit');
      if (auto) form.submit();
    });
  });

  /* lightbox */
  var dlg = null;
  function open(src) {
    if (!dlg) {
      dlg = document.createElement('dialog'); dlg.className = 'lightbox';
      dlg.innerHTML = '<img alt=""><button type="button" class="lbclose" aria-label="Close">×</button>';
      document.body.appendChild(dlg);
      dlg.addEventListener('click', function () { dlg.close(); });
    }
    dlg.querySelector('img').src = src;
    if (dlg.showModal) dlg.showModal(); else window.open(src, '_blank');
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-lightbox]') : null;
    if (!a) return;
    e.preventDefault(); open(a.href);
  });
})();
