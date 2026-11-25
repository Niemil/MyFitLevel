# Contributing

Thanks for helping. This is a small community project, so keep things simple.

1. Open an issue first for anything bigger than a small fix, so we can agree on the idea.
2. Fork the repo, make your change on a branch and open a pull request. Explain what and why in a few lines.
3. Keep to the existing style: plain PHP 8 with PDO, no frameworks, no new external services, no tracking.
4. Database changes must apply themselves (see `web/includes/schema.php` and the lazy `game_ensure()` pattern). Nobody should have to run SQL by hand.
5. Never commit secrets (`config.php`, keys, passwords). Test with your own local copy.
6. Be kind. No ads, no data selling and no dark patterns, ever.

Nothing from a pull request goes to the live site until the maintainer has reviewed and merged it.
