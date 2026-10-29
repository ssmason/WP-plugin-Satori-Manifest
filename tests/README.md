# Satori Manifest — tests

Integration tests on the official WordPress test suite: real WordPress, real database,
every test wrapped in a transaction that is rolled back afterwards.

## Running

From the project root (the PHP container must be running):

```bash
docker exec -w /var/www/html/web/app/plugins/satori-manifest qr-code-manager-php-1 vendor/bin/phpunit
# or, from the plugin folder:
npm test
```

Run one file or test:

```bash
… vendor/bin/phpunit tests/phpunit/BillingTest.php
… vendor/bin/phpunit --filter test_signature_verification
```

## How it works

| File | Role |
|---|---|
| `bootstrap.php` | Loads the WordPress test library (`wp-phpunit/wp-phpunit`), then the plugin |
| `wp-tests-config.php` | Database settings, read from the project's `.env` — nothing secret in this file |
| `support/class-test-case.php` | `Test_Case`: base class with helpers (`make_client()`, `make_application()`, `sent_subjects()`) |
| `phpunit/*Test.php` | The tests |
| `tools/check-class-references.php` | Not a test: checks every class reference resolves (`npm run check:classes`) |

The suite uses the site's own database but **separate tables prefixed `wptests_`**, which
it creates and empties on every run. Real site data (`wp_` tables) is never touched.

Stripe is never called: tests answer every request through the
`satori_manifest_pre_stripe_request` filter. Email goes to WordPress's mock mailer, so
tests can assert what was sent.

## What is covered

| Test | Covers |
|---|---|
| `SanitizerTest` | price-list cleaning, permanent section IDs |
| `YamlTest` | the YAML reader; every shipped config file parses |
| `PlansTest` | plan catalogue, monthly/yearly prices, savings, currency formatting |
| `EmailBuilderTest` | every email template renders; placeholders escaped; empty detail rows dropped; HTML + Reply-To |
| `SettingsTest` | settings sanitising, staff recipients, readable text colours |
| `ClientsTest` | provisioning, unique usernames, isolation between clients, no content creation |
| `ApplicationApproverTest` | approve with/without payment, idempotent activation, decline, taken usernames |
| `EmbedTest` | website embed snippet: embed URL, title, origin-checked resize script |
| `PdfTest` | text metrics and wrapping, print colours, PDF structure, multi-page price list, signed download links |
| `HardeningTest` | login lockout, generic login errors, private REST user list, XML-RPC off, price-list save keeps quotes and needs a nonce |
| `BillingTest` | Checkout parameters, webhook signatures, unsigned requests rejected, first payment, renewals (logged once), failed payments, cancellation |

## Writing a test

Extend `SatoriManifest\Tests\Test_Case`, name the file `SomethingTest.php` in
`tests/phpunit/`, and prefix methods with `test_`. Create data with the WordPress
factories (`self::factory()`) or the helpers above — never rely on site content.
