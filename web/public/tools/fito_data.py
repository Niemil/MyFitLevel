#!/usr/bin/env python3
"""
fito_data.py - finds PUBLIC posts where people pasted old Fitocracy workouts (text or screenshots)
so we can calibrate MyFitLevel's points. Run it on your Raspberry Pi. Python 3.8+, no packages.

  curl -fsSL https://myfitlevel.com/tools/fito_data.py -o fito_data.py
  nohup python3 fito_data.py all --out /media/YOURDRIVE/fito > fito_data.log 2>&1 &
  tail -f fito_data.log

It does three things, slowly and politely (3+ s between requests, safe to stop and restart):
  1. search   Reddit (r/Fitocracy and site-wide), DuckDuckGo and the Wayback Machine for posts about Fitocracy points
  2. extract  lines that look like "exercise weight x reps ... N pts" and save them to out/calib.tsv
  3. images   download ALL screenshots that posts share (imgur / i.redd.it) to out/images/ (no limit; use --max-images N to cap)
Usernames are NOT saved - only the numbers and the exercise text. Images are for private reference only.
Result: out/calib.tsv (single lines), out/calib_blocks.txt (whole pasted logs, with exercise names - the most useful file),  out/images/ and out/calib_images.zip. Send those to Claude, or add --token YOURTOKEN to upload them to the admin page.
"""
import argparse, hashlib, html, io, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request, zipfile

UA = "Mozilla/5.0 (compatible; MyFitLevel-research/1.0; hobby project; polite)"
PAUSE = 3.0
QUERIES = [
    "fitocracy workout points squat", "fitocracy bench press points", "fitocracy deadlift points", "fitocracy \"pts\" workout log",
    "fitocracy how many points for", "fitocracy points formula", "fitocracy my workout today points", "fitocracy first workout points",
    "fitocracy level up points workout", "fitocracy screenshot workout", "fitocracy points per set", "fitocracy points running",
    "fitocracy imgur", "fitocracy my log", "fitocracy workout history", "fitocracy PR points", "fitocracy overhead press points",
    "fitocracy barbell row points", "fitocracy pull up points", "fitocracy leg press points", "fitocracy lat pulldown points",
    "fitocracy dumbbell points", "fitocracy cardio points", "fitocracy stronglifts", "fitocracy 5x5 points", "fitocracy ppl routine",
    "fitocracy level 10", "fitocracy level 20", "fitocracy level 30", "fitocracy level 50", "fitocracy achievements screenshot",
]
SUBS = ["Fitocracy", "fitness", "bodyweightfitness", "weightlifting", "powerlifting", "xxfitness", "loseit", "gainit"]
PTS = re.compile(r"(\d{1,5})\s*(?:pts?|points?)\b", re.I)
SETLIKE = re.compile(r"(\d+(?:[.,]\d+)?)\s*(kg|lbs?|lb)?\s*(?:x|×|for)\s*(\d{1,3})\b", re.I)


def get(url, binary=False, tries=4, accept=None):
    d = PAUSE
    for _ in range(tries):
        time.sleep(PAUSE)
        h = {"User-Agent": UA}
        if accept:
            h["Accept"] = accept
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=h), timeout=40) as r:
                b = r.read(8_000_000)
                return b if binary else b.decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            if e.code in (429, 503, 502, 504):
                d = min(d * 2, 180); print(f"  slow down ({e.code}), waiting {d:.0f}s", flush=True); time.sleep(d); continue
            print(f"  HTTP {e.code} from {urllib.parse.urlparse(url).netloc}", flush=True)
            return None
        except Exception as e:
            print(f"  network problem ({e})", flush=True); time.sleep(d); d = min(d * 2, 180)
    return None


def reddit_listing(url, pages=10):
    """Follow Reddit's 'after' cursor so we get far more than the first 100 hits."""
    after = ""
    for _ in range(pages):
        t = get(url + ("&after=" + after if after else ""), accept="application/json")
        try:
            d = json.loads(t)["data"]
        except Exception:
            return
        for c in d.get("children", []):
            yield c
        after = d.get("after") or ""
        if not after:
            return



def jget(url):
    t = get(url, accept="application/json")
    try:
        return json.loads(t) if t else None
    except Exception:
        return None


