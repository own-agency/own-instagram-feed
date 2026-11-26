## Wat en waarom

<!-- Wat verandert er voor een site of een redacteur, en waarom. -->

## Checklist

- [ ] `CHANGELOG.md`: de wijziging staat onder **Unreleased**
- [ ] Opgeslagen data veranderd? Dan een nieuwe migratie in `src/Upgrade.php`
- [ ] Publieke API (functies, hooks, templates, shortcode, REST) alleen uitgebreid, niets weggehaald
- [ ] Getest: `php tests/CoreTest.php`, `npm run lint:js`, en op een site
- [ ] Relay gewijzigd? Dan apart uitrollen (`cd relay && npx wrangler deploy`)
