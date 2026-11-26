# OWN Instagram Feed

Toont de nieuwste posts van een Instagram-account als blok ("Instagram-feed"). Elke website koppelt zijn eigen account via één Meta-app van OWN ("My OWN Social Feed"). De website haalt elk uur zelf nieuwe posts op en bewaart de afbeeldingen lokaal: bezoekers laden niets van Meta, en de feed blijft werken als Instagram's afbeeldingslinks verlopen.

Werkt op een losse site en op multisite, per site of netwerkbreed geactiveerd. Vereist WordPress 6.6+ en PHP 8.0+.

## Installeren en bijwerken

Installeer de zip uit de [laatste release](https://github.com/own-agency/own-instagram-feed/releases). Daarna komen updates gewoon binnen via Dashboard → Updates.

Een nieuwe versie uitbrengen: versie ophogen in de plugin-header **en** `VERSION` in `own-instagram-feed.php`, `CHANGELOG.md` bijwerken, mergen naar `main`, tag `vX.Y.Z` pushen. De GitHub Action bouwt de zip en hangt hem aan de release.

Ontwikkelen: `npm install && npm run build` (of `npm start`), `composer install`. Tests: `php tests/CoreTest.php`.

## Instellen

### Meta-app (eenmalig, al gedaan voor "My OWN Social Feed")

- App van het type **Business** met het product **Instagram** → "API setup with Instagram login".
- **Live**, met advanced access voor `instagram_business_basic`.
- Business login settings:
  - OAuth redirect URI: de relay, `https://own-instagram-relay.aged-wildflower-ae4f.workers.dev/`
  - Deauthorize callback URL: `…/?deauthorize`
  - Data deletion request URL: `…/?data-deletion`
- Meta vraagt één keer per jaar om de **data access renewal**. Rond die af, anders worden alle feeds tegelijk beperkt.

### Per installatie

- **Netwerkbreed geactiveerd:** Netwerkbeheer → Instagram.
- **Per site of losse site:** Instellingen → Instagram → tab Meta-app (alleen voor ontwikkelaars).

Vul in: **Instagram app ID** (`747057097604862`, niet het app-ID bovenaan het Meta-dashboard), **Instagram app secret** en de **Relay-URL**. Of zet ze in `wp-config.php`:

```php
define( 'OWN_INSTAGRAM_APP_ID', '747057097604862' );
define( 'OWN_INSTAGRAM_APP_SECRET', '…' );
define( 'OWN_INSTAGRAM_RELAY_URL', 'https://own-instagram-relay.aged-wildflower-ae4f.workers.dev/' );
define( 'OWN_INSTAGRAM_DEVELOPERS', array( 'ties', 'dev@ownagency.nl' ) ); // Wie de Meta-app ziet; nodig zodra een klant ook (super)admin is.
define( 'OWN_INSTAGRAM_ALERT_EMAIL', 'dev@ownagency.nl' );                  // Wie de mail krijgt als een koppeling breekt.
```

### Per website

Instellingen → Instagram → **Instagram koppelen**, of **Koppellink maken** voor wie het account beheert. Alleen zakelijke en creator-accounts. Toegang: de capability `own_instagram_manage`, bij installatie gegeven aan redacteuren en beheerders en daarna per rol aan te passen (User Role Editor).

## De feed plaatsen

- **Blok "Instagram-feed"** (blokeditor): weergave kaarten of alleen foto's, 3/4/6/8 posts, afgeronde hoeken, "Lege melding tonen op de website".
- **Patroon "Instagram-feed met titel"** (categorie Instagram): titel, knop naar het account en de feed, alles los aan te passen.
- **Shortcode** (klassieke editor, page builders, tekstwidgets):
  `[own_instagram_feed layout="cards" count="4" rounded="yes" empty="no" class=""]`
- **In een thema-template** (oudere, niet-blok-thema's):
  `<?php if ( function_exists( 'own_instagram_feed' ) ) own_instagram_feed( array( 'count' => 4, 'empty' => true ) ); ?>`

Alle vier geven dezelfde HTML en gebruiken dezelfde templates en CSS. Alle instellingen zijn optioneel: `layout` cards|grid, `count` 3|4|6|8, `rounded` ja/nee, `empty` (lege melding) ja/nee, `class` extra CSS-klassen.

## Hoe het werkt

- **Synchronisatie:** WP-Cron, elk uur per site. Het token (60 dagen geldig) wordt wekelijks ververst. Mislukt ophalen, dan blijft de vorige feed staan.
- **Servercron:** WP-Cron draait per site en alleen bij bezoek. Met `DISABLE_WP_CRON` moet de servercron élke site afgaan:
  `wp site list --field=url | xargs -I{} wp cron event run --due-now --url={}`
- **Koppeling verbroken** (wachtwoord gewijzigd, app verwijderd): de laatste posts blijven staan, er gaat één mail uit, en na 30 dagen zonder nieuwe koppeling verwijdert de site posts en afbeeldingen zelf. Zo worden verwijderverzoeken via Meta ook echt uitgevoerd.
- **Relay** (`relay/`): Cloudflare Worker als vaste redirect-URI voor alle installaties, plus Meta's deauthorize- en verwijdercallbacks. Slaat niets op. Bijwerken: `cd relay && npx wrangler deploy`.
- **Site Health** (Gereedschap → Sitestatus) en **WP-CLI**: `wp own-instagram status|sync|link|disconnect`.

## Publieke API

Alles hieronder blijft werken tussen versies (alleen uitbreidingen). Alles onder `OWN\InstagramFeed\*` is intern. Gebruik in een thema altijd `function_exists()`.

**Functies**

| Functie | |
|---|---|
| `own_instagram_get_posts( [ 'limit' => 4 ] )` | Posts: `id, permalink, caption, type, timestamp, image_url, width, height, username` |
| `own_instagram_get_account()` | `username, needs_reconnect, synced_at`, of `null` |
| `own_instagram_is_connected()` | bool |
| `own_instagram_feed( [ 'count' => 4 ] )` | Print de feed (voor thema-templates) |
| `own_instagram_render_feed( $attributes, $context )` | Geeft de feed-HTML terug zoals het blok hem rendert |
| `own_instagram_template( 'card.php', $args )` | Een template renderen (thema eerst) |

**Templates** — kopieer naar `<thema>/own-instagram/`: `feed.php`, `card.php`. De `$args` staan bovenaan elk bestand.

**Filters** — `own_instagram/admin_parent` (menu van de instellingenpagina), `own_instagram/show_empty` (lege melding op de site i.p.v. niets), `own_instagram/load_default_styles`, `own_instagram/feed_args`, `own_instagram/post`, `own_instagram/template`, `own_instagram/is_developer`, `own_instagram/alert_email`.
Standaardstijlen uit: `add_theme_support( 'own-instagram-feed-styles', false );`

**Acties** — `own_instagram/connected`, `own_instagram/disconnected`, `own_instagram/synced`, `own_instagram/connection_broken`.

**Shortcode** — `[own_instagram_feed]` met `layout`, `count`, `rounded`, `empty`, `class`.

**REST** — `own-instagram/v1`: velden komen erbij, verdwijnen nooit; iets breken = `v2`.

## Een oude site overzetten

Oudere sites (bijv. werkenbijhetwakz.nl) hebben hun eigen Instagram-code en blijven gewoon werken: de plugin raakt hun code en hun redirect-URI in de Meta-app niet aan. Overzetten, per site wanneer het uitkomt:

1. Plugin installeren en de Meta-app + relay invullen.
2. Instellingen → Instagram → Instagram koppelen.
3. Het blok "Instagram-feed" plaatsen. Geen blokeditor? Dan de shortcode `[own_instagram_feed]` of `own_instagram_feed()` in het template.
4. De oude Instagram-code van die site verwijderen. Laat nooit beide tegelijk draaien.
5. Daarna mag de oude redirect-URI van die site uit de Meta-app.

## Voor wie verder bouwt

- **Datamigraties** in `src/Upgrade.php`: genummerd, nooit een bestaande wijzigen, altijd een nieuwe toevoegen en `SITE_VERSION`/`NETWORK_VERSION` ophogen.
- **Opgeslagen namen** (opties, capability, blocknaam, cron-hook) staan in `src/Config.php`. Hernoemen = een migratie.
- **Het state-formaat** met de relay ligt vast in `tests/CoreTest.php`. Verandert die test, dan breekt de relay.
- **Alleen stabiele `@wordpress/components`**, geen `__experimental*`: dan breekt een WordPress-update de schermen niet.
