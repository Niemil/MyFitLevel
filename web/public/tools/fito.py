#!/usr/bin/env python3
"""
fito.py - collects PUBLIC, non-personal Fitocracy info from the Wayback Machine
(levels, achievements, quests, exercise lists, help/FAQ, blog, about) plus a few
reference images, and sends a compact digest to your MyFitLevel admin page.

Needs only Python 3.8+. No packages.

  curl -fsSL https://myfitlevel.com/tools/fito.py -o fito.py
  nohup python3 fito.py all --token YOURTOKEN > fito.log 2>&1 &
  tail -f fito.log

Commands: all | list | fetch | text | digest | images | upload
Personal pages (profiles, workouts, feeds, people) are skipped on purpose.
Images are for private reference only - do not republish them.
Pause between requests defaults to 3 s to be kind to archive.org. Safe to stop and restart.
"""
import argparse, hashlib, html.parser, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request, zipfile

SITE = "https://myfitlevel.com"
UA = "MyFitLevel-research/1.5 (hobby project; polite, rate-limited)"
CDX = "https://web.archive.org/cdx/search/cdx"
DOMAINS = ["fitocracy.com/*", "blog.fitocracy.com/*", "help.fitocracy.com/*", "support.fitocracy.com/*"]
INCLUDE = re.compile(r"(achievement|quest|level|point|activit|exercise|faq|help|about|blog|hero|feature|how|rules|guide|challenge|duel|group|tour|press|terms|badge|prop|track|routine|workout-plan|plans|stats|mobile|app)", re.I)
EXCLUDE = re.compile(r"/(profile|profiles|people|person|user|users|u|workout|workouts|feed|inbox|messages|settings|account|login|signup|register|yearly-performance|dashboard|followers|following|comment|comments|props)(/|$|\?)", re.I)


def get(url, tries=5, pause=3.0, binary=False, timeout=60):
    delay = pause
    for _ in range(tries):
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        try:
            with urllib.request.urlopen(req, timeout=timeout) as r:
                data = r.read()
                return data if binary else data.decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            if e.code in (429, 503, 502, 504):
                delay = min(delay * 2, 120)
                print(f"  slow down ({e.code}); waiting {delay:.0f}s", flush=True)
                time.sleep(delay)
                continue
            if e.code in (404, 403, 400):
                return None
            raise
        except (urllib.error.URLError, TimeoutError, ConnectionError) as e:
            print(f"  network problem ({e}); retry in {delay:.0f}s", flush=True)
            time.sleep(delay)
            delay = min(delay * 2, 120)
    return None


def wanted(url, inc=INCLUDE):
    path = urllib.parse.urlparse(url).path or "/"
    return bool(inc.search(path)) and not EXCLUDE.search(path)


# Second pass: ask the index for each interesting section on its own, because one big
# alphabetical query can run out of rows before it reaches /achievements, /levels etc.
SECTIONS = ["achievements", "achievement", "levels", "level", "quests", "quest", "faq", "help", "about", "hero", "how-it-works",
            "howitworks", "knowledge", "knowledge-center", "leaderboard", "leaderboards", "groups", "activities", "activity",
            "exercises", "terms", "privacy", "tour", "features", "points", "challenges", "duels", "titles", "badges", "props",
            "track", "tracking", "getting-started", "mobile", "press", "story", "coaching", "team", "ambassador", "connect",
            "explore", "routines", "plans", "stats", "workout-plans", "support", "blog", "faq/levels", "faq/points"]
EXTRA_DOMAINS = ["fitocracy.zendesk.com/*", "fitocracy.desk.com/*", "support.fitocracy.com/*", "help.fitocracy.com/*",
                 "blog.fitocracy.com/*", "forum.fitocracy.com/*", "fitocracy.tumblr.com/*"]


