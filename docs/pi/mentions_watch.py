#!/usr/bin/env python3
"""Watcher (runs every 10 minutes from cron, but only searches at night or when you press Run now): finds posts about Fitocracy / Fitocracy alternatives on Reddit and Hacker News,
sends them to the Mentions page in MyFitLevel admin and (optionally) one Telegram digest.
It never posts anything anywhere. Python 3 standard library only.

Config file ~/.mfl_watch.env (one KEY=VALUE per line):
  MFL_KEY=<key from /admin/mentions>
  MFL_URL=https://myfitlevel.com/mentions/ingest      (optional)
  TG_TOKEN=<telegram bot token>                       (optional)
  TG_CHAT=<telegram chat id>                          (optional)
"""
import json, os, re, sys, time, urllib.parse, urllib.request
import xml.etree.ElementTree as ET
from datetime import datetime, timezone

HOME = os.path.expanduser('~')
CFG = {}
try:
    for ln in open(os.path.join(HOME, '.mfl_watch.env'), encoding='utf-8'):
        if '=' in ln and not ln.strip().startswith('#'):
            k, v = ln.strip().split('=', 1)
            CFG[k.strip()] = v.strip().strip('"\'')
except OSError:
    sys.exit('Missing ~/.mfl_watch.env')
KEY = CFG.get('MFL_KEY', '')
URL = CFG.get('MFL_URL', 'https://myfitlevel.com/mentions/ingest')
SEEN = os.path.join(HOME, '.mfl_watch_seen.json')
UA = 'Mozilla/5.0 (X11; Linux aarch64) mfl-mention-watch/1.0 (personal, 1 run per day)'
STATE = os.path.join(HOME, '.mfl_watch_state.json')


def get(url, accept='application/json'):
    req = urllib.request.Request(url, headers={'User-Agent': UA, 'Accept': accept})
    with urllib.request.urlopen(req, timeout=25) as r:
        return r.read().decode('utf-8', 'replace')


def score(title, text, age_days):
    t = (title + ' ' + text).lower()
    if 'myfitlevel' in t:
        return 0
    if 'fitocracy' in t:
        s = 20
    elif (re.search(r'gamif|\brpg\b|level(s|ing)? up|\bxp\b|points', t) and re.search(r'workout|fitness|gym|lifting', t)
          and re.search(r'tracker|app\b|website|site\b|community', t) and re.search(r'looking for|recommend|alternative|anyone know|suggest|\?', t)):
        s = 25
    else:
        return 0
    if re.search(r'alternativ|replac|substitut|similar|like fitocracy|instead of|looking for|recommend|anyone know|suggest', t):
        s += 30
    if re.search(r'shut|shutting|dead|gone|closed|closing|died|miss(ed)?\b|no longer|sunset', t):
        s += 20
    if '?' in title:
        s += 10
    if age_days <= 3:
        s += 10
    return min(100, s)


def reddit(q):
    base = 'https://www.reddit.com/search'
    qs = urllib.parse.urlencode({'q': q, 'sort': 'new', 't': 'week', 'limit': 25})
    out = []
    try:
        d = json.loads(get(f'{base}.json?{qs}'))
        for c in d['data']['children']:
            p = c['data']
            out.append(dict(source='Reddit', place='r/' + p.get('subreddit', ''), author=p.get('author', ''), title=p.get('title', ''),
                            snippet=(p.get('selftext') or '')[:500], url='https://www.reddit.com' + p.get('permalink', ''),
                            ts=p.get('created_utc', time.time())))
        return out
    except Exception as e:
        print('reddit json failed:', e, file=sys.stderr)
    try:  # fallback: RSS
        ns = {'a': 'http://www.w3.org/2005/Atom'}
        root = ET.fromstring(get(f'{base}.rss?{qs}', 'application/atom+xml'))
        for en in root.findall('a:entry', ns):
            link = en.find('a:link', ns)
            cat = en.find('a:category', ns)
            body = re.sub(r'<[^>]+>', ' ', (en.findtext('a:content', '', ns) or ''))
            upd = en.findtext('a:updated', '', ns)
            try:
                ts = datetime.fromisoformat(upd.replace('Z', '+00:00')).timestamp()
            except ValueError:
                ts = time.time()
            out.append(dict(source='Reddit', place='r/' + (cat.get('term') if cat is not None else ''), author=(en.findtext('a:author/a:name', '', ns) or '').replace('/u/', ''),
                            title=en.findtext('a:title', '', ns) or '', snippet=body[:500], url=link.get('href') if link is not None else '', ts=ts))
    except Exception as e:
        print('reddit rss failed:', e, file=sys.stderr)
    return out


