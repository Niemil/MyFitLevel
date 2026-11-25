(function () {
  var t = document.getElementById('b-title'), d = document.getElementById('b-desc'), b = document.getElementById('b-body'), l = document.getElementById('b-seo-list');
  if (!t || !l) return;
  function run() {
    var body = b.value, words = (body.match(/\S+/g) || []).length;
    var h2 = (body.match(/^##\s/gm) || []).length, links = (body.match(/\]\(\//g) || []).length;
    var items = [
      [t.value.length >= 30 && t.value.length <= 65, 'Title length ' + t.value.length + ' (aim for 30 to 65 characters, with the main keyword near the start)'],
      [d.value.length >= 110 && d.value.length <= 160, 'Description length ' + d.value.length + ' (aim for 120 to 160 characters)'],
      [words >= 500, words + ' words (guides tend to do best at 600+; useful beats long)'],
      [h2 >= 2, h2 + ' sub-headings (use ## to break the text up)'],
      [links >= 2, links + ' internal links (link to /register, /points, /fitocracy-alternative and other posts)']
    ];
    l.innerHTML = '';
    items.forEach(function (i) { var li = document.createElement('li'); li.textContent = (i[0] ? '✅ ' : '⚠️ ') + i[1]; l.appendChild(li); });
  }
  [t, d, b].forEach(function (x) { x.addEventListener('input', run); });
  run();
})();