def cmd_list(a):
    os.makedirs(a.out, exist_ok=True)
    inc = re.compile(a.include, re.I) if a.include else INCLUDE
    seen, rows = set(), []
    old = os.path.join(a.out, "urls.tsv")
    if os.path.exists(old):  # keep what we already have
        for l in open(old):
            ts, _, o = l.rstrip("\n").partition("\t")
            if o:
                seen.add(re.sub(r"[?#].*$", "", o.lower().replace("http://", "https://").replace("www.", "")))
                rows.append((ts, o))
    targets = [(d, a.limit, inc) for d in DOMAINS]
    if a.sections:
        everything = re.compile(".")
        for sec in SECTIONS:
            for host in ("fitocracy.com", "www.fitocracy.com"):
                targets.append((f"{host}/{sec}*", 300, everything))
        for d in EXTRA_DOMAINS:
            targets.append((d, 1500, everything))
    for dom, lim, inc in targets:
        q = {"url": dom, "output": "json", "fl": "timestamp,original,mimetype,statuscode",
             "filter": ["statuscode:200", "mimetype:text/html"], "collapse": "urlkey", "limit": str(lim)}
        print("asking the index about", dom, flush=True)
        raw = get(CDX + "?" + urllib.parse.urlencode(q, doseq=True), tries=2, pause=a.pause, timeout=40)
        time.sleep(a.pause)
        report(a, "Listing pages in the archive index", targets.index((dom, lim, inc)) + 1, len(targets), dom)
        if not raw:
            print("  no answer (skipping)", flush=True)
            continue
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            print("  unreadable answer for", dom, flush=True)
            continue
        for ts, orig, mime, status in data[1:]:
            key = re.sub(r"[?#].*$", "", orig.lower().replace("http://", "https://").replace("www.", ""))
            if key in seen or not wanted(orig, inc):
                continue
            seen.add(key)
            rows.append((ts, orig))
        with open(os.path.join(a.out, "urls.tsv"), "w") as f:  # save progress after every query
            for ts2, o2 in sorted(rows, key=lambda r: r[1]):
                f.write(f"{ts2}\t{o2}\n")
    rows.sort(key=lambda r: r[1])
    with open(os.path.join(a.out, "urls.tsv"), "w") as f:
        for ts, orig in rows:
            f.write(f"{ts}\t{orig}\n")
    print(f"{len(rows)} pages listed", flush=True)


def fname(orig):
    p = urllib.parse.urlparse(orig)
    base = re.sub(r"[^A-Za-z0-9._-]+", "_", (p.netloc + p.path).strip("/")) or "index"
    if p.query:
        base += "_" + hashlib.md5(p.query.encode()).hexdigest()[:6]
    return base[:150] + ".html"


def cmd_fetch(a):
    done, log = set(), os.path.join(a.out, "fetched.jsonl")
    if os.path.exists(log):
        for line in open(log):
            try:
                done.add(json.loads(line)["url"])
            except Exception:
                pass
    todo = [l.rstrip("\n").split("\t") for l in open(os.path.join(a.out, "urls.tsv")) if l.strip()]
    todo = [t for t in todo if t[1] not in done]
    if a.max:
        todo = todo[: a.max]
    os.makedirs(os.path.join(a.out, "html"), exist_ok=True)
    print(f"{len(todo)} pages to fetch, about {len(todo) * a.pause / 60:.0f} min", flush=True)
    for i, (ts, orig) in enumerate(todo, 1):
        data = get(f"https://web.archive.org/web/{ts}id_/{orig}", pause=a.pause, binary=True)
        if data:
            with open(os.path.join(a.out, "html", fname(orig)), "wb") as f:
                f.write(data)
        with open(log, "a") as f:
            f.write(json.dumps({"url": orig, "ts": ts, "status": "ok" if data else "missing"}) + "\n")
        print(f"[{i}/{len(todo)}] {'ok' if data else 'missing'} {orig}", flush=True)
        report(a, "Downloading pages", i, len(todo), orig)
        time.sleep(a.pause)


class Text(html.parser.HTMLParser):
    SKIP = {"script", "style", "noscript", "svg", "head"}
    BLOCK = {"p", "div", "br", "li", "tr", "h1", "h2", "h3", "h4", "h5", "table", "ul", "ol", "section", "article"}

    def __init__(self):
        super().__init__()
        self.out, self.skip = [], 0

    def handle_starttag(self, tag, attrs):
        if tag in self.SKIP:
            self.skip += 1
        if tag in self.BLOCK:
            self.out.append("\n")

    def handle_endtag(self, tag):
        if tag in self.SKIP and self.skip:
            self.skip -= 1
        if tag in self.BLOCK:
            self.out.append("\n")

    def handle_data(self, d):
        if not self.skip:
            self.out.append(d)