def archive_posts():
    """Reddit archives (not Reddit itself): PullPush and Arctic Shift hold old posts AND comments with full text."""
    now = int(time.time())
    # PullPush: text search, newest to oldest, page by page
    for kind in ("submission", "comment"):
        for q in ("fitocracy pts", "fitocracy points", "fitocracy workout", "fitocracy squat", "fitocracy bench", "fitocracy deadlift", "fitocracy"):
            before = now
            for page in range(60):
                d = jget(f"https://api.pullpush.io/reddit/search/{kind}/?q={urllib.parse.quote(q)}&size=100&sort=desc&sort_type=created_utc&before={before}")
                data = (d or {}).get("data") or []
                if not data:
                    break
                print(f"pullpush {kind} '{q}' page {page + 1}: {len(data)} items", flush=True)
                for x in data:
                    yield from item_out(x, kind)
                before = min(int(x.get("created_utc", before)) for x in data) - 1
    # Arctic Shift: the whole r/Fitocracy subreddit, every post and comment it has
    for kind in ("posts", "comments"):
        before = now
        for page in range(400):
            d = jget(f"https://arctic-shift.photon-reddit.com/api/{kind}/search?subreddit=Fitocracy&limit=100&sort=desc&before={before}")
            data = (d or {}).get("data") or []
            if not data:
                break
            print(f"arctic-shift {kind} page {page + 1}: {len(data)} items", flush=True)
            for x in data:
                yield from item_out(x, kind)
            before = min(int(x.get("created_utc", before)) for x in data) - 1


def item_out(x, kind):
    link = x.get("permalink") or ""
    url = ("https://www.reddit.com" + link) if link.startswith("/") else (link or "reddit")
    imgs = []
    u = x.get("url") or ""
    if re.search(r"\.(png|jpe?g)$", u, re.I) or "imgur.com" in u:
        imgs.append(u)
    for m in (x.get("media_metadata") or {}).values():
        if isinstance(m, dict) and m.get("s", {}).get("u"):
            imgs.append(html.unescape(m["s"]["u"]))
    body = x.get("selftext") or x.get("body") or ""
    imgs += re.findall(r"https?://(?:i\.imgur\.com|i\.redd\.it|preview\.redd\.it)/[^\s)\]\"']+\.(?:png|jpe?g)", body)
    yield url, x.get("title", ""), body, imgs


def find_posts(out):
    """Yield (url, title, text, [image urls]) from Reddit search."""
    yield from archive_posts()
    probe = get("https://www.reddit.com/search.json?limit=1&q=fitocracy", accept="application/json")
    if not probe:
        print("Reddit itself blocks this computer; skipping direct Reddit and using the archives + web search instead.", flush=True)
        return
    seen = set()
    urls = []
    for q in QUERIES:
        urls.append("https://www.reddit.com/search.json?limit=100&sort=relevance&q=" + urllib.parse.quote(q))
    for s in SUBS:
        for q in ("points", "workout points", "screenshot"):
            urls.append(f"https://www.reddit.com/r/{s}/search.json?restrict_sr=1&limit=100&q={urllib.parse.quote('fitocracy ' + q if s != 'Fitocracy' else q)}")
    for u in urls:
        print("search", u[:110], flush=True)
        for c in reddit_listing(u):
            d = c.get("data", {})
            pid = d.get("id")
            if not pid or pid in seen:
                continue
            seen.add(pid)
            blob = (d.get("title", "") + " " + d.get("selftext", "")).lower()
            if "fitocracy" not in blob and d.get("subreddit", "").lower() != "fitocracy":
                continue
            imgs = []
            if re.search(r"\.(png|jpe?g|gif)$", d.get("url", ""), re.I) or "imgur.com" in d.get("url", ""):
                imgs.append(d["url"])
            for m in (d.get("media_metadata") or {}).values():
                if m.get("s", {}).get("u"):
                    imgs.append(html.unescape(m["s"]["u"]))
            yield "https://www.reddit.com" + d.get("permalink", ""), d.get("title", ""), d.get("selftext", ""), imgs
            # comments often hold pasted logs
            cj = get("https://www.reddit.com" + d.get("permalink", "") + ".json?limit=200", accept="application/json")
            try:
                for blk in json.loads(cj)[1]["data"]["children"]:
                    yield from walk_comment(blk, d.get("permalink", ""))
            except Exception:
                pass


def walk_comment(node, link):
    d = node.get("data", {})
    if node.get("kind") == "t1":
        yield "https://www.reddit.com" + link, "", d.get("body", ""), []
        r = d.get("replies")
        if isinstance(r, dict):
            for ch in r["data"]["children"]:
                yield from walk_comment(ch, link)


def ddg_pages():
    for q in QUERIES:
        t = get("https://html.duckduckgo.com/html/?q=" + urllib.parse.quote(q + " -site:fitocracy.com"))
        for m in re.finditer(r'uddg=([^"&]+)', t or ""):
            u = urllib.parse.unquote(m.group(1))
            if re.match(r"https?://", u) and "fitocracy.com" not in u:
                yield u


def wayback_pages():
    t = get("https://web.archive.org/cdx/search/cdx?url=reddit.com/r/fitocracy/*&output=txt&fl=original&collapse=urlkey&limit=400&filter=statuscode:200")
    for u in (t or "").split():
        if re.search(r"/comments/", u):
            yield "https://web.archive.org/web/2/" + u


def strip_tags(h):
    h = re.sub(r"(?is)<(script|style).*?</\1>", " ", h)
    h = re.sub(r"(?i)<br\s*/?>|</p>|</li>|</div>|</tr>", "\n", h)
    return html.unescape(re.sub(r"<[^>]+>", " ", h))


