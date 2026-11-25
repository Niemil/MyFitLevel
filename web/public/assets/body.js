/* MyFitLevel body map: tappable muscle regions (front + back). Used by the exercise picker and the heat map. */
(function () {
  var NS = 'http://www.w3.org/2000/svg';
  var LABEL = {
    chest: 'Chest', shoulders: 'Shoulders', biceps: 'Biceps', triceps: 'Triceps', forearms: 'Forearms', abs: 'Abs',
    obliques: 'Obliques', quads: 'Quads', calves: 'Calves', traps: 'Traps', lats: 'Lats', lowback: 'Lower back',
    glutes: 'Glutes', hamstrings: 'Hamstrings'
  };

  // [region, shape, attrs]  shape: e = ellipse(cx,cy,rx,ry,rot) · p = path
  var FRONT = [
    ['shoulders', 'e', 62, 92, 19, 15, -20], ['shoulders', 'e', 138, 92, 19, 15, 20],
    ['chest', 'e', 83, 112, 19, 16, 8], ['chest', 'e', 117, 112, 19, 16, -8],
    ['biceps', 'e', 50, 132, 10, 24, 8], ['biceps', 'e', 150, 132, 10, 24, -8],
    ['forearms', 'e', 41, 186, 9, 27, 6], ['forearms', 'e', 159, 186, 9, 27, -6],
    ['abs', 'r', 85, 134, 30, 58, 9],
    ['obliques', 'e', 74, 164, 9, 26, -6], ['obliques', 'e', 126, 164, 9, 26, 6],
    ['quads', 'e', 80, 270, 19, 52, -3], ['quads', 'e', 120, 270, 19, 52, 3],
    ['calves', 'e', 76, 366, 12, 36, -3], ['calves', 'e', 124, 366, 12, 36, 3]
  ];
  var BACK = [
    ['traps', 'p', 'M100 64 L72 86 L84 108 L100 122 L116 108 L128 86 Z'],
    ['shoulders', 'e', 62, 92, 19, 15, -20], ['shoulders', 'e', 138, 92, 19, 15, 20],
    ['lats', 'e', 78, 140, 16, 32, -10], ['lats', 'e', 122, 140, 16, 32, 10],
    ['triceps', 'e', 50, 132, 10, 24, 8], ['triceps', 'e', 150, 132, 10, 24, -8],
    ['forearms', 'e', 41, 186, 9, 27, 6], ['forearms', 'e', 159, 186, 9, 27, -6],
    ['lowback', 'r', 86, 174, 28, 26, 9],
    ['glutes', 'e', 83, 222, 20, 20, 0], ['glutes', 'e', 117, 222, 20, 20, 0],
    ['hamstrings', 'e', 80, 285, 18, 44, -3], ['hamstrings', 'e', 120, 285, 18, 44, 3],
    ['calves', 'e', 76, 366, 12, 36, -3], ['calves', 'e', 124, 366, 12, 36, 3]
  ];

  function mk(tag, attrs) {
    var n = document.createElementNS(NS, tag);
    Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    return n;
  }

  function silhouette(g) {
    var attrs = { class: 'bm-base' };
    g.appendChild(mk('circle', { cx: 100, cy: 32, r: 21, class: 'bm-base' }));
    g.appendChild(mk('rect', { x: 91, y: 50, width: 18, height: 20, rx: 6, class: 'bm-base' }));
    g.appendChild(mk('rect', { x: 62, y: 66, width: 76, height: 150, rx: 28, class: 'bm-base' }));
    g.appendChild(mk('rect', { x: 28, y: 78, width: 26, height: 150, rx: 13, class: 'bm-base', transform: 'rotate(6 41 78)' }));
    g.appendChild(mk('rect', { x: 146, y: 78, width: 26, height: 150, rx: 13, class: 'bm-base', transform: 'rotate(-6 159 78)' }));
    g.appendChild(mk('rect', { x: 60, y: 200, width: 36, height: 215, rx: 16, class: 'bm-base' }));
    g.appendChild(mk('rect', { x: 104, y: 200, width: 36, height: 215, rx: 16, class: 'bm-base' }));
    return attrs;
  }

  function figure(parent, offsetX, defs, opts, title) {
    var g = mk('g', { transform: 'translate(' + offsetX + ' 0)' });
    silhouette(g);
    defs.forEach(function (d) {
      var m = d[0], node;
      if (d[1] === 'e') node = mk('ellipse', { cx: d[2], cy: d[3], rx: d[4], ry: d[5], transform: 'rotate(' + d[6] + ' ' + d[2] + ' ' + d[3] + ')' });
      else if (d[1] === 'r') node = mk('rect', { x: d[2], y: d[3], width: d[4], height: d[5], rx: d[6] });
      else node = mk('path', { d: d[2] });
      node.setAttribute('class', 'bm-r');
      node.setAttribute('data-m', m);
      var t = opts.heat && opts.heat[m] ? opts.heat[m] : 0;
      if (t > 0) {
        node.style.fill = 'hsl(' + Math.round(275 - 255 * t) + ' 88% 58%)';
        node.style.fillOpacity = String(0.35 + 0.6 * t);
      }
      if (opts.selected === m) node.classList.add('sel');
      if (opts.onPick) {
        node.setAttribute('role', 'button');
        node.setAttribute('tabindex', '0');
        node.setAttribute('aria-label', LABEL[m]);
        node.addEventListener('click', function () { opts.onPick(m); });
        node.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); opts.onPick(m); } });
      }
      var tt = mk('title'); tt.textContent = LABEL[m] + (opts.tips && opts.tips[m] ? ': ' + opts.tips[m] : '');
      node.appendChild(tt);
      g.appendChild(node);
    });
    var lab = mk('text', { x: 100, y: 432, 'text-anchor': 'middle', class: 'bm-cap' });
    lab.textContent = title;
    g.appendChild(lab);
    parent.appendChild(g);
  }

  /* render(container, {heat:{m:0..1}, tips:{m:'text'}, selected:'quads', onPick:fn}) */
  function render(container, opts) {
    opts = opts || {};
    container.innerHTML = '';
    var svg = mk('svg', { viewBox: '0 0 410 440', class: 'bodymap', role: 'group', 'aria-label': 'Body map: front and back' });
    figure(svg, 0, FRONT, opts, 'FRONT');
    figure(svg, 210, BACK, opts, 'BACK');
    container.appendChild(svg);
  }

  window.MFLBody = { render: render, label: LABEL };
})();
