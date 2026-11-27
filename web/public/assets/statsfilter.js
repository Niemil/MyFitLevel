(function () {
  // Filter links on the Traffic page refresh only the visitor list, so the page does not jump to the top.
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-sw]') : null;
    if (!a) { return; }
    e.preventDefault();
    var go = function () { location.href = a.href; };
    fetch(a.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) {
      var n = new DOMParser().parseFromString(t, 'text/html').getElementById('visits');
      var c = document.getElementById('visits');
      if (!n || !c) { return go(); }
      c.replaceWith(n);
      history.replaceState(null, '', a.href);
    }).catch(go);
  });
})();