def extract(text):
    """Lines that mention points and look like a set (weight x reps) or sit next to one."""
    lines = [re.sub(r"\s+", " ", l).strip() for l in text.replace("\r", "").split("\n")]
    hits = []
    for i, l in enumerate(lines):
        if not l or len(l) > 300:
            continue
        near = " | ".join(x for x in lines[max(0, i - 1):i + 2] if x)
        if re.search(r"\*\*[^*]*\(\d+ points?\)\*\*", l):
            continue   # r/Fitness "Fittit" karma lists, not workouts
        if PTS.search(l) and (SETLIKE.search(near) or re.search(r"\b(squat|bench|deadlift|press|row|curl|pull|run|walk|swim|cycl)", near, re.I)):
            hits.append(near[:400])
    return hits


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("cmd", choices=["all", "search", "images", "upload"])
    ap.add_argument("--out", default="out")
    ap.add_argument("--token", default="")
    ap.add_argument("--site", default="https://myfitlevel.com")
    ap.add_argument("--max-images", type=int, default=0, help="0 = no limit")
    a = ap.parse_args()
    os.makedirs(a.out + "/images", exist_ok=True)
    tsv = os.path.join(a.out, "calib.tsv")
    if a.cmd in ("all", "search"):
        done = set(open(tsv, encoding="utf-8").read().split("\n")) if os.path.exists(tsv) else set()
        imgfile = os.path.join(a.out, "image_urls.txt")
        imgs = set(open(imgfile).read().split()) if os.path.exists(imgfile) else set()
        n = 0
        blocks = os.path.join(a.out, "calib_blocks.txt")
        def save(src, text, title=""):
            nonlocal n
            # a pasted log: keep the WHOLE text so exercise names, dates and bodyweight come with it
            sets = [l for l in text.split("\n") if PTS.search(l) and SETLIKE.search(l)]
            if len(sets) >= 3:
                head = "=== " + src + ((" | " + title) if title else "") + "\n"
                with open(blocks, "a", encoding="utf-8") as f:
                    f.write(head + text[:8000] + "\n\n")
            for h in extract(text):
                row = src + "\t" + h
                if row not in done:
                    done.add(row); n += 1
                    with open(tsv, "a", encoding="utf-8") as f:
                        f.write(row + "\n")
        for url, title, body, im in find_posts(a.out):
            save(url, title + "\n" + body, title)
            imgs.update(im)
        for gen in (ddg_pages, wayback_pages):
            for u in gen():
                print("page", u[:100], flush=True)
                t = get(u)
                if t:
                    save(u, strip_tags(t))
                    imgs.update(re.findall(r'https?://(?:i\.imgur\.com|i\.redd\.it|preview\.redd\.it)/[^\s"\'<>]+\.(?:png|jpe?g)', t))
        open(imgfile, "w").write("\n".join(sorted(imgs)))
        print(f"found {n} new lines with points, {len(imgs)} image links", flush=True)
    if a.cmd in ("all", "images"):
        imgfile = os.path.join(a.out, "image_urls.txt")
        urls = open(imgfile).read().split() if os.path.exists(imgfile) else []
        got = 0
        seen_hash = set()
        for i, u in enumerate(urls):
            if a.max_images and got >= a.max_images:
                break
            name = os.path.join(a.out, "images", f"{i:04d}.jpg")
            if os.path.exists(name):
                got += 1
                continue
            b = get(u, binary=True)
            if b and len(b) > 15000 and b[:3] in (b"\xff\xd8\xff", b"\x89PN"):
                hsh = hashlib.md5(b).hexdigest()
                if hsh in seen_hash:
                    continue
                seen_hash.add(hsh)
                open(name, "wb").write(b); got += 1; print("image", got, u[:80], flush=True)
        with zipfile.ZipFile(os.path.join(a.out, "calib_images.zip"), "w", zipfile.ZIP_DEFLATED) as z:
            for f in sorted(os.listdir(a.out + "/images")):
                z.write(os.path.join(a.out, "images", f), f)
    if a.cmd in ("all", "upload") and a.token:
        for name in ("calib.tsv", "calib_blocks.txt", "calib_images.zip"):
            p = os.path.join(a.out, name)
            if os.path.exists(p) and os.path.getsize(p) > 11_000_000:
                print(name, "is too big to upload (", os.path.getsize(p) // 1_000_000, "MB ). Copy it from", a.out, "instead.")
            elif os.path.exists(p):
                req = urllib.request.Request(f"{a.site}/api/v1/fito/upload?name={name}", data=open(p, "rb").read(), method="POST",
                                             headers={"X-Fito-Token": a.token, "User-Agent": UA})
                try:
                    print("uploaded", name, urllib.request.urlopen(req, timeout=120).read().decode()[:80])
                except Exception as e:
                    print("upload failed", name, e)
    print("Done. Files are in", a.out, "- send calib.tsv and the images folder (or calib_images.zip) to Claude.")


if __name__ == "__main__":
    main()
