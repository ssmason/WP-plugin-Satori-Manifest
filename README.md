# Satori Manifest

Hosted, branded price-list pages for QR-code clients - with sign-up, approval,
Stripe subscriptions, branded emails and a client portal built in.

Each client gets **one login, one page and one price list**. The page is rendered by the
plugin itself (not the theme), so it works on any WordPress install. Clients pick one of
nine designs, brand it (logo, colours, fonts, header, footer), manage navigation under
Appearance → Menus, and edit their prices in a purpose-built editor (sections, items and
menu headings, drag and drop) - or ask an AI app to do it for them. Everything else in
wp-admin is hidden from them, and server-side rules keep every client isolated.

No runtime PHP dependencies - no Composer packages, no Stripe SDK. ACF (free or Pro) is
needed only for the Branding screen; pages still render without it.

---

## Contents

1. [Getting started](#getting-started)
2. [The customer journey](#the-customer-journey)
3. [Security](#security)
4. [Payments (Stripe)](#payments-stripe)
5. [Email](#email)
6. [Price list editor and headings](#price-list-editor-and-headings)
7. [AI assistant (MCP)](#ai-assistant-mcp)
8. [How it fits together](#how-it-fits-together)
9. [Configuration in YAML](#configuration-in-yaml)
10. [Client isolation](#client-isolation)
11. [Directory structure](#directory-structure)
12. [Coding standards and tests](#coding-standards-and-tests)
13. [Data model](#data-model)
14. [Hooks](#hooks)

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
wp satori-manifest stats list [--days=30]
wp satori-manifest stats send-summary [--month=YYYY-MM] [--client=<id>] [--to=<email>]
```

Environment settings (`.env` or `wp-config.php` constants - secrets never live in the database):

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

* **Register** - the `satori-manifest/registration-form` block: plan cards with a
  Monthly / Yearly toggle (pure CSS), business details, preferred design. Guarded by a
  nonce, honeypot, minimum fill time and a per-IP rate limit. `?plan=managed&billing=year`
  pre-selects.
* **Approve** - `Application_Approver`. With Stripe connected and "Take payment first"
  ticked, the applicant gets a payment link (status *Awaiting payment*); otherwise the
  account is created straight away. Staff can resend the link or create the account
  without payment.
* **Activate** - creating the account is idempotent, so a repeated Stripe webhook never
  makes a second client.
* **Portal** - clients land on Home, with a "New here?" link to the five-step **Help**
  guide. Every client screen also has a short contextual Help tab (WordPress's Help
  button, top right). Help text lives in `config/help.yaml`. While logged in, the admin bar
  (also on their own page, and on phones) has **Dashboard** and **Price list** links
  (`Admin\Portal\Client_Chrome`).
* **Embed on their own website** - the client Home screen shows a copy-and-paste
  snippet (`Clients\Embed_Code`): an iframe of their page with `?sm_embed=1`, which drops
  the page's own header and footer, is kept out of search results, and reports its height
  so the frame grows to fit (`Frontend\Embed`, `src/js/embed.js`). The resize script only
  trusts messages from this site. Without scripts the frame still works at a fixed height.
* **PDF** - on the client Home screen clients choose the **whole price list or one heading**
  (e.g. just "Main Menu"), **A4 or A3**, and download; staff get "PDF" / "A3" on Clients. Built
  on the server (`Pdf\Price_List_Pdf`) with the plugin's own dependency-free PDF writer
  (`Pdf\Pdf_Document`, standard Helvetica fonts): logo, headings, sections, right-aligned
  prices with dotted leaders, contact details, page numbers and the live page address. A3 is
  the A4 layout enlarged onto an exact A3 page (`set_output_size()`), so it keeps its
  proportions. Hidden headings (and their sections) are left out; the page's navigation and
  jump links never appear. Brand colours too light for paper are swapped for readable ones;
  white logos are placed on a dark tile. Nonce-protected; clients can only download their own.
* **QR builder** - clients design a printable sign around their code (`Admin\Screens\Qr_Builder_Screen`,
  `Qr\Qr_Sign`, `Qr\Qr_Sign_Pdf`): **large** (top text, subtitle, their colour; one per A4 page,
  about 16 × 19.5 cm) or **small** (short text with the last word in red; four per A4 page, about
  6.5 × 8 cm). Live preview while typing; settings in page meta `_sm_qr_sign`. One shape definition
  feeds the SVG preview and a vector PDF (`Pdf_Document::path()` / `rounded_rect()`), so it prints as
  previewed. The sign shows on the Home card with "Download PDF" (`Qr_Download::url( $page, 'sign' )`)
  and is linked from Branding.
* **QR code** - every client page has one, built by the plugin's own encoder
  (`Qr\Qr_Encoder`, ISO 18004, no library; verified with a real scanner). It opens the page
  with `?src=qr` and uses error correction Q (25%) so worn prints still scan. Clients see it
  on Home and download it as PNG (print) or SVG (designers); staff from Clients ("QR"); it is
  also printed on the PDF.
* **Visits and scans** - `Stats\View_Counter` counts visits with a small background request
  (works with page caching): bots, the client, staff and repeat visits within 30 minutes are
  not counted; QR scans are visits that came from `?src=qr`. Only daily totals are stored, on
  the page (`Stats\Page_Views`, 400 days). Home shows the last 30 days.
* **Monthly summary** - on the 1st, each client gets last month's visits and scans compared
  with the month before (`Stats\Monthly_Summary`, daily WP-Cron check). Staff can switch it off
  in Settings; clients on their Home screen.
* **Page CSS helpers** - in client-page.scss: `sm-flush-top` / `sm-flush-bottom` (full-width
  photo against the header / footer) and `sm-display` (title that scales on phones).
* **Header colours** - optional "Header background" / "Header text" under Branding → Header
  override the design's header (name, tagline, menu, phone menu, jump links); empty = the
  design's own. Also `update_page_details` (`header_background`, `header_text`) for the AI.
* **Support tickets** (`includes/support/`) - clients raise tickets under **Support** (and from the
  "Need a hand?" panel on Home) and see replies there; staff answer under **Manifest → Support
  tickets** (count of opened tickets in the menu). Statuses: Opened (amber), In progress (blue),
  Closed (green); a staff reply moves Opened to In progress, a client reply reopens a closed one.
  New tickets and client replies email the addresses in `config/support.yaml` (`notify`, else the
  staff addresses) as a prompt; staff replies are emailed to the client. Private post types
  `sm_ticket` / `sm_ticket_msg`; removed with the client.
* **Login** - `wp-login.php` is branded with the portal logo and colours and links to
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
A lapsed subscription never takes a page offline automatically - staff decide, with the
*Take offline / Put online* button on **Manifest → Payments** (administrators only).
Clients see their plan, next payment, invoices and a *Manage billing* button (Stripe
customer portal) under **Billing**.

---

## Email

`Mailer::send( $template, $to, $vars )` → `Email_Builder` renders
`config/emails/{template}.yaml` into the branded HTML layout `templates/email/layout.php`
(logo, accent colour, details table, button, plain-text alternative).

Delivery: Brevo API when `BREVO_API_KEY` is set, otherwise `wp_mail()` - which goes
through **WP Mail SMTP** once its mail server is configured. With a Brevo key,
`WP_Mail_Relay` also sends everything else that calls `wp_mail()` (login-screen password
resets, two-factor codes, admin notices) through Brevo. WordPress's own password-reset
email uses the same branded layout (`Core_Emails`). Sender: `SATORI_MANIFEST_MAIL_FROM` /
`SATORI_MANIFEST_MAIL_FROM_NAME`; Reply-To: the support email (the sender, for contact messages).

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
| `account-exists` | registrant | someone registered again with an existing email |
| `monthly-summary` | client | 1st of the month: visits and QR scans |

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
* The registration form answers the same way for new and already-registered emails; the
  owner of an existing email gets an email instead, so the form can't be used to find out
  who is registered.
* `Hardening\Client_Ip` - the real visitor IP behind Cloudflare or another proxy (Manifest →
  Settings → "Site is behind"), used by the login throttle, the sign-up limit and visit counts.
* Two-step login: the **Two Factor** plugin (WordPress.org) is installed; each user switches
  it on under Users → Profile.
* `Hardening\Login_Throttle` - 5 failed logins from one IP lock it out for 15 minutes.
* `Hardening\Site_Hardening` - XML-RPC blocked, REST user list private (no username
  harvesting), login errors don't reveal whether a username exists, WordPress version hidden.
  Each measure can be switched off with the `satori_manifest_hardening` filter.
* Secrets only in the environment (`Core\Env`), never in the database or code.

---

## Price list editor and headings

The **Price list** screen (`Manifest\Meta_Box`, `templates/admin/meta-box-sections.php`,
`src/js/manifest-admin.js`) edits one ordered list of **sections** (with items) and **headings**.

* **Headings** (`Manifest\Headings`) - e.g. "Starters & Snacks", "Main Menu". A heading groups
  every section below it, up to the next heading, so one heading can cover several sections.
  Each has an optional note ("Served until 2 p.m. daily"), a size (S/M/L), optional own banner
  and text colours, and two switches:
  * **Show on page** - off hides the heading *and* its sections from the page and the PDF
    (kept for later, e.g. breakfast after 2 p.m.).
  * **In jump links** - adds a link to the heading in a bar under the page header
    (`templates/client-page/jump-links.php`), separate from the navigation. Only headings that
    are switched on and shown by a price list block on the page get a link
    (`Headings::jump_links()`); links follow renames automatically.
* **Adding** - "Add heading" at the top (adds at the top) and bottom (adds at the end), "Add
  section" likewise.
* **On the page** (`Manifest\Page_Sync`, `Manifest\Block_Placement`) - saving the price list
  (editor or AI) keeps the page in step: every heading gets a **heading group** (a group holding
  the `satori-manifest/price-list-heading` block, i.e. the banner) and, unless the page already
  shows its sections, a **menu group** below it (a price list block with `headingId`, styled like
  its neighbouring menu group), placed in price list order. They move and go with the heading.
  Only these automatic groups (classes `sm-auto-heading` / `sm-auto-menu`) are touched; one the
  client has added content to stays put. Switched-off headings render nothing (empty automatic
  groups are dropped). Price list blocks never draw headings.
* **Ordering** - drag by the handle (≡) for sections, headings and items (items can move
  between sections); the ↑/↓ arrows stay for keyboard and touch screens (phone browsers do not
  support drag and drop).
* **On the page** - the price list block renders a heading as a full-width banner (any layout,
  every design) with an anchor from its title (`#main-menu`). In the block's section picker
  headings appear as "Heading: …", so one page can still mix layouts.
* Price lists without headings work exactly as before.

---

## AI assistant (MCP)

Clients connect an AI app (Claude, ChatGPT, Claude Code…) over the Model Context Protocol and
manage their page by asking: "here's a photo of our menu, set up my page", "Guinness is now 5.50",
"cocktails up 5%, show me first", "undo that".

* **Module** - everything lives in `includes/mcp/`, wired by one call (`Mcp::register()` in
  `Plugin`). `server/` is a generic, stateless MCP server (JSON-RPC 2.0, Streamable HTTP with JSON
  responses, protocol 2024-11-05 to 2025-11-25) that knows nothing about price lists.
* **Endpoint** - `POST /wp-json/satori-manifest/v1/mcp/{key}` (or `/mcp` with
  `Authorization: Bearer {key}`). One link to paste; survives hosts that strip the Authorization
  header. Replies as JSON, or as one server-sent event when the client accepts
  `text/event-stream`; a (stateless) `Mcp-Session-Id` is sent after `initialize`. Rate-limited
  per client (120 requests / 5 minutes), 20 invalid links per address per 15 minutes, 1 MB body
  limit, no GET/DELETE.
* **Keys** - `Tokens`: `smai_{user}_{40 hex}`, one per client, HMAC-hashed with the auth salt,
  compared in constant time, shown once. Not a WordPress password: works on this endpoint only.
  A key in the address is written to web server access logs; apps that can send headers (e.g.
  `claude mcp add --header`) should use `Authorization: Bearer` instead.
* **Scope** - the key decides the client; tools get a `Client_Context` (that client's page and
  price list IDs) and write only through `Page_Store` (price list via `Sanitizer`, details via
  `Brand::set`, design via `Design_Applier`). Requests also run as the client, so WordPress's
  own rules apply. Billing, account, media and other content are unreachable.
* **Tools** - get_page, set_up_price_list, update_items, add_items, remove_items,
  adjust_prices (with preview; a heading id means every section under it),
  add/update/move/remove_section, add_heading, update_heading (title, note, colours, size, show
  on page, in jump links), update_page_details, list_designs, choose_design, list_changes,
  undo_last_change, get_visits. set_up_price_list can start headings from a menu. Plus prompts
  (set up from my menu, change my prices, add specials, check my price list) and server
  instructions telling the model to keep the business's wording and confirm big changes.
* **Page blocks** - `Page_Blocks` + tools `get_page_layout`, `add_block`, `update_block`,
  `remove_block`, `list_images`: the assistant adds, changes and removes the client's own blocks
  inside the page's groups (ids `g2`, `g2.1` from the layout). Common blocks are built from simple
  fields so they match the editor's markup; others can be given as block markup, limited to the
  blocks the client may use (`Editor_Lockdown`). Price list and heading blocks are never touched,
  saving runs as the client (no scripts), images must be the client's own.
* **Undo** - `Change_Log` stores what each change replaced (private `sm_ai_change` posts,
  last 30 per client). Undo steps back one change at a time; also on the client's screen.
* **Who gets it** - `config/ai-assistant.yaml`: `plans` that include it (paid up), plus a
  per-client Follow plan / On / Off under **Manifest → AI assistant** (administrators).
  Switching off disconnects. Filters: `satori_manifest_ai_enabled`, `satori_manifest_ai_allowed`,
  `satori_manifest_ai_tools`.
* **Client screen** - **AI assistant**: create/replace/disconnect the private link, how to add
  it to an AI app, example requests, what it can and can't do, recent changes with undo.
* **Connecting** - the AI app's servers call the endpoint, so it must be public HTTPS (live
  site, or a tunnel for local testing). Verified with ChatGPT and the official MCP Python
  client. Claude's web/desktop custom connectors currently stop after `initialize` for
  servers like this one (Anthropic issue `anthropics/claude-ai-mcp#1241`, open); Claude Code
  works.

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
**Provisioning** - giving a user the client role creates the page, manifest and menu
(`Provisioner`, idempotent). New clients are always created through `Client_Factory`.

**Designs** - one folder each: `designs/{slug}/design.yaml` (metadata, palette, fonts),
`content.html` (starter blocks), `style.scss` (→ `build/designs/{slug}.css`). Shipped:
Atelier, Studio, Noir, Bloom, Mono, Harbour, Morning, Serene, Edge. Switching design keeps or replaces the content
(`Design_Applier`). Styling layers: `client-page.css` → design CSS → inline brand
variables (`--sm-*`). The same CSS is rescoped into the block editor, so editing is WYSIWYG.

**Price-list block** - `satori-manifest/price-list`; shows the page's own manifest by
default; layouts single-column, two-column, card-style, minimal, leaders; "Sections to
hide" (`excludedSectionIds`, permanent `s_xxxxxxxx` section and heading IDs) lets one page mix
layouts. Headings render as full-width banners; switched-off headings and their sections are
skipped (`Headings::visible()`).

**Portal brand** - `Branding\Portal_Brand`: logo and colours of the service itself
(login, client dashboard, emails) from Manifest → Settings, falling back to the site logo
and site icon.

---

## Configuration in YAML

Parsed by the plugin's own dependency-free reader (`Core\Yaml`).

| File | Purpose |
|---|---|
| `config/acf/*.yaml` | Branding screen field groups (brand, header, footer) |
| `config/plans.yaml` | Plans, currency, monthly/yearly prices, optional Stripe Price IDs |
| `config/ai-assistant.yaml` | AI assistant: on/off, plans that include it, rate limit, history kept |
| `config/emails/*.yaml` | Email subject, heading, body, details, button, footer |
| `config/help.yaml` | Getting-started steps, contextual help tabs, FAQ, links |
| `config/fonts.yaml` | Font library for the font selects |
| `config/demo/*.yaml` | Demo clients |
| `designs/*/design.yaml` | Design metadata |

**Theme overrides** - any file can be overridden at the same path under
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
| Cannot create posts, pages, manifests, patterns, templates - any path | `Access_Control::block_new_posts` |
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
  class-plugin.php           Plugin - wires every area's hooks
  core/          Core\         Yaml, Config, Env, Settings, Security, Sanitizer, Migrations, Multisite,
                               Media_Importer, Page_Cache
  manifest/      Manifest\     Post_Types, Manifest_Repository, Meta_Box, Block, Patterns,
                               Price_List_View (block view data), Headings (menu headings,
                               visibility, jump links)
  clients/       Clients\      Client_Role, Client_Repository, Provisioner, Access_Control,
                               Menu_Access, Page_Template_Lock, First_Password,
                               Client_Factory, Client_Summary, Set_Password_Link, Embed_Code,
                               Demo_Seeder
  designs/       Designs\      Design_Registry, Design_Applier
  branding/      Branding\     Brand, Fonts, Icons, Page_Title_Sync, Portal_Brand
  registration/  Registration\ Plans, Applications, Application_Approver, Registration_Form,
                               Registration_Handler, Registration_Notifier, Abandoned_Checkouts
  billing/       Billing\      Stripe_Settings, Stripe_Client, Checkout, Webhooks,
                               Webhook_Signature, Webhook_Events, Billing_Notifier,
                               Subscriptions, Payments, Billing_Portal, Checkout_Return,
                               Code_Catalogue, Printed_Codes (printed QR code orders)
  mail/          Mail\         Mailer, Email_Builder, Brevo_Transport, Core_Emails, WP_Mail_Relay
  contact/       Contact\      Contact_Form (block + dashboard panel), Contact_Handler
  fields/        Fields\       Field_Loader (YAML → ACF)
  frontend/      Frontend\     Client_Page, Embed, Editor, Design_Preview, Example_Bar
  support/       Support\      Support, Tickets, Ticket_Notifier; admin/ Client_Tickets_Screen, Staff_Tickets_Screen
  mcp/           Mcp\          AI assistant module (see "AI assistant (MCP)"): Mcp, Access, Tokens,
                               Endpoint, Toolbox, Page_Store, Price_List, Prices, Change_Log
    server/      Mcp\Server\  generic MCP/JSON-RPC: Server, Tool, Tool_Result, Prompt, Schema_Validator
    tools/       Mcp\Tools\   one class per tool (Client_Tool base)
    admin/       Mcp\Admin\   Connect_Screen (clients), Access_Screen (staff)
  login/         Login\        Login_Screen
  pdf/           Pdf\          Pdf_Document, Font_Metrics, Print_Colour, Pdf_Image,
                               Price_List_Pdf, Pdf_Download
  hardening/     Hardening\    Login_Throttle, Site_Hardening, Client_Ip, Turnstile
  qr/            Qr\           Qr_Encoder, Qr_Renderer, Page_Qr, Qr_Download, Qr_Sign, Qr_Sign_Pdf
  stats/         Stats\        Page_Views, View_Counter, Monthly_Summary
  help/          Help\         Help_Content, Help_Tabs
  admin/         Admin\        Admin_Menu, Assets
    screens/     Admin\Screens\ one class per screen (Client_Home, Branding, Design, Qr_Builder, Clients,
                               Applications, Payments, Billing, Settings, Help)
    portal/      Admin\Portal\  Screen_Guard, Client_Chrome, Editor_Lockdown
  cli/           Cli\          Demo, Client, Designs, Email and Billing commands
templates/
  admin/                     one view per admin screen
  client-page/               page.php, header.php, footer.php, jump-links.php
  registration/form.php
  contact/form.php
  email/layout.php
blocks/{name}/               block.json + render.php per block
src/blocks/{name}/           block editor JS          ─┐
src/js/, src/scss/           admin, login, front end   ├─▶ build/ (never edit by hand)
designs/{slug}/              design.yaml, content.html, style.scss
config/                      YAML (see above)
tests/                       PHPUnit integration tests - see tests/README.md
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

A heading is an entry in the same list (no items):

```json
{ "id": "s_65244486", "type": "heading", "title": "Main Menu",
  "description": "Served 9:30am - 10pm", "visible": true, "in_menu": true,
  "style": { "background": "#4b5f57", "text": "#ffffff", "size": "l" }, "items": [] }
```

An item with an empty price renders as a sub-heading. All input passes through
`Sanitizer::sanitize_sections()` (headings through `Headings::sanitize()`: hex colours only,
size s/m/l).

| Where | What |
|---|---|
| `sm_application` (private post) | applications: fields, status, approved username/design, checkout token, email log |
| `sm_payment` (private post) | payment log (invoice, amount, status, link) |
| `sm_ai_change` (private post) | AI change log: what each change replaced (undo), last 30 per client |
| user meta `_sm_ai_token`, `_sm_ai_access` | AI key (hash, created, last used); staff AI setting (plan/on/off) |
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
| `satori_manifest_client_ip` | filter | visitor IP (after the Settings proxy choice) |
| `satori_manifest_ai_enabled` | filter | switch the AI assistant on/off site-wide |
| `satori_manifest_ai_allowed` | filter | whether one client may use the AI assistant |
| `satori_manifest_ai_tools` | filter | add, remove or replace AI tools |
| `satori_manifest_client_screens` | filter | admin screens clients may open (used by the AI module) |

---

## License

GPL-2.0-or-later.
