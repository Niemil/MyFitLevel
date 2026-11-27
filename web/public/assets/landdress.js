(function () {
  var box = document.getElementById('dressdemo'); if (!box) { return; }
  var pick = box.querySelector('.dd-pick'), img = document.getElementById('ddimg');
  var cur = { o: 'cha', w: 'angel_gold', a: 'ring_gold' };
  pick.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-v]'); if (!b) { return; }
    var row = b.parentNode, k = row.getAttribute('data-k'); if (!k) { return; }
    [].forEach.call(row.querySelectorAll('button'), function (x) { x.classList.remove('on'); });
    b.classList.add('on');
    cur[k] = b.getAttribute('data-v');
    img.src = pick.getAttribute('data-base') + '?o=' + encodeURIComponent(cur.o) + '&w=' + encodeURIComponent(cur.w) + '&a=' + encodeURIComponent(cur.a);
  });
})();
