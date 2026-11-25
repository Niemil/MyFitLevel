#!/usr/bin/env python3
"""
fito_more.py - second helper for the Fitocracy research archive. Run it on your Raspberry Pi.

  sudo apt install -y tesseract-ocr python3-pil          # once (OCR + image resize)
  curl -fsSL https://myfitlevel.com/tools/fito_more.py -o fito_more.py
  nohup python3 fito_more.py all --token YOURTOKEN --out ~/fito > fito_more.log 2>&1 &
  tail -f fito_more.log

What it does, slowly and politely (safe to stop and restart, finished work is remembered):
  ocr     reads the screenshots already collected (out/images/*.jpg, e.g. from fito_data.py) with OCR and writes
            ocr.tsv         every line that looks like a set with points ("Set 14 (67 pts)", "185 lb x 5 ... 68 pts")
            levels_found.tsv  level / total points / "pts to next level" lines (e.g. "13041 pts to Level 39")
  sets    asks the Wayback Machine for old Fitocracy workout/activity pages and keeps ONLY the numbers
            (exercise, weight, reps, points) in sets.tsv. No names, no profile text, no comments are saved.
  upload  sends ocr.tsv, levels_found.tsv, sets.tsv and a live status to the archive page on myfitlevel.com
  all     ocr + sets + upload

The archive page shows the live status ("OCR 12/80") while this runs. Add --no-upload to keep everything local.
"""
import argparse, glob, json, os, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request

UA = "MyFitLevel-research/2.0 (hobby project; polite, rate-limited)"
SITE = "https://myfitlevel.com"
PAUSE = 3.0

PTS = re.compile(r"\(?\+?\s*([\d,]{1,7})\s*(?:pts?|points?)\b\)?", re.I)
SET = re.compile(r"(\d+(?:[.,]\d+)?)\s*(kg|lbs?|lb)?\s*(?:x|×|for)\s*(\d{1,3})\b", re.I)
LEVEL_TOT = re.compile(r"([\d]{1,3}(?:,\d{3})+|\d{4,8})\s*Total\s*Points", re.I)
LEVEL_NUM = re.compile(r"\b(\d{1,3})\s*Level\b|\bLevel\s*(\d{1,3})\b", re.I)
TO_NEXT = re.compile(r"([\d,]{2,8})\s*pts?\s*to\s*Level\s*(\d{1,3})", re.I)
EXERCISE = re.compile(r"(squat|bench|deadlift|press|row|curl|pull-?up|chin-?up|dip|lunge|raise|fly|extension|pulldown|shrug|"
                      r"push-?up|sit-?up|crunch|plank|run|walk|swim|cycl|bike|row|jog|hike|kettlebell|snatch|clean|thrust)", re.I)


# ---------- status (shown live on the archive page) ----------
class Status:
    def __init__(self, a):
        self.a, self.last = a, 0.0

    def put(self, phase, done=0, total=0, msg="", force=False):
        now = time.time()
        print(f"[{phase}] {done}/{total} {msg}", flush=True)
        if not self.a.token or self.a.no_upload or (not force and now - self.last < 20):
            return
        self.last = now
        body = json.dumps({"phase": phase, "done": done, "total": total, "msg": msg[:120], "t": int(now)}).encode()
        send(self.a, "status.json", body)


def send(a, name, data):
    if not a.token or a.no_upload:
        return
    req = urllib.request.Request(f"{a.site}/api/v1/fito/upload?name={name}", data=data, method="POST",
                                 headers={"X-Fito-Token": a.token, "User-Agent": UA})
    try:
        urllib.request.urlopen(req, timeout=120).read()
    except Exception as e:
        print("  upload problem:", name, e, flush=True)


def get(url, tries=4, binary=False):
    d = PAUSE
    for _ in range(tries):
        time.sleep(PAUSE)
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=60) as r:
                b = r.read(4_000_000)
                return b if binary else b.decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            if e.code in (429, 502, 503, 504):
                d = min(d * 2, 180); print(f"  slow down ({e.code}); waiting {d:.0f}s", flush=True); time.sleep(d); continue
            return None
        except Exception as e:
            print(f"  network problem ({e})", flush=True); time.sleep(d); d = min(d * 2, 180)
    return None


