/* Shared search helper: turns short forms into the full words ("db" -> "dumbbell"). */
(function () {
  'use strict';
  var MAP = {
    db: 'dumbbell', dbs: 'dumbbell', bb: 'barbell', kb: 'kettlebell', bw: 'bodyweight', sb: 'smith', smith: 'smith',
    ohp: 'overhead press', bp: 'bench press', rdl: 'romanian deadlift', dl: 'deadlift', sldl: 'stiff deadlift',
    sq: 'squat', ez: 'ez', pd: 'pulldown', lat: 'lat', tri: 'triceps', tris: 'triceps', bi: 'biceps', bis: 'biceps', delt: 'shoulder', delts: 'shoulder', quad: 'leg', quads: 'leg', glutes: 'glute', abs: 'core'
  };
  window.MFLSearch = {
    terms: function (q) {
      var out = [];
      String(q || '').toLowerCase().split(/\s+/).filter(Boolean).forEach(function (t) {
        if (MAP[t]) MAP[t].split(' ').forEach(function (w) { out.push(w); }); else out.push(t);
      });
      return out;
    }
  };
})();
