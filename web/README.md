# MyFitLevel: free gamified workout tracker

PHP 8 + MySQL, no framework, no external services. Log workouts, earn XP, level up, badges, quests,
feed with props and comments, follows, groups, leaderboard, REST API, installable as a phone app (PWA).

## Install (5 minutes)

1. In cPanel, create an empty MySQL database + user (note the three values).
2. Upload this whole `fitness/` folder.
   - **As a subfolder** (e.g. `niemil.com/fitness`): put the folder in `public_html/`. The root `.htaccess`
     serves only `public/` and blocks `includes/` and `config/`.
   - **On its own domain**: upload the folder anywhere and point the domain's document root at `fitness/public`.
3. Open `https://your-site/fitness/install/` (or `https://your-domain/install/`), fill in the form, press Install.
4. Click "Delete install folder". Done. The first account you create is the admin.

The config (DB password etc.) is written to `config/config.php`, outside `public/`, and is blocked from the web.

## Updating

Upload the changed files over the old ones. Database changes are applied automatically on the next page
load (see `includes/schema.php`, `migrations()`), and new built-in exercises are added by slug without
touching your edits. Bump `APP_VERSION` / `SCHEMA_VERSION` in `includes/bootstrap.php` when you ship a release.

## How points work

- Weights: `coef × kg × reps^0.6 × 0.2` (heavier pays more, extra reps pay less and less)
- Bodyweight moves: same formula using `bw_pct × your bodyweight + added weight`
- Cardio: `coef × minutes + dist_coef × km`; timed holds: `coef × minutes`
- Personal record (best estimated 1RM for an exercise): +25 XP, only after you have a previous result
- Level N needs `120 × (N-1)²` total XP. Quests give bonus XP.

Every coefficient can be edited in Admin → Exercises. The constants are at the top of `includes/core.php`.

## API (for snakediet.org or anything else)

Create a key under Settings → API access. Send it as `Authorization: Bearer fit_...`.

| Endpoint | What |
|---|---|
| `GET /api/v1/me` | basic profile, level, XP |
| `GET /api/v1/exercises` | exercise list (stable `slug`s) |
| `GET /api/v1/workouts?after_id=0&since=2026-01-01&limit=50` | workouts with all sets. Poll with `after_id` = last `next_after_id` |
| `POST /api/v1/workouts` | log a workout (JSON below) |

```json
{"title":"Push day","performed_at":"2026-11-20T18:00:00",
 "exercises":[{"slug":"barbell-bench-press","sets":[{"weight_kg":80,"reps":8}]},
              {"slug":"running","sets":[{"duration_sec":1800,"distance_m":5000}]}]}
```

## Folder layout

```
fitness/
  .htaccess        root rewrite (only needed in subfolder mode)
  config/          config.php + installed.lock (created by installer, web-blocked)
  includes/        all PHP code (web-blocked): pages/, views/, schema.php, seed.php, xp.php …
  public/          the only web-facing folder: index.php, assets/, sw.js, install/
```

Security: prepared statements everywhere, CSRF tokens on all forms, `password_hash`, login/registration/API
rate limiting, HttpOnly + SameSite cookies, CSP header, escaped output, account deletion + data export.


## v1.1 changes
- Renamed to MyFitLevel (change the name any time in Admin > Site, or in the installer).
- Logger: every change is autosaved (server + this device), so closing the page or losing signal never loses a workout. Sets are pre-filled from your last session, +/- buttons for weight and reps, warm-up sets (saved, no XP), tick each set when done.
- Light / dark mode (follows the phone, with a toggle), purple theme, mascot, animations.
- 339 built-in exercises (gym, bodyweight, cardio, outdoors, sports).
- SEO: titles/descriptions, social share image, sitemap.xml, robots.txt, structured data, public pages /points, /exercises, /fitocracy-alternative, /privacy.
- Database changes are applied automatically on first load after uploading (no SQL needed).
- Installer detects the URL path automatically (also when installed through the folder's root .htaccess).
