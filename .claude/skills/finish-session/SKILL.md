---
name: finish-session
description: End-of-session quality gate for the Satori Manifest plugin — build assets, run PHPCS/ESLint/Stylelint, check class references and run the PHPUnit suite, then report. Use when a piece of plugin work is finished, before handing back to the user or committing.
---

# Finish session — Satori Manifest

Run from the plugin folder (`web/app/plugins/satori-manifest`). Run every step even if
an earlier one fails, then report all results together.

1. **Build** — `npm run build`. Must end with "compiled successfully".
2. **PHP standards** — `vendor/bin/phpcs`. Must report nothing. Fix with
   `vendor/bin/phpcbf` first, then by hand; never silence a sniff without a reason in a
   `phpcs:ignore … --` comment.
3. **JavaScript** — `npx eslint "src/blocks/**/*.js" "src/js/**/*.js" webpack.config.js`.
4. **SCSS** — `npx stylelint "src/scss/**/*.scss" "designs/**/*.scss"`.
5. **Autoload** — if PHP classes were added or moved: `composer dump-autoload -o` at the
   project root, then `npm run check:classes` (must say "All class references resolve.").
6. **Tests** — `npm test` (PHPUnit in the PHP container). All must pass. New behaviour
   should come with a test in `tests/phpunit/` (see `tests/README.md`).
7. **Smoke** — `curl -s -o /dev/null -w '%{http_code}'` for `http://localhost:5080/`,
   `/register/`, one client page and `/wp/wp-login.php`: all 200.

## Report

A short table: step, result (pass / fail with the first error), anything fixed. Do not
claim a step passed unless its output was seen in this session.
