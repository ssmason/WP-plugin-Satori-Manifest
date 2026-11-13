# Satori Manifest - notes for Claude

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
- SCSS for plugin styles (compiled to `build/` - never edit `build/` by hand); vanilla JS.
- After adding or moving PHP classes: `composer dump-autoload -o` at the project root.
- Never read `.env`.
- Autoloader loads `class-{name}.php`, then `interface-{name}.php` (one folder per namespace).
- Bump the version in three places together: plugin header, `SATORI_MANIFEST_VERSION`,
  `readme.txt` (Stable tag + changelog entry).

## Areas that need care
- **AI assistant (`includes/mcp/`)** - every write goes through `Mcp\Page_Store` and
  `Change_Log::record()` (so undo works); tools never touch other content. `server/` stays
  generic (no price-list knowledge). New tools: one class in `tools/`, register in
  `Toolbox::TOOLS`, describe it for the model, add a test.
- **Price list entries** - sections and headings share one list (`Manifest\Headings`).
  Code that walks sections must handle headings: skip them for items/counts
  (`Headings::sections_only()`), respect switched-off groups (`Headings::visible()`).
- **PDF** - drawn on A4 and enlarged for A3; keep layouts in A4 points.
- **Client page** - must work in all nine designs and on phones (390px); check the block
  editor opens pages without "invalid block" warnings after changing block markup.

## Checking in a browser
Playwright (from this folder, so it resolves `node_modules`) with `channel: 'chrome'`.
For a logged-in screen, mint cookies with
`wp eval 'echo wp_generate_auth_cookie(<user>, time()+1800, "logged_in");'` (and `"auth"`)
inside the PHP container - no passwords needed. Remove any temporary script afterwards.

## Working rhythm
Don't lint or test after every edit. At the end of a piece of work run the
`finish-session` skill (build, lint, class check, tests) and report the results.
