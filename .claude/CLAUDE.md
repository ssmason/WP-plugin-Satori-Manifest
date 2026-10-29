# Satori Manifest — notes for Claude

WordPress plugin: hosted QR price-list pages, client portal, sign-up/approval, Stripe
subscriptions and branded email. Architecture and conventions: `README.md`.
Tests: `tests/README.md`.

## Rules
- Must work on any WordPress stack: no theme dependencies, no runtime Composer packages.
- WordPress Coding Standards (`.phpcs.xml`): tabs, Yoda, `declare(strict_types=1);`,
  typed params/returns, escaping on output, nonces via `Core\Security`,
  `__()` with text domain `satori-manifest`, full docblocks (`@author`, `@since`).
- One folder = one namespace (`includes/billing/` → `SatoriManifest\Billing\`), one
  responsibility per class. Screens read input, call a service, render a template.
- Config is YAML (`config/`), never PHP arrays. Secrets only via `Core\Env` (.env).
- SCSS for plugin styles (compiled to `build/` — never edit `build/` by hand); vanilla JS.
- After adding or moving PHP classes: `composer dump-autoload -o` at the project root.
- Never read `.env`.

## Working rhythm
Don't lint or test after every edit. At the end of a piece of work run the
`finish-session` skill (build, lint, class check, tests) and report the results.
