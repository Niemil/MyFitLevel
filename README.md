# MyFitLevel

**Free, open-source, non-profit workout tracker with levels, quests, bosses and a community.** Inspired by Fitocracy.
Live at **https://myfitlevel.com**. No ads, no tracking, no selling data, free forever.

- **Web app** (`web/`): PHP 8 + MySQL, no framework, no external services. Installs on ordinary shared hosting in about five minutes. Works in any browser and as an installable phone app (PWA).
- **Android app** (`android/`): the site in a WebView, plus step sync through Health Connect.
- **REST API**: every member gets API keys. Connect scripts, widgets, iPhone Shortcuts and other apps. Docs live on the site at `/api`.
- **Game layer**: points, levels (1 to 100), quests, badges, weekly goals and streaks, a weekly boss and team raids, a coin shop with gear, seasons and an advent calendar, private messages, groups, leaderboards.

## Why open source?

It is a community project, so the code should be open too. Read it, check what it does with your data, run your own copy, report a bug or send an improvement. You can also point your own AI at the code and suggest changes.

## Run your own copy

See [`web/INSTALL.md`](web/INSTALL.md). Short version: upload `web/`, create an empty MySQL database, open `/install/` in your browser, press Install, delete the install folder. Database changes are applied automatically when you update.

## Contribute

Read [CONTRIBUTING.md](CONTRIBUTING.md). Improvements arrive as pull requests that the maintainer reviews before anything goes live. To report a security problem privately, see [SECURITY.md](SECURITY.md).

## Layout

```
web/       the website (includes/ = code, public/ = web root, config/ = your private config, never committed)
android/   the Android app (builds an APK on every push, see .github/workflows)
```

## Licence

[GNU AGPL-3.0](LICENSE). You are free to use, study, change and share it. If you run a modified version as a public service, you must share your changes under the same licence. The name "MyFitLevel" and the logo are not part of the licence.
