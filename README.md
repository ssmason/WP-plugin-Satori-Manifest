# Satori Manifest

Hosted, branded price-list pages for QR-code clients — with sign-up, approval,
Stripe subscriptions, branded emails and a client portal built in.

Each client gets **one login, one page and one price list**. The page is rendered by the
plugin itself (not the theme), so it works on any WordPress install. Clients pick one of
five designs, brand it (logo, colours, fonts, header, footer), manage navigation under
Appearance → Menus, and edit their prices in a purpose-built editor. Everything else in
wp-admin is hidden from them, and server-side rules keep every client isolated.

No runtime PHP dependencies — no Composer packages, no Stripe SDK. ACF (free or Pro) is
needed only for the Branding screen; pages still render without it.

---

## Contents

1. [Getting started](#getting-started)
2. [The customer journey](#the-customer-journey)
3. [Security](#security)
4. [Payments (Stripe)](#payments-stripe)
5. [Email](#email)
6. [How it fits together](#how-it-fits-together)
7. [Configuration in YAML](#configuration-in-yaml)
8. [Client isolation](#client-isolation)
9. [Directory structure](#directory-structure)
10. [Coding standards and tests](#coding-standards-and-tests)
11. [Data model](#data-model)
12. [Hooks](#hooks)

---

## Getting started

```bash
composer install      # dev tools: PHPCS + WPCS, PHPUnit + WordPress test library
npm install
npm run build         # production build → build/  (npm run dev to watch)
```

WP-CLI (inside the PHP container: `docker exec qr-code-manager-php-1 vendor/bin/wp … --allow-root`):

```bash
wp satori-manifest demo seed [--reset]        # 5 demo clients, password ManifestDemo!2026
wp satori-manifest demo remove
wp satori-manifest client create "Blue Door Barbers" hello@bluedoor.test --design=studio
wp satori-manifest client list
wp satori-manifest designs
wp satori-manifest email test application-approved you@example.com
wp satori-manifest email preview payment-receipt --file=/tmp/receipt.html
wp satori-manifest billing status
wp satori-manifest billing simulate-payment <application_id>   # offline, no Stripe call
```

Environment settings (`.env` or `wp-config.php` constants — secrets never live in the database):

| Name | Purpose |
|---|---|
| `STRIPE_SECRET_KEY` | Turns billing on. `sk_test_…` while testing, `sk_live_…` in production. |
| `STRIPE_WEBHOOK_SECRET` | Signing secret of the Stripe webhook endpoint. |
| `BREVO_API_KEY` | Optional: send email through the Brevo API instead of `wp_mail()`. |
| `SATORI_MANIFEST_MAIL_FROM` / `_FROM_NAME` | Optional sender address and name. |

Non-secret settings (portal logo and colours, support and staff emails, payment default)
are under **Manifest → Settings**.

---

## The customer journey

```
Visitor ──Register form──▶ Application (pending)
                              │  staff: Manifest → Applications
              ┌───────────────┼──────────────────────────┐
         Approve +        Approve, no              Decline
         take payment     payment                  (optional email)
              │               │
   email: payment link        │
              │               │
   Stripe Checkout ──paid──▶ Client account created ◀──┘
   (hosted by Stripe)          page + price list + menu, branding pre-filled
                               email: welcome + set-password link
                                     │
                               Client logs in → Home, Help, Branding, Design,
                               Price list, My page, Billing
```

* **Register** — the `satori-manifest/registration-form` block: plan cards with a
  Monthly / Yearly toggle (pure CSS), business details, preferred design. Guarded by a
  nonce, honeypot, minimum fill time and a per-IP rate limit. `?plan=managed&billing=year`
  pre-selects.
* **Approve** — `Application_Approver`. With Stripe connected and "Take payment first"
  ticked, the applicant gets a payment link (status *Awaiting payment*); otherwise the
  account is created straight away. Staff can resend the link or create the account
  without payment.
* **Activate** — creating the account is idempotent, so a repeated Stripe webhook never
  makes a second client.
* **Portal** — clients land on Home, with a "New here?" link to the five-step **Help**
  guide. Every client screen also has a short contextual Help tab (WordPress's Help
  button, top right). Help text lives in `config/help.yaml`.
* **Embed on their own website** — the client Home screen shows a copy-and-paste
  snippet (`Clients\Embed_Code`): an iframe of their page with `?sm_embed=1`, which drops
  the page's own header and footer, is kept out of search results, and reports its height
  so the frame grows to fit (`Frontend\Embed`, `src/js/embed.js`). The resize script only
  trusts messages from this site. Without scripts the frame still works at a fixed height.
* **PDF** — "Download PDF" on the client Home screen (and "PDF" on staff Clients) builds a
  print-ready A4 price list on the server (`Pdf\Price_List_Pdf`) with the plugin's own
  dependency-free PDF writer (`Pdf\Pdf_Document`, standard Helvetica fonts): logo, sections,
  right-aligned prices with dotted leaders, contact details, page numbers and the live page
  address. Brand colours too light for paper are swapped for readable ones; white logos are
  placed on a dark tile. Nonce-protected; clients can only download their own.
* **Login** — `wp-login.php` is branded with the portal logo and colours and links to
  the Register page.

---

## Payments (Stripe)

Stripe Checkout and the Stripe customer portal do the card handling, so card details
never touch the site. The plugin talks to the Stripe API directly (`Billing\Stripe_Client`,
WordPress HTTP API, pinned API version).

**Setup (test mode first):**

1. Stripe → Developers → API keys: put the secret key in `.env` as `STRIPE_SECRET_KEY`.
2. Stripe → Developers → Webhooks → add endpoint
   `https://{site}/wp-json/satori-manifest/v1/stripe/webhook` with the events
   `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`,
   `customer.subscription.updated`, `customer.subscription.deleted`.
   Put its signing secret in `.env` as `STRIPE_WEBHOOK_SECRET`.
3. Stripe → Settings → Billing → Customer portal: save the settings once.
4. Manifest → Payments shows a checklist until all of this is done.

Prices come from `config/plans.yaml` (currency, monthly and yearly amounts). Checkout
creates the price on the fly, so nothing needs setting up in Stripe; pre-created Stripe
Price IDs can be added per plan instead.

| Event | What happens |
|---|---|
| `checkout.session.completed` | Account created, subscription linked, payment logged, welcome email, staff notice |
| `invoice.paid` (renewal) | Payment logged, receipt emailed |
| `invoice.payment_failed` | Logged, client asked to update their card, staff notice |
| `customer.subscription.updated` | Plan, status and renewal date kept in sync |
| `customer.subscription.deleted` | Marked cancelled, client and staff emailed |

Webhooks are signature-checked (5-minute tolerance) and de-duplicated by event ID.
A lapsed subscription never takes a page offline automatically — staff decide, with the
*Take offline / Put online* button on **Manifest → Payments** (administrators only).
Clients see their plan, next payment, invoices and a *Manage billing* button (Stripe
customer portal) under **Billing**.

---

## Email

`Mailer::send( $template, $to, $vars )` → `Email_Builder` renders
`config/emails/{template}.yaml` into the branded HTML layout `templates/email/layout.php`
(logo, accent colour, details table, button, plain-text alternative).

Delivery: Brevo API when `BREVO_API_KEY` is set, otherwise `wp_mail()` — which goes
through **WP Mail SMTP** once its mail server is configured. WordPress's own
password-reset email uses the same branded layout (`Core_Emails`).

| Template | Sent to | When |
|---|---|---|
| `application-received` | applicant | registration |
| `application-staff-notice` | staff | registration |
| `application-approved-payment` | applicant | approved, payment link |
| `application-approved` | new client | account created (welcome + set password) |
| `account-created` | new client | staff added the client by hand |
| `application-declined` | applicant | declined (optional) |
| `payment-receipt` | client | renewal paid |
| `payment-failed` | client | payment failed |
| `subscription-cancelled` | client | subscription ended |
| `staff-billing-notice` | staff | new subscription, failed payment, cancellation |
| `password-reset` | user | "Lost your password?" |

Passwords are never emailed. Each application keeps a log of its emails.

---

## Security

WordPress Coding Standards cover escaping, sanitising and nonces (PHPCS enforces them).
On top of that:

* Every form and action checks a nonce **and** a capability; public endpoints are the
  registration form (nonce, honeypot, minimum fill time, per-IP rate limit), payment links
  (32-character secret, compared in constant time) and the Stripe webhook (signature, 5-minute
  window, de-duplicated events).
* Clients are isolated server-side (see Client isolation) and have no `unfiltered_html`, so
  WordPress strips scripts from anything they write.
* `Hardening\Login_Throttle` — 5 failed logins from one IP lock it out for 15 minutes.
* `Hardening\Site_Hardening` — XML-RPC blocked, REST user list private (no username
  harvesting), login errors don't reveal whether a username exists, WordPress version hidden.
  Each measure can be switched off with the `satori_manifest_hardening` filter.
* Secrets only in the environment (`Core\Env`), never in the database or code.

---

## How it fits together

```
Client user (role: satori_manifest_client)
 ├─ 1 page        rendered by templates/client-page/page.php (not the theme)
 │                brand/header/footer fields as page meta (ACF, YAML-defined); design slug
 ├─ 1 manifest    sm_manifest post; sections & items as JSON
 ├─ 1 nav menu    Appearance → Menus, shown in the page header
 └─ subscription  Stripe snapshot in user meta (_sm_subscription)
```

`Client_Repository` is the only class that knows how these are linked.
**Provisioning** — giving a user the client role creates the page, manifest and menu
(`Provisioner`, idempotent). New clients are always created through `Client_Factory`.

**Designs** — one folder each: `designs/{slug}/design.yaml` (metadata, palette, fonts),
`content.html` (starter blocks), `style.scss` (→ `build/designs/{slug}.css`). Shipped:
Atelier, Studio, Noir, Bloom, Mono. Switching design keeps or replaces the content
(`Design_Applier`). Styling layers: `client-page.css` → design CSS → inline brand
variables (`--sm-*`). The same CSS is rescoped into the block editor, so editing is WYSIWYG.

**Price-list block** — `satori-manifest/price-list`; shows the page's own manifest by
default; layouts single-column, two-column, card-style, minimal, leaders; "Sections to
hide" (`excludedSectionIds`, permanent `s_xxxxxxxx` section IDs) lets one page mix layouts.

**Portal brand** — `Branding\Portal_Brand`: logo and colours of the service itself
(login, client dashboard, emails) from Manifest → Settings, falling back to the site logo
and site icon.

---

## Configuration in YAML

Parsed by the plugin's own dependency-free reader (`Core\Yaml`).

| File | Purpose |
|---|---|
| `config/acf/*.yaml` | Branding screen field groups (brand, header, footer) |
| `config/plans.yaml` | Plans, currency, monthly/yearly prices, optional Stripe Price IDs |
| `config/emails/*.yaml` | Email subject, heading, body, details, button, footer |
| `config/help.yaml` | Getting-started steps, contextual help tabs, FAQ, links |
| `config/fonts.yaml` | Font library for the font selects |
| `config/demo/*.yaml` | Demo clients |
| `designs/*/design.yaml` | Design metadata |

**Theme overrides** — any file can be overridden at the same path under
`{theme}/satori-manifest/` (child theme, then parent, then plugin). The plugin never
requires the theme.

---

## Client isolation

UI (`Admin\Portal\*`) and enforcement (`Clients\Access_Control`) are separate.

| Rule | Where |
|---|---|
| Allow-list of admin screens; anything else redirects to Home | `Admin\Portal\Screen_Guard` |
| Menu, admin bar, notices and footer trimmed and branded | `Admin\Portal\Client_Chrome` |
| No Query Loop / Navigation / HTML blocks, no code editor, plugin patterns only, no SEO panels | `Admin\Portal\Editor_Lockdown` |
| Cannot create posts, pages, manifests, patterns, templates — any path | `Access_Control::block_new_posts` |
| Admin, AJAX and REST queries return only their own content | `Access_Control::restrict_queries` (+ media/link search) |
| Own menu only; no create/delete/locations | `Access_Control` nav-menu guards |
| Theme-level REST routes blocked; Customizer and theme editing denied | `Access_Control::restrict_rest`, `map_meta_cap` |
| Applications, payments and settings: administrators only | `manage_options` checks + admin-only post types |

---

## Directory structure

One folder per area, one namespace per folder, one responsibility per class.

```
satori-manifest.php          bootstrap, constants, autoloader (namespace → folder)
includes/
  class-plugin.php           Plugin — wires every area's hooks
  core/          Core\         Yaml, Config, Env, Settings, Security, Sanitizer, Migrations, Multisite
  manifest/      Manifest\     Post_Types, Manifest_Repository, Meta_Box, Block, Patterns
  clients/       Clients\      Client_Role, Client_Repository, Provisioner, Access_Control,
                               Client_Factory, Client_Summary, Set_Password_Link, Embed_Code,
                               Demo_Seeder
  designs/       Designs\      Design_Registry, Design_Applier
  branding/      Branding\     Brand, Fonts, Icons, Page_Title_Sync, Portal_Brand
  registration/  Registration\ Plans, Applications, Application_Approver, Registration_Form
  billing/       Billing\      Stripe_Settings, Stripe_Client, Checkout, Webhooks,
                               Subscriptions, Payments, Billing_Portal
  mail/          Mail\         Mailer, Email_Builder, Brevo_Transport, Core_Emails
  fields/        Fields\       Field_Loader (YAML → ACF)
  frontend/      Frontend\     Client_Page, Embed, Editor
  login/         Login\        Login_Screen
  pdf/           Pdf\          Pdf_Document, Font_Metrics, Print_Colour, Pdf_Image,
                               Price_List_Pdf, Pdf_Download
  hardening/     Hardening\    Login_Throttle, Site_Hardening
  help/          Help\         Help_Content, Help_Tabs
  admin/         Admin\        Admin_Menu, Assets
    screens/     Admin\Screens\ one class per screen (Client_Home, Branding, Design, Clients,
                               Applications, Payments, Billing, Settings, Help)
    portal/      Admin\Portal\  Screen_Guard, Client_Chrome, Editor_Lockdown
  cli/           Cli\          Demo, Client, Designs, Email and Billing commands
templates/
  admin/                     one view per admin screen
  client-page/               page.php, header.php, footer.php
  registration/form.php
  email/layout.php
blocks/{name}/               block.json + render.php per block
src/blocks/{name}/           block editor JS          ─┐
src/js/, src/scss/           admin, login, front end   ├─▶ build/ (never edit by hand)
designs/{slug}/              design.yaml, content.html, style.scss
config/                      YAML (see above)
tests/                       PHPUnit integration tests — see tests/README.md
```

Screens only read input, call a service and render a template; business rules live in
the services (`Client_Factory`, `Application_Approver`, `Design_Applier`, …).

---

## Coding standards and tests

```bash
vendor/bin/phpcs                                  # WordPress Coding Standards (.phpcs.xml)
npx eslint "src/blocks/**/*.js" "src/js/**/*.js" webpack.config.js
npx stylelint "src/scss/**/*.scss" "designs/**/*.scss"
npm test                                          # PHPUnit, inside the PHP container
npm run check:classes                             # every class reference resolves
```

All must be clean. See [`tests/README.md`](tests/README.md) for how the test suite runs
and what it covers.

---

## Data model

`_satori_manifest_sections` (JSON on the manifest):

```json
[
  {
    "id": "s_d4d1d846",
    "title": "Colour",
    "description": "Skin test required 48 hours before any colour service.",
    "items": [
      { "label": "Balayage", "description": "Hand-painted colour", "price": "145.00",
        "price_prefix": "from", "badge": "Signature" }
    ]
  }
]
```

An item with an empty price renders as a sub-heading. All input passes through
`Sanitizer::sanitize_sections()`.

| Where | What |
|---|---|
| `sm_application` (private post) | applications: fields, status, approved username/design, checkout token, email log |
| `sm_payment` (private post) | payment log (invoice, amount, status, link) |
| user meta `_sm_subscription`, `_sm_stripe_customer` | subscription snapshot |
| option `satori_manifest_settings` | Manifest → Settings |

---

## Hooks

| Hook | Type | Use |
|---|---|---|
| `satori_manifest_billing_enabled` | filter | force billing on/off |
| `satori_manifest_checkout_params` | filter | adjust the Stripe Checkout session (e.g. tax) |
| `satori_manifest_pre_stripe_request` | filter | short-circuit Stripe calls (tests, simulation) |
| `satori_manifest_stripe_event` | action | after a verified Stripe event is handled |
| `satori_manifest_application_status` | action | after an application changes status |
| `satori_manifest_pre_send_email` | filter | take over delivery (queue, other API) |
| `satori_manifest_mail_transport` | filter | choose `brevo` or `wp_mail` |
| `satori_manifest_staff_recipients` | filter | who gets staff notices |
| `satori_manifest_email_sent` | action | after every email attempt |
| `satori_manifest_hardening` | filter | switch a hardening measure off (`xmlrpc`, `rest_users`, `login_errors`, `version`) |
| `satori_manifest_client_ip` | filter | visitor IP for login throttling (behind a proxy/CDN) |

---

## License

GPL-2.0-or-later.
