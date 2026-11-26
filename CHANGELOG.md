# Changelog

All notable changes to OWN Instagram Feed. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/): patch for fixes, minor for anything
new, major only when the public API (see README, "Publieke API") changes.

Every change goes under **Unreleased** in the same pull request that makes it.
A release turns that section into a version (see CONTRIBUTING.md).

## [Unreleased]

### Added

- Relay: privacy policy for the Meta app at `/privacy`, with deletion instructions at `/privacy#verwijderen`. Replaces the pages on framework.myownagency.nl, which no longer exists.

### Changed

- The Meta app is now called "OWN Social Feed" (was "My OWN Social Feed"); relay notifications and README follow.

## [1.2.1] - 2026-11-26

### Changed

- **Relay on its own domain**: `https://connect.myownagency.nl/`. The old
  `workers.dev` address keeps working until every installation and the Meta
  app have moved.

### Fixed

- **Notices in the admin cards** (such as the relay allowlist check) no longer
  sit right against the text above them.

## [1.2.0] - 2026-11-26

### Added

- **Relay allowlist check** on the Meta-app card and in Site Health: the
  plugin asks the relay (signed with the app secret) whether this
  installation is on `ALLOWED_HOSTS`. A missing entry shows while setting up,
  with the exact fix, instead of when a school can't connect. Checked again
  after saving the Meta-app settings, otherwise cached for five minutes.
  Needs the relay from this version; an older relay shows "kon niet
  gecontroleerd worden".

## [1.1.0] - 2026-11-26

### Security

- **Relay only forwards to allowed installations.** It now checks the return
  address against `ALLOWED_HOSTS` and the plugin's callback path, https only.
  Before, anyone holding the app secret (stored in every installation) could
  have the relay send a school's Instagram login to their own server.
- **No free text from the URL on the admin pages.** After an Instagram login
  the page gets a fixed reason code instead of a message, so a crafted link
  can't show its own text as an official error. Meta's reason is shown with
  the site's status instead.
- **Revoking a connect link stops logins already underway** with that link,
  not only new ones.
- **Updates install only the built release zip**, never GitHub's source
  zipball (which lacks `build/` and `vendor/`).
- GitHub Actions pinned to commit SHAs, CI with read-only rights, and the
  release built without write rights; only publishing the zip may write.

### Changed

- **On a network every school's login returns through the main site**, also
  with a relay. The relay's allowlist then needs one domain per installation,
  however many schools or mapped domains it has. **Update all installations
  before adding a network's main domain-only allowlist to the relay.**
- Changelog in Keep a Changelog format; `CONTRIBUTING.md` (branches,
  releases), pull request template, `.editorconfig`; `main` and release tags
  protected on GitHub.

### Fixed

- **"Weigeren" on Instagram is recognised as a denial.** WordPress can drop
  the `error` parameter from the request, so a refused login was reported as
  a failed exchange; it now says that no permission was given.

## [1.0.2] - 2026-11-26

### Changed

- **Admin pages use the full width** instead of stopping at 960px, like
  WordPress' own screens. Form fields and text keep a readable width; on wide
  screens the Meta-app card puts the checklist next to the fields.

### Fixed

- **"Kopiëren" button next to a connect link** no longer gets squeezed in the
  sites table (Network Admin → Instagram): its text stays inside the button,
  the link wraps beside it.

## [1.0.1] - 2026-11-26

### Added

- **"Instellingen" link** in the plugin's row on the plugins screen: straight
  to Network Admin → Instagram (network-activated) or Settings → Instagram.

## [1.0.0] - 2026-11-26

First release, taking over the Instagram feed that was built into the
own-framework theme (1.3.0–1.5.0).

### Added

- **Block "Instagram-feed"** (`own-instagram/feed`): post cards (account, type
  icon, 4:5 photo, two-line caption) or a square photo grid; 3, 4, 6 or 8
  posts; columns follow the block's own width. Templates can be overridden by
  a theme.
- **Pattern "Instagram-feed met titel"**: heading, a button to the account and
  the feed, built from ordinary blocks. "Lege melding tonen op de website" as
  a block setting.
- **Shortcode `[own_instagram_feed]`** and template function
  **`own_instagram_feed()`** for older themes, the classic editor and page
  builders: same output as the block.
- **Settings → Instagram**: each site connects its own account, refreshes,
  disconnects or makes a single-use connect link. Capability
  `own_instagram_manage` for editors and administrators.
- **Network Admin → Instagram** (network-activated) or the Meta-app tab (per
  site / single site): Meta app ID, secret and relay, plus an overview of
  every site.
- Hourly sync per site, weekly token refresh, images stored locally, last good
  feed kept on errors, one alert e-mail when a connection breaks, automatic
  removal after 30 days without a working connection.
- Relay (Cloudflare Worker) source in `relay/`: one redirect URI for every
  installation, plus Meta's deauthorize and data deletion callbacks.
- Site Health test, WP-CLI commands, updates through GitHub releases.
- Automatic takeover of a connection made with own-framework 1.3.0–1.5.0 (no
  reconnecting needed), and conversion of that theme's `acf/instagram-feed`
  blocks to this plugin's block.

[Unreleased]: https://github.com/own-agency/own-instagram-feed/compare/v1.2.1...HEAD
[1.2.1]: https://github.com/own-agency/own-instagram-feed/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/own-agency/own-instagram-feed/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/own-agency/own-instagram-feed/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/own-agency/own-instagram-feed/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/own-agency/own-instagram-feed/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/own-agency/own-instagram-feed/releases/tag/v1.0.0
