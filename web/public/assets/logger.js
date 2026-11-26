/* MyFitLevel workout logger: autosaving draft, pre-filled sets, +/- steppers, warm-ups. */
(function () {
  'use strict';
  var dataEl = document.getElementById('fit-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';
  var LB = 2.2046226218;
  var IS_LB = D.unit === 'lb';
  var W_STEP = IS_LB ? 5 : 2.5;
  var stepPref = D.steps || {};
  function stepFor(ex) { return stepPref[ex.id] > 0 ? Number(stepPref[ex.id]) : W_STEP; }
  // Dumbbells come in a rack, not in equal jumps: 1-10 kg by 1, then 12.5, 15, 17.5 ... plus whatever this member already uses.
  var ladderCache = null;
  // Dumbbell weights of the chosen gym (if it lists any), so +/- hops between what the gym really has
  function gymDumbbells() {
    var sel = document.getElementById('f_gym');
    if (!sel) return null;
    var id = Number(sel.value) || 0, out = null;
    (D.gyms || []).forEach(function (g) { if (g.id === id && g.db && g.db.length) out = g.db.slice(); });
    return out;
  }
  function dumbbellLadder() {
    if (ladderCache) return ladderCache;
    var gdb = gymDumbbells();
    if (gdb) { ladderCache = gdb; return gdb; }
    var v = [], i;
    if (IS_LB) { for (i = 5; i <= 150; i += 5) v.push(i); }
    else { for (i = 1; i <= 10; i++) v.push(i); for (i = 12.5; i <= 50; i += 2.5) v.push(i); for (i = 55; i <= 100; i += 5) v.push(i); }
    (D.dbUsed || []).forEach(function (x) { x = r2(Number(x)); if (x > 0 && v.indexOf(x) === -1) v.push(x); });
    v.sort(function (a, b) { return a - b; });
    ladderCache = v;
    return v;
  }
  function ladderFor(ex) {
    return ex.kind === 'weight' && (ex.equipment === 'dumbbell' || ex.per_side === 1) && !(stepPref[ex.id] > 0) ? dumbbellLadder() : null;
  }
  function saveStep(ex, v) {
    if (v > 0) stepPref[ex.id] = v; else delete stepPref[ex.id];
    fetch(D.prefUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' }, body: JSON.stringify({ _csrf: csrf, id: ex.id, step: v > 0 ? v : 0 }) }).catch(function () {});
    render();
  }
  // Suggest a step from last session when its weights rise in equal jumps (e.g. 30, 36, 42 -> 6)
  function guessStep(ex) {
    var l = (last[ex.id] || []).filter(function (x) { return !x.wu && x.w !== '' && num(x.w) > 0; }).map(function (x) { return num(x.w); });
    var u = l.filter(function (v, i) { return l.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
    if (u.length < 2) return 0;
    var d = r2(u[1] - u[0]);
    for (var i = 2; i < u.length; i++) if (Math.abs((u[i] - u[i - 1]) - d) > 0.05) return 0;
    return d > 0 && Math.abs(d - stepFor(ex)) > 0.05 ? d : 0;
  }

  var byId = {};
  D.exercises.forEach(function (x) { byId[x.id] = x; });
  var last = D.last || {};

  var wrap = document.getElementById('exercises');
  var estEl = document.getElementById('estpts');
  var form = document.getElementById('logform');
  var stateEl = document.getElementById('savestate');
  var fTitle = document.getElementById('f_title');
  var fWhen = document.getElementById('f_when');
  var fNotes = document.getElementById('f_notes');
  var fGym = document.getElementById('f_gym');
  var hidden = document.getElementById('hidden');

  var items = [];          // [{id, note, sets:[{w,r,t,d,wu,k}]}]
  var began = 0;           // ms timestamp when the workout was started (0 = logged afterwards)
  var started = false;
  var startCard = document.getElementById('startcard');
  var phase2 = document.getElementById('phase2');
  var timerEl = document.getElementById('timer');
  var timerHandle = null;
  function fmtTime(sec) { var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60; return (h ? h + ':' + pad(m) : pad(m)) + ':' + pad(s); }
  function tick() { if (began && timerEl) timerEl.textContent = fmtTime(Math.max(0, Math.floor((Date.now() - began) / 1000))); }
  function showPhase() {
    startCard.hidden = started; phase2.hidden = !started;
    var sw = document.getElementById('sesswhen');
    if (started) {
      clearInterval(timerHandle);
      if (began) { tick(); timerHandle = setInterval(tick, 1000); if (sw) sw.textContent = 'Started ' + new Date(began).toTimeString().slice(0, 5); }
      else { timerEl.textContent = 'Logged'; if (sw) sw.textContent = fWhen.value.replace('T', ' '); }
    }
  }
  function beginWorkout(now) {
    started = true; began = now ? Date.now() : 0;
    if (now) { var d = new Date(); fWhen.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
    showPhase(); persist();
  }
  document.getElementById('startnow').addEventListener('click', function () { beginWorkout(true); });
  document.getElementById('startpast').addEventListener('click', function () { beginWorkout(false); });
  var submitting = false;
  var dirty = false;
  var timer = null;

  /* ---------- helpers ---------- */
  function el(tag, attrs, children) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'text') n.textContent = attrs[k];
      else if (k === 'class') n.className = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) n.appendChild(c); });
    return n;
  }
  function num(v) { v = parseFloat(String(v).replace(',', '.')); return isNaN(v) ? 0 : v; }
  function r2(v) { return Math.round(v * 100) / 100; }
  function show(v) { return v === '' || v === null || v === undefined ? '' : String(r2(num(v))); }
  function isStrength(ex) { return ex.kind === 'weight' || ex.kind === 'bodyweight'; }
  function buzz() { try { if (navigator.vibrate) navigator.vibrate(12); } catch (e) {} }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function nowTime() { var d = new Date(); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }

  /* ---------- points estimate (mirrors the server) ---------- */
  function setPoints(ex, s) {
    if (s.wu || !s.k) return 0;
    var kg = IS_LB ? num(s.w) / LB : num(s.w);
    var reps = num(s.r), p = 0;
    var sd = (ex.equipment === 'dumbbell' || ex.per_side === 1) ? 2 : 1;
    if (ex.kind === 'weight') { var ld = kg * sd; p = reps > 0 ? ex.coef * Math.pow(ld * (1 + Math.min(reps, 12) / 30), 1.3) * D.scale : 0; }
    else if (ex.kind === 'bodyweight') { var lb = D.bw * ex.bw_pct + kg; p = reps > 0 ? ex.coef * Math.pow(lb * (1 + Math.min(reps, 12) / 30), 1.3) * D.scale : 0; }
    else if (ex.kind === 'cardio') { var km = num(s.d), tm = num(s.t); if (ex.dist_coef > 0 && km > 0) { var vv = tm > 0 ? km / (tm / 3600) : ex.ref; var rt = Math.max(0.4, Math.min(2.5, vv / ex.ref)); p = ex.dist_coef * 6.6 * Math.pow(km, 1.2) * Math.pow(rt, 1.3); } else p = ex.coef * 6.6 * (tm / 60); }
    else if (ex.kind === 'duration') p = ex.coef * (num(s.t) / 60);
    var valid = isStrength(ex) ? reps >= 1 : num(s.t) >= 1;
    return valid ? Math.max(1, Math.round(p)) : 0;
  }
  function estimate() {
    var t = 0;
    items.forEach(function (it) { var ex = byId[it.id]; it.sets.forEach(function (s) { t += setPoints(ex, s); }); });
    if (String(t) !== estEl.textContent) {
      estEl.textContent = t;
      estEl.classList.remove('pop'); void estEl.offsetWidth; estEl.classList.add('pop');
    }
  }

  /* ---------- autosave ---------- */
  function setState(txt, cls) {
    stateEl.textContent = txt;
    stateEl.className = 'savestate' + (cls ? ' ' + cls : '');
  }
  function snapshot() {
    return { ts: Date.now(), title: fTitle.value, notes: fNotes.value, when: fWhen.value, gym: fGym ? fGym.value : '0', items: items, began: began, started: started ? 1 : 0 };
  }
  function persist() {
    if (submitting || D.edit) return;
    dirty = true;
    try { localStorage.setItem(D.storeKey, JSON.stringify(snapshot())); } catch (e) {}
    setState('Saving…');
    clearTimeout(timer);
    timer = setTimeout(function () { push(false); }, 400);
  }
  function push(beacon) {
    if (submitting) return;
    var body = JSON.stringify({ _csrf: csrf, payload: snapshot() });
    if (beacon && navigator.sendBeacon) {
      try { navigator.sendBeacon(D.draftUrl, new Blob([body], { type: 'text/plain' })); } catch (e) {}
      return;
    }
    fetch(D.draftUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Content-Type': 'text/plain', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok) { dirty = false; setState(j.empty ? 'Autosave on' : 'Saved ✓ ' + nowTime(), j.empty ? '' : 'ok'); }
        else { setState((j && j.error) || 'Not saved', 'bad'); }
      })
      .catch(function () {
        setState('Offline: kept on this device', 'warn');
        clearTimeout(timer);
        timer = setTimeout(function () { push(false); }, 6000);
      });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && dirty) { clearTimeout(timer); push(true); } });
  window.addEventListener('pagehide', function () { if (dirty) push(true); });
  window.addEventListener('online', function () { if (dirty) push(false); });
  [fTitle, fWhen, fNotes].forEach(function (f) { f.addEventListener('input', persist); f.addEventListener('change', persist); });
  if (fGym) fGym.addEventListener('change', function () { ladderCache = null; persist(); render(); });

  /* ---------- steppers ---------- */
  function stepper(opts) {
    // opts: get(), set(str), step, min, max, mode, ph, label
    var inp = el('input', { type: 'text', inputmode: opts.mode || 'decimal', placeholder: opts.ph || '', autocomplete: 'off', 'aria-label': opts.label });
    inp.value = opts.get();
    function commit(v) { opts.set(v); persist(); estimate(); if (opts.after) opts.after(); }
    function bump(dir) {
      var cur = inp.value === '' ? (opts.start || 0) : num(inp.value);
      var nv = inp.value === '' && dir > 0 && opts.start ? opts.start : cur + dir * opts.step;
      if (opts.ladder) {
        var L = opts.ladder, found = null, k;
        if (dir > 0) { for (k = 0; k < L.length; k++) if (L[k] > cur + 0.001) { found = L[k]; break; } }
        else { for (k = L.length - 1; k >= 0; k--) if (L[k] < cur - 0.001) { found = L[k]; break; } }
        nv = found !== null ? found : (dir < 0 && cur > 0 ? 0 : cur + dir * opts.step);
      }
      nv = Math.min(opts.max, Math.max(opts.min, nv));
      nv = r2(nv);
      inp.value = String(nv);
      commit(String(nv));
    }
    function btn(sym, dir, label) {
      var b = el('button', { type: 'button', class: 'stp', 'aria-label': label, text: sym });
      var hold = null, rep = null, fired = false;
      function stop() { clearTimeout(hold); clearInterval(rep); }
      b.addEventListener('pointerdown', function (e) {
        e.preventDefault(); fired = false;
        hold = setTimeout(function () { fired = true; rep = setInterval(function () { bump(dir); }, 90); }, 420);
      });
      ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (ev) { b.addEventListener(ev, stop); });
      b.addEventListener('click', function (e) { e.preventDefault(); if (!fired) bump(dir); fired = false; buzz(); });
      return b;
    }
    inp.addEventListener('input', function () { commit(inp.value); });
    inp.addEventListener('focus', function () { try { inp.select(); } catch (e) {} });
    return el('div', { class: 'stepper' }, [btn('−', -1, 'Decrease ' + opts.label), inp, btn('+', 1, 'Increase ' + opts.label)]);
  }

  /* ---------- rendering ---------- */
  function workingNumber(it, idx) {
    var n = 0;
    for (var i = 0; i <= idx; i++) if (!it.sets[i].wu) n++;
    return n;
  }
  function lastHint(ex) {
    var l = last[ex.id];
    if (!l || !l.length) return '';
    var work = l.filter(function (s) { return !s.wu; });
    var s0 = work[0] || l[0];
    if (isStrength(ex)) {
      return 'Last time: ' + (s0.w !== '' ? show(s0.w) + (IS_LB ? ' lb' : ' kg') + ' × ' : '') + s0.r + (work.length > 1 ? ' · ' + work.length + ' sets' : '');
    }
    return 'Last time: ' + (s0.t ? Math.round(num(s0.t) / 60 * 10) / 10 + ' min' : '') + (s0.d ? ' · ' + s0.d + ' km' : '');
  }

  function buildRow(it, ex, s, j, card) {
    var row = el('div', { class: 'setrow' + (s.k ? ' done' : '') + (s.wu ? ' wu' : '') });
    function refresh() { row.classList.toggle('done', !!s.k); tick.setAttribute('aria-pressed', s.k ? 'true' : 'false'); }
    function touched() { if (!s.k) { s.k = 1; refresh(); } }

    var nlabel = s.wu ? 'W' : String(workingNumber(it, j));
    var nb = el('button', { type: 'button', class: 'setno', text: nlabel, title: isStrength(ex) ? 'Tap to switch warm-up / working set' : 'Set ' + nlabel });
    if (isStrength(ex)) {
      nb.addEventListener('click', function () { s.wu = s.wu ? 0 : 1; persist(); render(); });
    } else { nb.disabled = true; }

    var tick = el('button', { type: 'button', class: 'tick', 'aria-label': 'Mark set done', 'aria-pressed': s.k ? 'true' : 'false', text: '✓' });
    tick.addEventListener('click', function () { s.k = s.k ? 0 : 1; refresh(); buzz(); persist(); estimate(); });

    var rm = el('button', { type: 'button', class: 'xbtn', title: 'Remove set', 'aria-label': 'Remove set', text: '×' });
    rm.addEventListener('click', function () {
      it.sets.splice(j, 1);
      if (!it.sets.length) items.splice(items.indexOf(it), 1);
      persist(); render();
    });

    var cells = [nb];
    if (isStrength(ex)) {
      cells.push(stepper({
        label: ex.kind === 'bodyweight' ? 'added weight' : 'weight', get: function () { return show(s.w); },
        set: function (v) { s.w = v; touched(); }, step: stepFor(ex), ladder: ladderFor(ex), min: 0, max: IS_LB ? 2200 : 1000, mode: 'decimal', ph: ex.kind === 'bodyweight' ? '+0' : '0'
      }));
      cells.push(stepper({
        label: 'reps', get: function () { return show(s.r); },
        set: function (v) { s.r = v; touched(); }, step: 1, min: 0, max: 500, mode: 'numeric', ph: '0', start: 1
      }));
    } else if (ex.kind === 'cardio') {
      cells.push(stepper({
        label: 'minutes', get: function () { return s.t ? String(r2(num(s.t) / 60)) : ''; },
        set: function (v) { s.t = v === '' ? '' : String(Math.round(num(v) * 60)); touched(); }, step: 1, min: 0, max: 1440, mode: 'decimal', ph: 'min', start: 1
      }));
      cells.push(stepper({
        label: 'kilometres', get: function () { return show(s.d); },
        set: function (v) { s.d = v; touched(); }, step: 0.5, min: 0, max: 500, mode: 'decimal', ph: 'km'
      }));
    } else {
      var one = stepper({
        label: 'seconds', get: function () { return show(s.t); },
        set: function (v) { s.t = v; touched(); }, step: 5, min: 0, max: 86400, mode: 'numeric', ph: 'seconds', start: 30
      });
      one.classList.add('span2');
      cells.push(one);
    }
    cells.push(tick, rm);
    cells.forEach(function (c) { row.appendChild(c); });
    return row;
  }

  function render() {
    wrap.innerHTML = '';
    items.forEach(function (it) {
      var ex = byId[it.id];
      if (!ex) return;
      var rmEx = el('button', { type: 'button', class: 'xbtn', title: 'Remove exercise', 'aria-label': 'Remove exercise', text: '×' });
      rmEx.addEventListener('click', function () {
        if (it.sets.some(function (s) { return s.k; }) && !window.confirm('Remove ' + ex.name + ' and its ticked sets?')) return;
        items.splice(items.indexOf(it), 1); persist(); render();
      });
      var hint = lastHint(ex);
      var infoBtn = el('button', { type: 'button', class: 'xbtn infobtn', title: 'How to do this exercise', 'aria-label': 'How to do ' + ex.name, text: 'ⓘ' });
      infoBtn.addEventListener('click', function () { openGuide(ex); });
      var stepBtn = el('button', { type: 'button', class: 'xbtn stepbtn', title: 'Change the +/- weight jump', 'aria-label': 'Weight step', text: ladderFor(ex) ? 'auto' : '±' + show(stepFor(ex)) });
      var idx = items.indexOf(it);
      var upB = el('button', { type: 'button', class: 'xbtn', title: 'Move up', 'aria-label': 'Move up', text: '↑' });
      var dnB = el('button', { type: 'button', class: 'xbtn', title: 'Move down', 'aria-label': 'Move down', text: '↓' });
      if (idx === 0) upB.disabled = true;
      if (idx === items.length - 1) dnB.disabled = true;
      upB.addEventListener('click', function () { var i = items.indexOf(it); if (i > 0) { items.splice(i - 1, 0, items.splice(i, 1)[0]); persist(); render(); } });
      dnB.addEventListener('click', function () { var i = items.indexOf(it); if (i < items.length - 1) { items.splice(i + 1, 0, items.splice(i, 1)[0]); persist(); render(); } });
      var noteBtn = el('button', { type: 'button', class: 'xbtn notebtn' + (it.note ? ' has' : ''), title: 'Note for this exercise', 'aria-label': 'Note', text: '📝' });
      var notePanel = el('div', { class: 'exnotebox', hidden: 'hidden' });
      var noteIn = el('textarea', { rows: '2', maxlength: '500', placeholder: 'Note for this exercise (form cues, how it felt…)' });
      noteIn.value = it.note || '';
      noteIn.addEventListener('input', function () { it.note = noteIn.value; noteBtn.classList.toggle('has', !!noteIn.value); persist(); });
      notePanel.appendChild(noteIn);
      if (it.note) notePanel.hidden = false;
      noteBtn.addEventListener('click', function () { notePanel.hidden = !notePanel.hidden; if (!notePanel.hidden) noteIn.focus(); });
      var head = el('div', { class: 'exhead' }, [
        el('span', { class: 'exletter', text: String.fromCharCode(65 + (idx % 26)) }),
        el('div', { class: 'grow' }, [el('b', { text: ex.name }), el('small', { text: ex.muscle + (ex.equipment ? ' · ' + ex.equipment : '') }), hint ? el('div', { class: 'hint', text: hint }) : null]),
        el('div', { class: 'exbtns' }, [upB, dnB, noteBtn, isStrength(ex) && ex.kind === 'weight' ? stepBtn : null, infoBtn, rmEx])
      ]);
      var card = el('div', { class: 'exercard' + (it.fresh ? ' enter' : ''), 'data-ex': String(ex.id) }, [head, notePanel]);
      var panel = el('div', { class: 'steppanel', hidden: 'hidden' });
      stepBtn.addEventListener('click', function () {
        if (!panel.hidden) { panel.hidden = true; return; }
        panel.innerHTML = '';
        panel.appendChild(el('div', { class: 'small muted', text: 'Weight jump for the − / + buttons on this exercise' }));
        var row = el('div', { class: 'filters' });
        var opts = IS_LB ? [1, 2.5, 5, 10, 15, 20] : [1, 2.5, 5, 7.5, 10];
        if (ladderFor(ex)) panel.appendChild(el('div', { class: 'small muted', text: 'Auto: hops between dumbbell weights (' + dumbbellLadder().slice(0, 6).join(', ') + '… and the ones you use). Pick a number to use a fixed jump instead.' }));
        else if (ex.equipment === 'dumbbell' || ex.per_side === 1) { var au = el('button', { type: 'button', class: 'chip', text: 'Auto (dumbbell rack)' }); au.addEventListener('click', function () { saveStep(ex, 0); }); panel.appendChild(au); }
        var g = guessStep(ex);
        if (g) opts.unshift(g);
        opts.forEach(function (v, i) {
          var b = el('button', { type: 'button', class: 'chip' + (Math.abs(v - stepFor(ex)) < 0.001 ? ' on' : ''), text: String(v) + (g && i === 0 ? ' (last time)' : '') });
          b.addEventListener('click', function () { saveStep(ex, v); });
          row.appendChild(b);
        });
        panel.appendChild(row);
        var cust = el('input', { type: 'text', inputmode: 'decimal', placeholder: 'custom, e.g. 6', class: 'stepcustom' });
        var ok = el('button', { type: 'button', class: 'chip', text: 'Set' });
        ok.addEventListener('click', function () { var v = num(cust.value); if (v > 0) saveStep(ex, v); });
        var rs = el('button', { type: 'button', class: 'chip', text: 'Reset' });
        rs.addEventListener('click', function () { saveStep(ex, 0); });
        panel.appendChild(el('div', { class: 'filters' }, [cust, ok, rs]));
        panel.hidden = false;
      });
      card.appendChild(panel);
      if (ex.per_side === 1) card.appendChild(el('div', { class: 'sidehint', text: '⚖ Log the weight of ONE dumbbell (per hand)' }));
      if (ex.per_side === 2) card.appendChild(el('div', { class: 'sidehint', text: '⚖ Log the weight on ONE side (one stack), not both added together' }));
      it.fresh = false;
      var cols = el('div', { class: 'setcols' }, [
        el('span', { text: 'SET' }),
        el('span', { text: isStrength(ex) ? (ex.kind === 'bodyweight' ? '+' + (IS_LB ? 'LB' : 'KG') : (IS_LB ? 'LB' : 'KG') + (ex.per_side === 1 ? '/HAND' : ex.per_side === 2 ? '/SIDE' : '')) : (ex.kind === 'cardio' ? 'MIN' : 'SECONDS') }),
        el('span', { text: isStrength(ex) ? 'REPS' : (ex.kind === 'cardio' ? 'KM' : '') }),
        el('span', { text: 'DONE' }), el('span')
      ]);
      card.appendChild(cols);
      it.sets.forEach(function (s, j) { card.appendChild(buildRow(it, ex, s, j, card)); });

      var actions = el('div', { class: 'exactions' });
      var add = el('button', { type: 'button', class: 'addset', text: '+ Add set' });
      add.addEventListener('click', function () {
        var base = null;
        for (var i = it.sets.length - 1; i >= 0; i--) { if (!it.sets[i].wu) { base = it.sets[i]; break; } }
        base = base || it.sets[it.sets.length - 1] || blank();
        it.sets.push({ w: base.w, r: base.r, t: base.t, d: base.d, wu: 0, k: 0 });
        persist(); render();
        focusLast(it.id);
      });
      actions.appendChild(add);
      if (isStrength(ex)) {
        var wu = el('button', { type: 'button', class: 'addset ghostw', text: '+ Warm-up' });
        wu.addEventListener('click', function () {
          var firstWork = null, pos = 0, nth = 0;
          it.sets.forEach(function (s, i) { if (s.wu) { pos = i + 1; nth++; } else if (!firstWork) firstWork = s; });
          // Only ever mimic THIS exercise's own previous warm-ups; otherwise start empty.
          var l = last[ex.id] || [];
          var lw = l.filter(function (x) { return x.wu; });
          var lwork = l.filter(function (x) { return !x.wu; })[0];
          var tpl = lw[nth] || null, w = '', r = '';
          if (tpl) {
            r = String(tpl.r || '');
            if (tpl.w !== '' && tpl.w !== null) {
              w = String(tpl.w);
              if (lwork && num(lwork.w) && firstWork && num(firstWork.w)) {
                w = String(r2(Math.round(num(firstWork.w) * num(tpl.w) / num(lwork.w) / stepFor(ex)) * stepFor(ex)));
              }
            }
          }
          it.sets.splice(pos, 0, { w: w, r: r, t: '', d: '', wu: 1, k: 0 });
          persist(); render();
        });
        actions.appendChild(wu);
      }
      card.appendChild(actions);
      wrap.appendChild(card);
    });
    syncRoutineBox();
    estimate();
    var nS = 0; items.forEach(function (x) { nS += x.sets.length; });
    var ss = document.getElementById('sesssub');
    if (ss) ss.textContent = items.length + ' exercise' + (items.length === 1 ? '' : 's') + ' · ' + nS + ' set' + (nS === 1 ? '' : 's');
  }
  var gd = document.getElementById('guide');
  function openGuide(ex) {
    document.getElementById('guidetitle').textContent = ex.name;
    var steps = document.getElementById('guidesteps'); steps.innerHTML = '';
    document.getElementById('guidemuscles').textContent = 'Loading…';
    document.getElementById('guidevideo').href = 'https://www.youtube.com/results?search_query=' + encodeURIComponent('how to do ' + ex.name + ' proper form');
    document.getElementById('guidepage').href = '#';
    try { gd.showModal(); } catch (e) { gd.setAttribute('open', ''); }
    fetch(D.guideUrl + ex.id, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) throw 0;
      document.getElementById('guidemuscles').textContent = (j.muscles.length ? 'Works: ' + j.muscles.join(', ') : '') + (j.side === 1 ? ' · weight per hand' : j.side === 2 ? ' · weight per side' : '');
      if (j.steps.length) j.steps.forEach(function (t) { steps.appendChild(el('li', { text: t })); });
      else steps.appendChild(el('li', { class: 'muted', text: 'No written guide for this one yet. Tap the video button to see how it is done.' }));
      document.getElementById('guidevideo').href = j.video;
      document.getElementById('guidepage').href = j.page;
    }).catch(function () { document.getElementById('guidemuscles').textContent = 'Could not load the guide. Check your connection.'; });
  }
  document.getElementById('guideclose').addEventListener('click', function () { try { gd.close(); } catch (e) { gd.removeAttribute('open'); } });
  function blank() { return { w: '', r: '', t: '', d: '', wu: 0, k: 0 }; }
  function focusLast(id) {
    var card = wrap.querySelector('[data-ex="' + id + '"]');
    if (!card) return;
    var rows = card.querySelectorAll('.setrow');
    var r = rows[rows.length - 1];
    if (r && r.scrollIntoView) { try { r.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {} }
  }

  function addExercise(id) {
    id = parseInt(id, 10);
    var ex = byId[id];
    if (!ex) return;
    var existing = items.filter(function (x) { return x.id === id; })[0];
    if (existing) {
      var c = wrap.querySelector('[data-ex="' + id + '"]');
      if (c) { c.scrollIntoView({ block: 'center', behavior: 'smooth' }); c.classList.remove('flashcard'); void c.offsetWidth; c.classList.add('flashcard'); }
      return;
    }
    var l = last[id];
    var mk = function (s) { return { w: s.w === '' ? '' : String(s.w), r: String(s.r || ''), t: String(s.t || ''), d: String(s.d || ''), wu: s.wu ? 1 : 0, k: 0 }; };
    var mode = D.prefill || 'first';   // first = only the first set like last time (default), all = copy every set, none = empty
    var sets;
    if (!l || !l.length || mode === 'none') sets = [blank()];
    else if (mode === 'all') sets = l.map(mk);
    else { var firstWork = l.filter(function (x) { return !x.wu; })[0] || l[0]; sets = [mk(firstWork)]; sets[0].wu = 0; }
    items.push({ id: id, sets: sets, note: '', fresh: true });
    persist(); render();
    var card = wrap.querySelector('[data-ex="' + id + '"]');
    if (card && card.scrollIntoView) { try { card.scrollIntoView({ block: 'start', behavior: 'smooth' }); } catch (e) {} }
  }

  /* ---------- exercise picker ---------- */
  var search = document.getElementById('exsearch');
  var results = document.getElementById('exresults');
  var mf = document.getElementById('musclefilter');
  var chips = document.getElementById('recentChips');
  var muscle = '';
  var sub = '';
  var equip = '';
  var bodyOpen = false;
  var ef = document.getElementById('equipfilter');
  var bodyWrap = document.getElementById('bodywrap');
  var activeBar = document.getElementById('activefilter');
  var favs = (D.favs || []).slice();
  var tab = favs.length ? 'fav' : 'recent';
  function isFav(id) { return favs.indexOf(id) !== -1; }
  var LISTS = D.lists || [], listOf = {};
  LISTS.forEach(function (l) { l.ids.forEach(function (id) { if (!listOf[id]) listOf[id] = l; }); });
  function inList(id) { return listOf[id] ? 1 : 0; }
  function terms(q) { return (window.MFLSearch ? window.MFLSearch.terms(q) : q.split(/\s+/).filter(Boolean)); }
  function toggleFav(id) {
    var on = !isFav(id);
    favs = on ? [id].concat(favs) : favs.filter(function (x) { return x !== id; });
    fetch(D.favUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' }, body: JSON.stringify({ _csrf: csrf, id: id, on: on }) }).catch(function () {});
    drawResults(); drawChips();
  }
  var EQUIP = ['barbell', 'dumbbell', 'machine', 'cable', 'bodyweight', 'kettlebell', 'outdoor'];
  var SUBLABEL = (window.MFLBody && window.MFLBody.label) || {};
  var muscles = ['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'core', 'fullbody', 'cardio'];

  function drawFilters() {
    mf.innerHTML = '';
    var bb = el('button', { type: 'button', class: 'chip body' + (bodyOpen ? ' on' : ''), text: '🧍 Body map' });
    bb.addEventListener('click', function () { bodyOpen = !bodyOpen; drawFilters(); drawBody(); });
    mf.appendChild(bb);
    [''].concat(muscles).forEach(function (m) {
      var b = el('button', { type: 'button', class: 'chip' + (muscle === m && !sub ? ' on' : ''), text: m === '' ? 'All' : m });
      b.addEventListener('click', function () { muscle = m; sub = ''; bodyOpen = false; drawFilters(); drawBody(); drawResults(); });
      mf.appendChild(b);
    });
    ef.innerHTML = '';
    [''].concat(EQUIP).forEach(function (q) {
      var b = el('button', { type: 'button', class: 'chip eq' + (equip === q ? ' on' : ''), text: q === '' ? 'Any equipment' : q });
      b.addEventListener('click', function () { equip = q; drawFilters(); drawResults(); });
      ef.appendChild(b);
    });
  }
  function drawBody() {
    bodyWrap.hidden = !bodyOpen;
    if (!bodyOpen) return;
    if (!window.MFLBody) { bodyWrap.hidden = true; return; }
    var box = el('div', { class: 'bodybox' });
    bodyWrap.innerHTML = '';
    bodyWrap.appendChild(el('p', { class: 'muted small center', text: 'Tap a muscle to see exercises for it' }));
    bodyWrap.appendChild(box);
    window.MFLBody.render(box, { selected: sub, onPick: function (m) { sub = m; muscle = ''; bodyOpen = false; drawFilters(); drawBody(); drawResults(); } });
  }
  function drawActive() {
    var bits = [];
    if (sub) bits.push(SUBLABEL[sub] || sub);
    if (equip) bits.push(equip);
    activeBar.hidden = !sub;
    activeBar.innerHTML = '';
    if (!sub) return;
    var clr = el('button', { type: 'button', class: 'chip on', text: '✕ ' + (SUBLABEL[sub] || sub) });
    clr.addEventListener('click', function () { sub = ''; drawActive(); drawResults(); });
    activeBar.appendChild(el('span', { class: 'muted small', text: 'Muscle: ' }));
    activeBar.appendChild(clr);
  }
  function summary(ex) {
    var l = last[ex.id];
    if (!l || !l.length) return '';
    var s = l.filter(function (x) { return !x.wu; })[0] || l[0];
    if (isStrength(ex)) return (s.w !== '' ? show(s.w) + '×' : '') + s.r;
    return s.t ? Math.round(num(s.t) / 60) + ' min' : '';
  }
  function drawResults() {
    var q = search.value.trim().toLowerCase();
    results.innerHTML = '';
    drawActive();
    if (!q && !muscle && !sub && !equip) {
      var tabs = el('div', { class: 'filters tabs' });
      LISTS.map(function (l) { return ['list:' + l.id, l.name, l.color]; }).concat([['fav', '★ Favorites'], ['recent', 'Recent'], ['freq', 'Most used'], ['routines', 'Routines']]).forEach(function (t) {
        var b = el('button', { type: 'button', class: 'chip' + (tab === t[0] ? ' on' : ''), text: t[1] });
        if (t[2]) { b.style.borderColor = t[2]; if (tab === t[0]) { b.style.background = t[2]; } }
        b.addEventListener('click', function () { tab = t[0]; drawResults(); });
        tabs.appendChild(b);
      });
      results.appendChild(tabs);
      if (tab === 'routines') { drawRoutines(); return; }
      var curList = tab.indexOf('list:') === 0 ? LISTS.filter(function (l) { return 'list:' + l.id === tab; })[0] : null;
      var ids = curList ? curList.ids : (tab === 'fav' ? favs : (tab === 'freq' ? D.freq : D.recent)) || [];
      ids = ids.filter(function (id) { return byId[id]; });
      if (!ids.length) {
        results.appendChild(el('p', { class: 'muted small pad', text: curList ? 'This list is empty. Add exercises to it under My lists in the menu.' : tab === 'fav' ? 'No favorites yet. Tap the ☆ next to any exercise to keep it here.' : 'Nothing here yet. Log a workout and your usual exercises show up.' }));
        results.appendChild(el('p', { class: 'muted small pad', text: 'Or search above, or pick a category or the body map.' }));
        return;
      }
      ids.forEach(function (id) { results.appendChild(resItem(byId[id])); });
      return;
    }
    var tms = terms(q);
    var list = D.exercises.filter(function (x) {
      if (muscle && x.muscle !== muscle) return false;
      if (sub && (x.subs || []).indexOf(sub) === -1) return false;
      if (equip && !(equip === 'outdoor' ? x.equipment === 'outdoor' : x.equipment === equip)) return false;
      var hay = (x.name + ' ' + x.equipment + ' ' + x.muscle).toLowerCase();
      return tms.every(function (t) { return hay.indexOf(t) !== -1; });
    });
    // exercises you have done before first, then alphabetical
    list.sort(function (a, b) {
      if (sub) { var pa = (a.subs || [])[0] === sub ? 0 : 1, pb = (b.subs || [])[0] === sub ? 0 : 1; if (pa !== pb) return pa - pb; }
      return inList(b.id) - inList(a.id) || (isFav(b.id) ? 1 : 0) - (isFav(a.id) ? 1 : 0) || (last[b.id] ? 1 : 0) - (last[a.id] ? 1 : 0) || (a.name < b.name ? -1 : 1);
    });
    list = list.slice(0, 50);
    if (!list.length) { results.appendChild(el('p', { class: 'muted small', text: 'No exercise found. Try another word (e.g. “walk”, “dumbbell”, “yoga”).' })); return; }
    list.forEach(function (x) { results.appendChild(resItem(x)); });
  }
  /* ---------- routines ---------- */
  var routines = (D.routines || []).slice();
  function postRoutine(body) {
    body._csrf = csrf;
    return fetch(D.routineUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
  }
  function startRoutine(r) {
    r.items.forEach(function (it) {
      addExercise(it.id);
      var cur = items.filter(function (x) { return x.id === it.id; })[0];
      if (cur && cur.sets.length < it.n) {
        var base = cur.sets[cur.sets.length - 1];
        while (cur.sets.length < it.n) cur.sets.push({ w: base.w, r: base.r, t: base.t, d: base.d, wu: 0, k: 0 });
      }
      if (cur && (it.reps || it.wu)) {   // targets from the routine (advanced editor)
        var rm = /^(\d+)\s*(s|sec)?\b/i.exec(String(it.reps || '')), isTime = rm && rm[2];
        cur.sets.forEach(function (st) {
          if (st.wu) return;
          if (rm && !isTime) st.r = rm[1];
          if (rm && isTime) st.t = rm[1];
          if (it.wu) st.w = String(it.wu);
        });
      }
    });
    persist(); render(); closePicker();
  }
  function drawRoutines() {
    if (!routines.length) {
      results.appendChild(el('p', { class: 'muted small pad', text: 'No routines yet. Add some exercises to your workout and use “Save as routine” further down.' }));
      return;
    }
    routines.forEach(function (r) {
      var names = r.items.map(function (it) { return byId[it.id] ? byId[it.id].name : null; }).filter(Boolean);
      var b = el('button', { type: 'button', class: 'resmain' }, [el('span', { text: r.name }), el('small', { text: names.length + ' exercises · ' + names.slice(0, 3).join(', ') + (names.length > 3 ? '…' : '') })]);
      b.addEventListener('click', function () { startRoutine(r); });
      var del = el('button', { type: 'button', class: 'favbtn', 'aria-label': 'Delete routine', text: '🗑' });
      del.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!window.confirm('Delete routine “' + r.name + '”?')) return;
        postRoutine({ action: 'delete', id: r.id }).then(function () { routines = routines.filter(function (x) { return x.id !== r.id; }); drawResults(); });
      });
      results.appendChild(el('div', { class: 'resitem' }, [b, del]));
    });
  }
  var rBox = document.getElementById('routinebox');
  function syncRoutineBox() { if (rBox) rBox.hidden = !items.length; }
  syncRoutineBox();
  if (rBox) {
    document.getElementById('routinesave').addEventListener('click', function () {
      var nm = document.getElementById('routinename'), msg = document.getElementById('routinemsg');
      var payload = items.map(function (it) { return { id: it.id, n: Math.max(1, it.sets.filter(function (s) { return !s.wu; }).length) }; });
      postRoutine({ action: 'save', name: nm.value, items: payload }).then(function (j) {
        if (j.ok) { routines.unshift(j.routine); nm.value = ''; msg.textContent = 'Saved! Find it under Routines when you add an exercise.'; }
        else msg.textContent = j.error || 'Could not save.';
      }).catch(function () { msg.textContent = 'Could not save (offline?).'; });
    });
  }
  function resItem(x) {
    var sm = summary(x);
    if (tab === 'freq' && !search.value && (D.counts || {})[x.id]) sm = 'Done ' + D.counts[x.id] + ' time' + (D.counts[x.id] === 1 ? '' : 's') + (sm ? ' · ' + sm : '');
    var b = el('button', { type: 'button', class: 'resmain' }, [
      el('span', { text: x.name }),
      el('small', { text: (sm ? sm + ' · ' : '') + (x.subs && x.subs.length ? x.subs.slice(0, 2).map(function (m) { return SUBLABEL[m] || m; }).join(', ') : x.muscle) + (x.equipment ? ' · ' + x.equipment : '') + (x.per_side === 1 ? ' · per hand' : x.per_side === 2 ? ' · per side' : '') })
    ]);
    b.addEventListener('click', function () { addExercise(x.id); closePicker(); });
    var star = el('button', { type: 'button', class: 'favbtn' + (isFav(x.id) ? ' on' : ''), 'aria-label': isFav(x.id) ? 'Remove from favorites' : 'Add to favorites', 'aria-pressed': isFav(x.id) ? 'true' : 'false', text: isFav(x.id) ? '★' : '☆' });
    star.addEventListener('click', function (e) { e.stopPropagation(); toggleFav(x.id); });
    var row = el('div', { class: 'resitem' }, [b, star]);
    var lo = listOf[x.id];
    if (lo) { row.classList.add('inlist'); row.style.borderLeft = '5px solid ' + lo.color; row.style.background = lo.color + '26'; b.querySelector('small').textContent += ' · ' + lo.name; }
    return row;
  }
  var picker = document.getElementById('picker');
  function openPicker() {
    picker.hidden = false; document.body.classList.add('noscroll');
    search.value = ''; muscle = ''; sub = ''; equip = ''; bodyOpen = false; drawFilters(); drawBody(); drawResults();
    setTimeout(function () { try { search.focus(); } catch (e) {} }, 60);
  }
  function closePicker() { picker.hidden = true; document.body.classList.remove('noscroll'); }
  document.getElementById('openpicker').addEventListener('click', openPicker);
  document.getElementById('closepicker').addEventListener('click', closePicker);
  picker.addEventListener('click', function (e) { if (e.target === picker) closePicker(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !picker.hidden) closePicker(); });
  search.addEventListener('input', drawResults);
  drawFilters();
  function drawChips() {
    chips.innerHTML = '';
    LISTS.forEach(function (l) {
      if (!l.ids.length) return;
      var lb = el('button', { type: 'button', class: 'chip listchip', text: '+ ' + l.name });
      lb.style.borderColor = l.color; lb.style.background = l.color + '33';
      lb.addEventListener('click', function () { openPicker(); tab = 'list:' + l.id; drawResults(); });
      chips.appendChild(lb);
    });
    var seen = {}, ids = [];
    favs.concat(D.recent || []).forEach(function (id) { if (byId[id] && !seen[id]) { seen[id] = 1; ids.push(id); } });
    ids = ids.slice(0, 10);
    if (!ids.length) return;
    chips.appendChild(el('small', { class: 'muted', text: ' Your usual: ' }));
    ids.forEach(function (id) {
      var b = el('button', { type: 'button', class: 'chip', text: (isFav(id) ? '★ ' : '') + byId[id].name });
      b.addEventListener('click', function () { addExercise(id); });
      chips.appendChild(b);
    });
  }
  drawChips();

  /* ---------- restore draft (server vs this device, newest wins) ---------- */
  function normItems(list) {
    return (list || []).map(function (it) {
      return {
        id: parseInt(it.id, 10),
        note: it.note ? String(it.note) : '',
        sets: (it.sets || []).map(function (s) {
          return { w: s.w === undefined ? '' : String(s.w), r: s.r === undefined ? '' : String(s.r), t: s.t === undefined ? '' : String(s.t), d: s.d === undefined ? '' : String(s.d), wu: s.wu ? 1 : 0, k: s.k ? 1 : 0 };
        })
      };
    }).filter(function (it) { return byId[it.id] && it.sets.length; });
  }
  if (fGym && D.gymDefault && fGym.querySelector('option[value="' + D.gymDefault + '"]')) fGym.value = String(D.gymDefault);
  var srv = D.draft, loc = null;
  if (!D.edit) { try { loc = JSON.parse(localStorage.getItem(D.storeKey) || 'null'); } catch (e) {} }
  var pick = D.edit || srv;
  var fromRepeat = !!(srv && srv.fromRepeat);
  if (!D.edit && !fromRepeat && loc && (!srv || (loc.ts || 0) > (srv.ts || 0))) pick = loc;
  if (pick) {
    items = normItems(pick.items);
    fTitle.value = pick.title || '';
    fNotes.value = pick.notes || '';
    if (pick.when) fWhen.value = pick.when;
    if (fGym && pick.gym) fGym.value = pick.gym;
    if (pick.started || items.length || fTitle.value || fNotes.value) { started = true; began = parseInt(pick.began, 10) || 0; }
    if (items.length || fTitle.value || fNotes.value) {
      var done = 0, total = 0;
      items.forEach(function (it) { it.sets.forEach(function (s) { total++; if (s.k) done++; }); });
      if (D.edit) {
        setState('Editing saved workout');
      } else if (fromRepeat) {
        setState('Pre-filled from an old workout');
      } else {
        document.getElementById('resumebar').hidden = false;
        document.getElementById('resumetxt').textContent = items.length + ' exercise' + (items.length === 1 ? '' : 's') + ', ' + done + '/' + total + ' sets ticked. Everything is saved as you go.';
      }
      if (pick === loc || fromRepeat) persist();
    }
  }
  render();
  showPhase();

  function doDiscard() {
    if (!window.confirm('Discard this workout? Everything you have logged so far will be deleted. This cannot be undone.')) return;
    items = []; fTitle.value = ''; fNotes.value = ''; started = false; began = 0; clearInterval(timerHandle);
    try { localStorage.removeItem(D.storeKey); } catch (e) {}
    fetch(D.draftUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' }, body: JSON.stringify({ _csrf: csrf, clear: true }) }).catch(function () {});
    document.getElementById('resumebar').hidden = true;
    dirty = false; setState('Discarded'); render(); showPhase();
  }
  Array.prototype.forEach.call(document.querySelectorAll('.discardbtn'), function (b) { b.addEventListener('click', doDiscard); });

  /* ---------- finish ---------- */
  var dlg = document.getElementById('unticked');
  function build() {
    hidden.innerHTML = '';
    items.forEach(function (it, i) {
      hidden.appendChild(el('input', { type: 'hidden', name: 'ex[' + i + '][id]', value: String(it.id) }));
      hidden.appendChild(el('input', { type: 'hidden', name: 'ex[' + i + '][note]', value: it.note || '' }));
      it.sets.forEach(function (s, j) {
        var pre = 'ex[' + i + '][sets][' + j + ']';
        ['w', 'r', 't', 'd'].forEach(function (k) { hidden.appendChild(el('input', { type: 'hidden', name: pre + '[' + k + ']', value: s[k] === undefined ? '' : String(s[k]) })); });
        hidden.appendChild(el('input', { type: 'hidden', name: pre + '[wu]', value: s.wu ? '1' : '0' }));
        hidden.appendChild(el('input', { type: 'hidden', name: pre + '[k]', value: s.k ? '1' : '0' }));
      });
    });
  }
  function reallySubmit() {
    submitting = true; clearTimeout(timer);
    document.getElementById('f_dur').value = began ? String(Math.floor((Date.now() - began) / 1000)) : '0';
    if (!D.edit) { try { localStorage.removeItem(D.storeKey); } catch (e) {} }
    build();
    document.getElementById('savebtn').disabled = true;
    var ov = document.getElementById('fsave');
    var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!ov || D.edit || still) { form.submit(); return; }
    var target = parseInt((document.getElementById('estpts') || {}).textContent, 10) || 0;
    var out = document.getElementById('fsvpts'), t0 = Date.now(), dur = 1800;
    ov.hidden = false; void ov.offsetWidth; ov.classList.add('on');
    (function tick() {
      var k = Math.min(1, (Date.now() - t0) / dur);
      if (out) out.textContent = String(Math.round(target * (1 - Math.pow(1 - k, 3))));
      if (k < 1) requestAnimationFrame(tick);
    })();
    setTimeout(function () { form.submit(); }, 2000);
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (submitting) return;
    if (!items.length) { window.alert('Add at least one exercise first.'); return; }
    var unticked = 0;
    items.forEach(function (it) { it.sets.forEach(function (s) { if (!s.k) unticked++; }); });
    if (unticked > 0 && dlg && dlg.showModal) {
      document.getElementById('unn').textContent = String(unticked);
      dlg.showModal();
      return;
    }
    reallySubmit();
  });
  if (D.autoRoutine && !D.edit) {
    var ar = routines.filter(function (x) { return x.id === D.autoRoutine; })[0];
    if (ar && !items.length) { if (!started) beginWorkout(true); startRoutine(ar); }
  }
  if (dlg) {
    document.getElementById('tickall').addEventListener('click', function () { items.forEach(function (it) { it.sets.forEach(function (s) { s.k = 1; }); }); dlg.close(); reallySubmit(); });
    document.getElementById('skipun').addEventListener('click', function () { dlg.close(); reallySubmit(); });
    document.getElementById('cancelun').addEventListener('click', function () { dlg.close(); });
  }
})();