# ---------- OCR ----------
def ocr_text(path, psm):
    """Run tesseract; enlarge small screenshots first because OCR reads small digits badly."""
    src = path
    try:
        from PIL import Image, ImageOps
        im = Image.open(path).convert("L")
        if im.width < 1800:
            f = 1800 / im.width
            im = im.resize((int(im.width * f), int(im.height * f)))
        im = ImageOps.autocontrast(im)
        src = "/tmp/_fito_ocr.png"
        im.save(src)
    except Exception:
        pass
    try:
        r = subprocess.run(["tesseract", src, "-", "--psm", str(psm)], capture_output=True, text=True, timeout=120)
        return r.stdout
    except FileNotFoundError:
        sys.exit("tesseract is missing. Run: sudo apt install -y tesseract-ocr python3-pil")
    except Exception:
        return ""


def parse_ocr(text):
    """-> (set_rows, level_rows). Each set row: (exercise line, weight, unit, reps, points)."""
    lines = [re.sub(r"\s+", " ", l).strip() for l in text.replace("\r", "").split("\n") if l.strip()]
    sets, levels = [], []
    for i, l in enumerate(lines):
        for m in TO_NEXT.finditer(l):
            levels.append(("to_next", int(m.group(2)), int(m.group(1).replace(",", "")), l))
        joined = l + " " + (lines[i + 1] if i + 1 < len(lines) else "")
        pair = re.search(r"\b(\d{1,3})\s+(\d{1,3}(?:,\d{3})+)\b", joined) if re.search(r"level", joined, re.I) and re.search(r"total", joined, re.I) else None
        if pair:
            levels.append(("total", int(pair.group(1)), int(pair.group(2).replace(",", "")), joined[:120]))
        m = None if pair else LEVEL_TOT.search(joined)
        if m:
            lv = None
            for x in lines[max(0, i - 2):i + 2]:
                lm = LEVEL_NUM.search(x)
                if lm:
                    lv = int(lm.group(1) or lm.group(2)); break
            levels.append(("total", lv or 0, int(m.group(1).replace(",", "")), l))
        pm = PTS.search(l)
        if pm and "to level" not in l.lower() and "total" not in l.lower():
            near = " | ".join(lines[max(0, i - 2):i + 1])
            sm = SET.search(near)
            if sm or EXERCISE.search(near):
                pts = int(pm.group(1).replace(",", ""))
                if 0 < pts < 100000:
                    sets.append((near[:200], sm.group(1) if sm else "", (sm.group(2) or "") if sm else "", sm.group(3) if sm else "", pts))
    return sets, levels


def cmd_ocr(a, st):
    imgs = sorted(glob.glob(os.path.join(a.out, "images", "*.*")) + glob.glob(os.path.join(a.out, "images_extra", "*.*")))
    imgs = [p for p in imgs if re.search(r"\.(jpe?g|png|webp)$", p, re.I)]
    done_f = os.path.join(a.out, "ocr_done.txt")
    done = set(open(done_f).read().split("\n")) if os.path.exists(done_f) else set()
    tsv, lvl = os.path.join(a.out, "ocr.tsv"), os.path.join(a.out, "levels_found.tsv")
    for i, p in enumerate(imgs, 1):
        name = os.path.basename(p)
        if name in done:
            continue
        st.put("OCR screenshots", i, len(imgs), name)
        txt = ocr_text(p, 6) + "\n" + ocr_text(p, 11)
        sets, levels = parse_ocr(txt)
        seen = set()
        with open(tsv, "a", encoding="utf-8") as f:
            for s in sets:
                row = "\t".join([name] + [str(x) for x in s])
                if row not in seen:
                    seen.add(row); f.write(row + "\n")
        with open(lvl, "a", encoding="utf-8") as f:
            for l in levels:
                f.write("\t".join([name] + [str(x) for x in l]) + "\n")
        with open(done_f, "a") as f:
            f.write(name + "\n")
    st.put("OCR screenshots", len(imgs), len(imgs), "finished", force=True)