def to_text(raw):
    p = Text()
    p.feed(raw)
    t = re.sub(r"[ \t\r\f\v]+", " ", "".join(p.out))
    return re.sub(r"\n\s*\n+", "\n\n", t).strip() + "\n"


def cmd_text(a):
    src, dst = os.path.join(a.out, "html"), os.path.join(a.out, "text")
    os.makedirs(dst, exist_ok=True)
    n = 0
    for name in sorted(os.listdir(src)):
        raw = open(os.path.join(src, name), encoding="utf-8", errors="replace").read()
        open(os.path.join(dst, name[:-5] + ".txt"), "w").write(to_text(raw))
        n += 1
    print(f"{n} text files", flush=True)


def cmd_digest(a):
    """One compact text file (<= ~400 KB) that Claude can read in one go."""
    tdir = os.path.join(a.out, "text")
    files = sorted(os.listdir(tdir), key=lambda n: ("blog" in n.lower() or "tumblr" in n.lower(), n))  # rules/help pages first, blog last
    urls = {}
    p = os.path.join(a.out, "urls.tsv")
    if os.path.exists(p):
        for l in open(p):
            ts, _, o = l.rstrip("\n").partition("\t")
            urls[fname(o)[:-5]] = (ts, o)
    out, total, seen = [], 0, set()
    for n in files:
        t = open(os.path.join(tdir, n), errors="replace").read()
        sig = hashlib.md5(re.sub(r"\W+", "", t[:2000]).lower().encode()).hexdigest()
        if sig in seen or len(t) < 200:
            continue
        seen.add(sig)
        ts, o = urls.get(n[:-4], ("", n))
        chunk = f"\n\n===== {o} (archived {ts}) =====\n{t[:a.per_page]}"
        if total + len(chunk) > a.digest_max:
            break
        out.append(chunk)
        total += len(chunk)
    with open(os.path.join(a.out, "digest.txt"), "w") as f:
        f.write("Fitocracy public pages digest" + "".join(out))
    print(f"digest.txt: {len(out)} pages, {total // 1024} KB", flush=True)


IMG = re.compile(r"""(?:src|data-src|content)=["']([^"']+\.(?:png|jpe?g|gif|webp))["']""", re.I)


def cmd_images(a):
    """Reference images (badges, level icons, UI) straight from archived pages. Capped in count and size."""
    hdir = os.path.join(a.out, "html")
    idir = os.path.join(a.out, "images")
    os.makedirs(idir, exist_ok=True)
    urls = {}
    for l in open(os.path.join(a.out, "urls.tsv")):
        ts, _, o = l.rstrip("\n").partition("\t")
        pth = os.path.join(hdir, fname(o))
        if not os.path.exists(pth):
            continue
        raw = open(pth, encoding="utf-8", errors="replace").read()
        for m in IMG.findall(raw):
            u = urllib.parse.urljoin(o, m)
            if "fitocracy" not in u or re.search(r"(avatar|profile|user|photo|gravatar|facebook|twitter)", u, re.I):
                continue
            urls.setdefault(u, ts)
    print(f"{len(urls)} candidate images", flush=True)
    got = 0
    for u, ts in list(urls.items()):
        if got >= a.max_images:
            break
        target = os.path.join(idir, hashlib.md5(u.encode()).hexdigest()[:10] + os.path.splitext(urllib.parse.urlparse(u).path)[1].lower())
        if os.path.exists(target):
            got += 1
            continue
        data = get(f"https://web.archive.org/web/{ts}im_/{u}", pause=a.pause, binary=True)
        time.sleep(a.pause)
        if data and 300 < len(data) < 400_000:
            open(target, "wb").write(data)
            with open(os.path.join(idir, "_sources.tsv"), "a") as f:
                f.write(f"{os.path.basename(target)}\t{u}\n")
            got += 1
            print(f"  image {got}/{a.max_images} {u}", flush=True)
            report(a, "Downloading images", got, a.max_images, u)
    zp = os.path.join(a.out, "images.zip")
    size = 0
    with zipfile.ZipFile(zp, "w", zipfile.ZIP_STORED) as z:
        for n in sorted(os.listdir(idir)):
            fp = os.path.join(idir, n)
            if size + os.path.getsize(fp) > 7_000_000:
                break
            z.write(fp, n)
            size += os.path.getsize(fp)
    print(f"images.zip: {size // 1024} KB", flush=True)


