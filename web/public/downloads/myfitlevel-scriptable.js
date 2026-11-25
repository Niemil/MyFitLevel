// MyFitLevel widget for iOS (free app "Scriptable").
// 1) Install Scriptable, make a new script and paste this whole file.
// 2) Add a Scriptable widget to your home or lock screen, long-press it > Edit widget > Script: MyFitLevel,
//    and type your API key in the "Parameter" field (MyFitLevel > Settings > API access).
const KEY = (args.widgetParameter || "").trim();
const SD = false; // SnakeDiet has its own widgets on snakediet.org
const SITE = SD
  ? { name: "SnakeDiet", base: "https://snakediet.org", accent: "#2bb3a3", pts: "KTN points", open: "https://snakediet.org/",
      tiles: [["streak", "streak"], ["fast_hours", "fasting h"], ["week_points", "week KTN"]] }
  : { name: "MyFitLevel", base: "https://myfitlevel.com", accent: "#36a85c", pts: "points", open: "https://myfitlevel.com/log",
      tiles: [["streak", "streak"], ["week_workouts", "this week"], ["prs", "PRs"]] };
const BASE = SITE.base;

const ACCENT = new Color(SITE.accent);
const BG1 = new Color("#0f1b2a");
const BG2 = new Color("#1c2f45");

async function load() {
  const r = new Request(BASE + "/api/v1/widget?key=" + encodeURIComponent(KEY));
  r.timeoutInterval = 15;
  return await r.loadJSON();
}

function bar(width, height, pct) {
  const ctx = new DrawContext();
  ctx.size = new Size(width, height);
  ctx.opaque = false;
  ctx.respectScreenScale = true;
  ctx.setFillColor(new Color("#ffffff", 0.18));
  ctx.fillPath(roundedRect(0, 0, width, height));
  ctx.setFillColor(ACCENT);
  ctx.fillPath(roundedRect(0, 0, Math.max(height, width * pct / 100), height));
  return ctx.getImage();
}
function roundedRect(x, y, w, h) {
  const p = new Path();
  p.addRoundedRect(new Rect(x, y, w, h), h / 2, h / 2);
  return p;
}

async function build() {
  const w = new ListWidget();
  const g = new LinearGradient();
  g.colors = [BG2, BG1];
  g.locations = [0, 1];
  w.backgroundGradient = g;
  w.setPadding(14, 14, 14, 14);
  if (!KEY) {
    const t = w.addText("Add your API key as the widget parameter");
    t.textColor = Color.white(); t.font = Font.systemFont(13);
    return w;
  }
  let d;
  try { d = await load(); } catch (e) { d = { ok: false, error: "No connection" }; }
  if (!d.ok) {
    const t = w.addText(SITE.name + ": " + (d.error || "error"));
    t.textColor = new Color("#ff7878"); t.font = Font.systemFont(13);
    return w;
  }
  const top = w.addStack(); top.centerAlignContent();
  const lv = top.addText("LV " + d.level);
  lv.font = Font.boldSystemFont(24); lv.textColor = ACCENT;
  top.addSpacer();
  const nm = top.addText(d.name);
  nm.font = Font.semiboldSystemFont(13); nm.textColor = Color.white(); nm.lineLimit = 1;
  w.addSpacer(6);
  const img = w.addImage(bar(150, 8, d.level_pct));
  img.imageSize = new Size(150, 8);
  w.addSpacer(3);
  const xt = w.addText(d.level_xp.toLocaleString() + " / " + d.level_need.toLocaleString() + " to next level");
  xt.font = Font.systemFont(10); xt.textColor = new Color("#ffffff", 0.7);
  w.addSpacer(8);
  const row = w.addStack();
  SITE.tiles.map(function (t) { return [d[t[0]] === undefined ? "-" : d[t[0]], t[1]]; }).forEach(function (s, i) {
    if (i) row.addSpacer();
    const c = row.addStack(); c.layoutVertically();
    const n = c.addText(String(s[0])); n.font = Font.boldSystemFont(18); n.textColor = Color.white();
    const l = c.addText(s[1]); l.font = Font.systemFont(9); l.textColor = new Color("#ffffff", 0.65);
  });
  w.addSpacer(6);
  const tt = w.addText((SD ? "" : "Today: ") + (d.today_label || d.today_routine || ""));
  tt.font = Font.systemFont(11); tt.textColor = ACCENT; tt.lineLimit = 1;
  w.refreshAfterDate = new Date(Date.now() + 15 * 60 * 1000);
  w.url = SITE.open;
  return w;
}

const widget = await build();
if (config.runsInWidget) { Script.setWidget(widget); } else { await widget.presentMedium(); }
Script.complete();