# ---------- archived workout pages: numbers only ----------
def strip_tags(h):
    h = re.sub(r"(?is)<(script|style).*?</\1>", " ", h)
    h = re.sub(r"(?i)<br\s*/?>|</p>|</li>|</div>|</tr>|</h\d>", "\n", h)
    import html as _h
    return _h.unescape(re.sub(r"<[^>]+>", " ", h))


def cmd_sets(a, st):
    out = os.path.join(a.out, "sets.tsv")
    cdx = os.path.join(a.out, "sets_urls.txt")
    if not os.path.exists(cdx):
        urls = []
        for dom in ("fitocracy.com/activity/*", "fitocracy.com/workout/*", "fitocracy.com/entry/*"):
            q = {"url": dom, "output": "json", "fl": "timestamp,original", "filter": ["statuscode:200", "mimetype:text/html"],
                 "collapse": "urlkey", "limit": str(a.limit)}
            qs = urllib.parse.urlencode(q, doseq=True)
            t = get("https://web.archive.org/cdx/search/cdx?" + qs)
            try:
                for ts, orig in json.loads(t)[1:]:
                    urls.append(f"https://web.archive.org/web/{ts}id_/{orig}")
            except Exception:
                print("  no list for", dom, flush=True)
        open(cdx, "w").write("\n".join(urls))
    urls = open(cdx).read().split()
    done_f = os.path.join(a.out, "sets_done.txt")
    done = set(open(done_f).read().split("\n")) if os.path.exists(done_f) else set()
    n = 0
    for i, u in enumerate(urls, 1):
        if u in done:
            continue
        st.put("Archived workout pages (numbers only)", i, len(urls), f"{n} sets so far")
        t = get(u)
        if t:
            sets, _ = parse_ocr(strip_tags(t))
            with open(out, "a", encoding="utf-8") as f:
                for s in sets:
                    # keep numbers and the exercise words only; strip everything that could be a name
                    ex = " ".join(w for w in re.findall(r"[A-Za-z][A-Za-z\-\(\)]+", s[0]) if EXERCISE.search(w) or w.istitle())[:80]
                    f.write("\t".join([ex, s[1], s[2], s[3], str(s[4])]) + "\n"); n += 1
        with open(done_f, "a") as f:
            f.write(u + "\n")
    st.put("Archived workout pages (numbers only)", len(urls), len(urls), f"finished, {n} sets", force=True)


def cmd_upload(a, st):
    for name in ("ocr.tsv", "levels_found.tsv", "sets.tsv"):
        p = os.path.join(a.out, name)
        if os.path.exists(p) and os.path.getsize(p) > 0:
            send(a, name, open(p, "rb").read())
            print("uploaded", name, os.path.getsize(p), "bytes", flush=True)
    st.put("Done", 1, 1, "all files uploaded", force=True)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("cmd", choices=["all", "ocr", "sets", "upload"])
    ap.add_argument("--out", default="out", help="folder that holds images/ (from fito_data.py)")
    ap.add_argument("--token", default="")
    ap.add_argument("--site", default=SITE)
    ap.add_argument("--limit", type=int, default=2000, help="max archived workout pages per kind")
    ap.add_argument("--no-upload", action="store_true")
    a = ap.parse_args()
    a.out = os.path.expanduser(a.out)
    os.makedirs(a.out, exist_ok=True)
    st = Status(a)
    if a.cmd in ("all", "ocr"):
        cmd_ocr(a, st)
        cmd_upload(a, st)
    if a.cmd in ("all", "sets"):
        cmd_sets(a, st)
        cmd_upload(a, st)
    if a.cmd == "upload":
        cmd_upload(a, st)


if __name__ == "__main__":
    main()
