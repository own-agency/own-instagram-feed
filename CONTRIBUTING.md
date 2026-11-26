# Werken aan OWN Instagram Feed

## Branches

- **`main`** is altijd releasebaar: alleen code die werkt en getest is. GitHub
  dwingt af dat er niets op `main` komt zonder pull request en groene CI. Geen
  directe pushes, geen force-push.
- **Werk gebeurt op een korte branch** vanaf `main`, één onderwerp per branch:
  - `feat/…` iets nieuws (wordt een minor-versie)
  - `fix/…` een bugfix (patch-versie)
  - `chore/…` onderhoud, documentatie, tooling (geen eigen release nodig)
- **Pull request naar `main`**, CI moet groen zijn. Mergen met *squash* (één
  nette commit per onderwerp) of *rebase*; merge-commits staan uit. De branch
  verdwijnt na het mergen vanzelf.
- **Tags `v*`** zijn beschermd: niet te verwijderen of te verplaatsen. Sites
  updaten vanaf die tags, dus een uitgebrachte versie verandert nooit meer.

## Lokaal

```bash
npm install && composer install
npm run start        # bouwt de admin-apps en het blok bij elke wijziging
npm run lint:js
php tests/CoreTest.php
```

## Een release

1. Zet de wijzigingen onder **Unreleased** in `CHANGELOG.md` (doe dat al in
   elke pull request).
2. Branch `chore/release-x.y.z`: hernoem *Unreleased* naar `## [x.y.z] - JJJJ-MM-DD`,
   zet er een nieuwe lege *Unreleased* boven en werk de links onderaan bij.
3. Versie ophogen op drie plekken: `Version:` en `VERSION` in
   `own-instagram-feed.php`, `version` in `package.json` (en
   `npm install --package-lock-only` voor `package-lock.json`).
4. Pull request, CI groen, mergen.
5. Op `main`: `git tag -a vX.Y.Z -m "OWN Instagram Feed X.Y.Z"` en
   `git push origin vX.Y.Z`.

De release-workflow controleert dat de tag gelijk is aan de versie in de
plugin én dat `CHANGELOG.md` een sectie voor die versie heeft, bouwt de zip en
zet die sectie als releasetekst op GitHub. Sites zien de update binnen 12 uur,
of meteen via "Check for updates" op het pluginsscherm.

## Wat je niet breekt

- **Opgeslagen namen** (opties, capability, blocknaam, cron-hook) staan in
  `src/Config.php`. Hernoemen kan alleen met een migratie.
- **Datamigraties** in `src/Upgrade.php`: genummerd, een bestaande nooit
  aanpassen, altijd een nieuwe toevoegen en `SITE_VERSION`/`NETWORK_VERSION`
  ophogen.
- **De publieke API** (README, "Publieke API") groeit alleen. Iets weghalen of
  anders laten werken is een major-versie.
- **Het state-formaat met de relay** ligt vast in `tests/CoreTest.php`. Faalt
  die test, dan werkt koppelen via de relay niet meer.
- **Alleen stabiele `@wordpress/components`**, geen `__experimental*`.
- **De relay** (`relay/`) apart uitrollen met `npx wrangler deploy` in die map;
  hij zit niet in de plugin-zip.
