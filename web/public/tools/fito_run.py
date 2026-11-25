#!/usr/bin/env python3
"""
fito_run.py - one command that collects Fitocracy research data all day and keeps the archive page up to date.
Run it on your Raspberry Pi (Python 3.8+). Safe to stop and start again: finished work is remembered.

  sudo apt install -y tesseract-ocr python3-pil                      # once
  curl -fsSL https://myfitlevel.com/tools/fito_run.py -o fito_run.py
  nohup python3 fito_run.py --token YOURTOKEN > fito_run.log 2>&1 &
  tail -f fito_run.log                                                 # Ctrl+C leaves the view, the job keeps running

Steps (each one polite and slow on purpose):
  1. official pages from the Wayback Machine (skipped when already collected; use --again to redo)
  2. web search: Reddit, DuckDuckGo and archive pages for pasted Fitocracy logs and screenshots
  3. OCR of every screenshot (points, levels)
  4. numbers only from archived workout pages
Every 10 minutes whatever is new is uploaded, and the archive page shows live status.
Stop it with:  pkill -f fito_run.py
"""
import argparse, json, os, subprocess, sys, threading, time, urllib.request

UA = "MyFitLevel-research/2.1 (hobby project; polite, rate-limited)"
HERE = os.path.dirname(os.path.abspath(__file__))


def post_status(a, phase, msg="", done=0, total=0):
    print(f"[{time.strftime('%H:%M:%S')}] {phase} {msg}", flush=True)
    body = json.dumps({"phase": phase, "done": done, "total": total, "msg": msg[:120], "t": int(time.time())}).encode()
    try:
        urllib.request.urlopen(urllib.request.Request(f"{a.site}/api/v1/fito/upload?name=status.json", data=body, method="POST",
                               headers={"X-Fito-Token": a.token, "User-Agent": UA}), timeout=60).read()
    except Exception as e:
        print("  status not sent:", e, flush=True)


def fetch(a, name):
    try:
        data = urllib.request.urlopen(urllib.request.Request(f"{a.site}/tools/{name}", headers={"User-Agent": UA}), timeout=60).read()
        open(os.path.join(HERE, name), "wb").write(data)
        return True
    except Exception as e:
        print("  could not download", name, e, flush=True)
        return os.path.exists(os.path.join(HERE, name))


def run(a, script, *args):
    cmd = [sys.executable, os.path.join(HERE, script), *args, "--out", a.out, "--site", a.site]
    cmd += ["--token", a.token]
    print("  $", " ".join(c if c != a.token else "***" for c in cmd), flush=True)
    if a.dry:
        return 0
    return subprocess.call(cmd)


def uploader(a, stop):
    """Every 10 minutes send whatever is new, and keep the 'collecting now' light on."""
    while not stop.wait(600):
        if a.dry:
            continue
        subprocess.call([sys.executable, os.path.join(HERE, "fito_data.py"), "upload", "--token", a.token, "--out", a.out, "--site", a.site],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        subprocess.call([sys.executable, os.path.join(HERE, "fito_more.py"), "upload", "--token", a.token, "--out", a.out, "--site", a.site],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--token", required=True, help="the token from Admin > Fitocracy data (not the word YOURTOKEN)")
    ap.add_argument("--out", default="~/fito")
    ap.add_argument("--site", default="https://myfitlevel.com")
    ap.add_argument("--again", action="store_true", help="redo the official-pages step even if it was done")
    ap.add_argument("--dry", action="store_true", help="show the plan without doing anything")
    a = ap.parse_args()
    a.out = os.path.expanduser(a.out)
    os.makedirs(a.out, exist_ok=True)
    if a.token.upper() in ("YOURTOKEN", "REALTOKEN", "TOKEN"):
        sys.exit("That is a placeholder. Copy your real token from Admin > Fitocracy data.")
    for s in ("fito.py", "fito_data.py", "fito_more.py"):
        if not fetch(a, s):
            sys.exit(f"Missing {s}. Check the internet connection and try again.")
    stop = threading.Event()
    threading.Thread(target=uploader, args=(a, stop), daemon=True).start()
    steps = [
        ("Step 1 of 4: official Fitocracy pages", "fito.py", ["all"], not os.path.exists(os.path.join(a.out, "digest.txt")) or a.again),
        ("Step 2 of 4: searching Reddit and the web", "fito_data.py", ["all"], True),
        ("Step 3 of 4: reading screenshots (OCR)", "fito_more.py", ["ocr"], True),
        ("Step 4 of 4: archived workout pages (numbers only)", "fito_more.py", ["sets"], True),
    ]
    for label, script, args, do in steps:
        if not do:
            print(f"{label}: already done, skipping (use --again to redo)", flush=True)
            continue
        post_status(a, label, "starting")
        code = run(a, script, *args)
        if code != 0:
            post_status(a, label, f"stopped with code {code}, continuing with the next step")
    stop.set()
    if not a.dry:
        subprocess.call([sys.executable, os.path.join(HERE, "fito_data.py"), "upload", "--token", a.token, "--out", a.out, "--site", a.site])
        subprocess.call([sys.executable, os.path.join(HERE, "fito_more.py"), "upload", "--token", a.token, "--out", a.out, "--site", a.site])
    post_status(a, "Finished", "all steps done and uploaded", 1, 1)


if __name__ == "__main__":
    main()
