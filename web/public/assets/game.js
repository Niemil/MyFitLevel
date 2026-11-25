(function () {
  'use strict';
  var cards = document.querySelectorAll('.gboss[data-state]');
  if (!cards.length) { return; }

  function fmt(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

  function paint(card, d, first) {
    if (!d || !d.id) { return; }
    var bar = card.querySelector('.gb-hp i');
    var hpEl = card.querySelector('[data-hp]');
    var left = card.querySelector('[data-left]');
    var pct = Math.max(0, Math.min(100, d.hp / Math.max(1, d.max) * 100));
    var old = parseInt(card.getAttribute('data-now') || '-1', 10);
    if (bar) { bar.style.width = pct + '%'; }
    if (hpEl) { hpEl.textContent = fmt(d.hp); }
    if (left && d.left) { left.textContent = d.left; }
    card.classList.toggle('gb-low', pct <= 25);
    if (!first && old > d.hp) {
      var dmg = old - d.hp;
      var f = document.createElement('span');
      f.className = 'gb-float';
      f.textContent = '-' + fmt(dmg);
      var art = card.querySelector('.gb-art');
      if (art) { art.appendChild(f); setTimeout(function () { f.remove(); }, 1600); }
      card.classList.remove('gb-hit');
      void card.offsetWidth;
      card.classList.add('gb-hit');
    }
    card.setAttribute('data-now', String(d.hp));
  }

  function poll(card, first) {
    fetch(card.getAttribute('data-state'), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) { paint(card, d, first); })
      .catch(function () {});
  }

  [].forEach.call(cards, function (card) {
    var bar = card.querySelector('.gb-hp i');
    if (bar) {
      var target = bar.style.width;
      bar.style.width = '100%';
      setTimeout(function () { bar.style.width = target; }, 120);
    }
    var hp = card.querySelector('[data-hp]');
    if (hp) { card.setAttribute('data-now', hp.textContent.replace(/,/g, '')); }
    setInterval(function () { if (!document.hidden) { poll(card, false); } }, 20000);
  });
})();