def hn(q):
    out = []
    try:
        since = int(time.time()) - 7 * 86400
        qs = urllib.parse.urlencode({'query': q, 'tags': '(story,comment)', 'numericFilters': f'created_at_i>{since}', 'hitsPerPage': 20})
        for h in json.loads(get('https://hn.algolia.com/api/v1/search_by_date?' + qs))['hits']:
            txt = re.sub(r'<[^>]+>', ' ', h.get('comment_text') or h.get('story_text') or '')
            out.append(dict(source='Hacker News', place='', author=h.get('author', ''), title=h.get('title') or h.get('story_title') or '',
                            snippet=txt[:500], url='https://news.ycombinator.com/item?id=' + str(h.get('objectID')), ts=h.get('created_at_i', time.time())))
    except Exception as e:
        print('hn failed:', e, file=sys.stderr)
    return out


def api(method, data=None):
    req = urllib.request.Request(URL, data=json.dumps(data).encode() if data is not None else None, method=method,
                                 headers={'Authorization': 'Bearer ' + KEY, 'Content-Type': 'application/json', 'User-Agent': UA})
    return json.loads(urllib.request.urlopen(req, timeout=30).read().decode())


def main():
    if not KEY:
        sys.exit('MFL_KEY missing')
    try:
        state = json.load(open(STATE))
    except Exception:
        state = {}
    force = '--now' in sys.argv
    cfg = {}
    try:
        cfg = api('GET')
    except Exception as e:
        print('config failed:', e, file=sys.stderr)
        if not force:
            return
    night = datetime.now().hour == 3 and time.time() - state.get('last', 0) > 20 * 3600
    first = 'last' not in state
    if not (force or first or night or cfg.get('run_now')):
        return
    queries = cfg.get('queries') or ['fitocracy alternative', 'fitocracy replacement', 'fitocracy shut down', 'like fitocracy', 'fitocracy']
    try:
        seen = set(json.load(open(SEEN)))
    except Exception:
        seen = set()
    found, errors = {}, []
    for q in queries[:12]:
        r1, r2 = reddit(q), hn(q)
        if not r1:
            errors.append('reddit: nothing for "' + q + '"')
        for it in r1 + r2:
            if it['url'] and it['url'] not in found:
                found[it['url']] = it
        time.sleep(4)
    items = []
    for it in found.values():
        age = (time.time() - float(it['ts'])) / 86400
        sc = score(it['title'], it['snippet'], age)
        if sc <= 0:
            continue
        it['score'] = sc
        it['posted'] = datetime.fromtimestamp(float(it['ts']), timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
        items.append(it)
    items.sort(key=lambda x: -x['score'])
    print(f'{len(items)} relevant items')
    try:
        print(api('POST', {'items': items[:60], 'finished': True, 'found': len(items), 'errors': errors[:3]}))
    except Exception as e:
        sys.exit(f'upload failed: {e}')
    fresh = [i for i in items if i['url'] not in seen and i['score'] >= 60][:5]
    if fresh and CFG.get('TG_TOKEN') and CFG.get('TG_CHAT'):
        msg = 'MyFitLevel: %d new post(s) where people may want an alternative\n\n' % len(fresh) + '\n\n'.join(
            f"{i['score']}% {i['place'] or i['source']}\n{i['title'][:100]}\n{i['url']}" for i in fresh)
        try:
            data = urllib.parse.urlencode({'chat_id': CFG['TG_CHAT'], 'text': msg, 'disable_web_page_preview': 'true'}).encode()
            urllib.request.urlopen(f"https://api.telegram.org/bot{CFG['TG_TOKEN']}/sendMessage", data=data, timeout=20)
        except Exception as e:
            print('telegram failed:', e, file=sys.stderr)
    seen.update(found.keys())
    json.dump(sorted(seen)[-3000:], open(SEEN, 'w'))
    state['last'] = time.time()
    json.dump(state, open(STATE, 'w'))


if __name__ == '__main__':
    main()
