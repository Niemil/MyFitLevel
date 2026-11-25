(function () {
  try {
    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    var f = document.getElementById('tzfield');
    if (tz && f && !f.value) f.value = tz;
    var s = document.getElementById('tzselect');
    if (tz && s && !s.dataset.set) { for (var i = 0; i < s.options.length; i++) { if (s.options[i].value === tz) { s.value = tz; break; } } }
  } catch (e) {}
})();