_last = [0.0]


def report(a, phase, done=0, total=0, msg="", force=False):
    """Tiny progress note shown live on Admin > Fitocracy data (best effort, never stops the run)."""
    if not a.token or (not force and time.time() - _last[0] < 20):
        return
    _last[0] = time.time()
    body = json.dumps({"phase": phase, "done": done, "total": total, "msg": msg[:120], "t": int(time.time())}).encode()
    try:
        req = urllib.request.Request(f"{a.site}/api/v1/fito/upload?name=status.json", data=body, method="POST",
                                     headers={"X-Fito-Token": a.token, "User-Agent": UA})
        urllib.request.urlopen(req, timeout=20).read()
    except Exception:
        pass


def post(a, path, name):
    if not a.token:
        print("no --token, so not uploading; files stay in", a.out)
        return False
    data = open(path, "rb").read()
    req = urllib.request.Request(f"{a.site}/api/v1/fito/upload?name={urllib.parse.quote(name)}", data=data, method="POST",
                                 headers={"X-Fito-Token": a.token, "Content-Type": "application/octet-stream", "User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            print("uploaded", name, r.read().decode()[:100], flush=True)
            return True
    except Exception as e:
        print("upload failed for", name, e, flush=True)
        return False


def cmd_upload(a):
    for n in ("digest.txt", "urls.tsv", "images.zip"):
        p = os.path.join(a.out, n)
        if os.path.exists(p):
            report(a, "Uploading " + n, force=True)
            post(a, p, n)


def cmd_all(a):
    report(a, "Starting", force=True)
    if a.sections or not os.path.exists(os.path.join(a.out, "urls.tsv")):
        report(a, "Listing pages in the archive index", force=True)
        cmd_list(a)
    cmd_fetch(a)
    report(a, "Building digest", force=True)
    cmd_text(a)
    cmd_digest(a)
    cmd_upload_one(a, "digest.txt")  # text first, so it arrives even if images fail
    if not a.no_images:
        cmd_images(a)
    cmd_upload(a)
    report(a, "DONE", 1, 1, "all files uploaded", force=True)
    print("DONE - open Admin > Fitocracy data on the site", flush=True)


def cmd_upload_one(a, n):
    p = os.path.join(a.out, n)
    if os.path.exists(p):
        post(a, p, n)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("cmd", choices=["all", "list", "fetch", "text", "digest", "images", "upload"])
    ap.add_argument("--out", default="fito")
    ap.add_argument("--token", default=os.environ.get("FITO_TOKEN", ""))
    ap.add_argument("--site", default=SITE)
    ap.add_argument("--pause", type=float, default=3.0)
    ap.add_argument("--limit", type=int, default=5000)
    ap.add_argument("--max", type=int, default=0, help="fetch at most this many pages")
    ap.add_argument("--include", default="")
    ap.add_argument("--per-page", type=int, default=6000, dest="per_page")
    ap.add_argument("--digest-max", type=int, default=1_500_000, dest="digest_max")
    ap.add_argument("--max-images", type=int, default=150, dest="max_images")
    ap.add_argument("--sections", action="store_true", help="also ask the index for each section (levels, achievements, faq, help ...) on its own")
    ap.add_argument("--no-images", action="store_true", dest="no_images")
    a = ap.parse_args()
    {"all": cmd_all, "list": cmd_list, "fetch": cmd_fetch, "text": cmd_text, "digest": cmd_digest,
     "images": cmd_images, "upload": cmd_upload}[a.cmd](a)


if __name__ == "__main__":
    main()
