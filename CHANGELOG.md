# Changelog

All notable changes to OWN Instagram Feed. Newest first. [Semver](https://semver.org/): patch for fixes, minor for anything new, major only when the public API (see README) changes.

## 1.0.0 — 2026-11-26

First release, taking over the Instagram feed that was built into the own-framework theme (1.3.0).

### Added

- **Block "Instagram-feed"** (`own-instagram/feed`): post cards (account, type icon, 4:5 photo, two-line caption) or a square photo grid; 3, 4, 6 or 8 posts; columns follow the block's own width. Templates can be overridden by a theme.
- **Settings → Instagram**: each site connects its own account, refreshes, disconnects or makes a single-use connect link. Capability `own_instagram_manage` for editors and administrators.
- **Network Admin → Instagram** (network-activated) or the Meta-app tab (per site / single site): Meta app ID, secret and relay, plus an overview of every site.
- Hourly sync per site, weekly token refresh, images stored locally, last good feed kept on errors, one alert e-mail when a connection breaks, automatic removal after 30 days without a working connection.
- **Pattern "Instagram-feed met titel"**: heading, a button to the account and the feed, built from ordinary blocks. "Lege melding tonen op de website" as a block setting.
- **Shortcode `[own_instagram_feed]`** and template function **`own_instagram_feed()`** for older themes, the classic editor and page builders: same output as the block.
- Relay (Cloudflare Worker) source in `relay/`: one redirect URI for every installation, plus Meta's deauthorize and data deletion callbacks.
- Site Health test, WP-CLI commands, updates through GitHub releases.
- Automatic takeover of a connection made with own-framework 1.3.0–1.5.0 (no reconnecting needed), and conversion of that theme's `acf/instagram-feed` blocks to this plugin's block.
