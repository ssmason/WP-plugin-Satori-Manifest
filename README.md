# Satori Manifest

Hosted, branded price-list pages for QR-code clients.

Each client gets **one login, one page and one price list**. The page is rendered by the
plugin itself (not the theme), so it works on any WordPress install. Clients pick one of
five designs, brand it (logo, colours, fonts, header, footer), manage their navigation
under Appearance → Menus, and edit their price list in a purpose-built editor. Everything
else in wp-admin is hidden from them, and server-side rules keep every client isolated
from every other client.

No runtime PHP dependencies — no Composer packages, no PECL extensions. ACF (free or Pro)
is needed only for the Branding screen; pages still render without it.

---

## Getting started

```bash
composer install      # dev tools: PHPCS + WPCS, PHPUnit polyfills
npm install
npm run build         # production build → build/
npm run dev           # watch mode
```

Lint (all must be clean):

```bash
vendor/bin/phpcs --standard=.phpcs.xml .
npx eslint "block/src/**/*.js" "src/js/**/*.js" webpack.config.js
npx stylelint "src/scss/**/*.scss" "designs/**/*.scss"
```

Demo data (5 clients, one per design — 2 with navigation, 3 standalone):

```bash
wp satori-manifest demo seed            # password: ManifestDemo!2026
wp satori-manifest demo seed --reset    # recreate
wp satori-manifest demo remove
```

Other commands:

```bash
wp satori-manifest site install [--reset]   # Home + Register pages, Home as front page
wp satori-manifest email test application-approved you@example.com
wp satori-manifest client create "Blue Door Barbers" hello@bluedoor.test --design=studio
wp satori-manifest client list
wp satori-manifest designs
```

---

## How it fits together

```
Client user (role: satori_manifest_client)
 ├─ 1 page        — template "satori-manifest-client-page", rendered by templates/client-page.php
 │                  brand/header/footer fields stored as page meta (ACF, YAML-defined)
 │                  design slug in _satori_manifest_design
 ├─ 1 manifest    — sm_manifest post; sections & items as JSON in _satori_manifest_sections
 └─ 1 nav menu    — classic menu, edited under Appearance → Menus, shown in the page header
```

Links are stored both ways: user meta (`satori_manifest_page_id`, `…_manifest_id`,
`…_menu_id`) and `_satori_manifest_client_id` on the page, manifest and menu term.
`Client_Repository` is the only class that knows this.

**Provisioning** — giving a user the client role (Users screen, Manifest → Clients, or
WP-CLI) creates the page, manifest and menu automatically (`Provisioner`). It is
idempotent. Deleting the user removes the menu; WordPress's own "delete or reassign
content" choice handles the page and manifest.

**Designs** — a design is a self-contained folder:

```
designs/{slug}/design.yaml    title, description, palette, fonts, default header layout
designs/{slug}/content.html   starter block markup ({{brand_name}}, {{tagline}}, {{year}})
designs/{slug}/style.scss     compiled to build/designs/{slug}.css
```

Shipped designs: **Atelier** (salons), **Studio** (barbers), **Noir** (bars), **Bloom**
(nails & beauty), **Mono** (cafés / standalone). Each design is also registered as a block
pattern. Switching design either keeps the page content (styles only) or loads the
design's starter layout — the old content is kept as a revision.

Styling layers, lowest to highest priority:
1. `build/css/client-page.css` — structure, header, footer, core blocks, default tokens.
2. `build/designs/{slug}.css` — the design's tokens and character.
3. Inline brand variables (`--sm-background`, `--sm-accent`, `--sm-font-heading`, …)
   from the client's Branding settings.

The same CSS is rescoped to `.editor-styles-wrapper` inside the block editor
(`Editor::scoped_css()`), so editing a client page is WYSIWYG.

**Manifest block** — `satori-manifest/price-list`. With no manifest selected it shows the
current client page's own manifest, and on a client page it only ever renders manifests
owned by that client. Layouts: `single-column`, `two-column`, `card-style`, `minimal`,
`leaders`. Colour scheme `brand` follows the page design.

**Public site & registration** — the Home and Register pages use the "Satori site page"
template (plugin-rendered, theme-independent, `build/css/site.css`) and are editable by
administrators only. Their starter content lives in `templates/site-content/`. The
Register page holds the `satori-manifest/registration-form` block; submissions become
private `sm_application` posts (nonce, honeypot, minimum fill time and a per-IP rate
limit guard the form). Under **Manifest → Applications** an administrator approves —
creating the username, a strong random password, the page/manifest/menu, branding
pre-filled from the form — or declines. The client is emailed their username and a
secure set-password link (passwords are never emailed). Pages are labelled in the admin
Pages list ("Client page — login · design", "Site page").

**Email** — `Mailer::send( $template, $to, $vars )` renders `config/emails/{template}.yaml`
into `templates/email/layout.php`. Transport: Brevo API when `BREVO_API_KEY` is set
(constant or env), otherwise `wp_mail()`. Optional `BREVO_TEMPLATE_{NAME}` switches a
message to a Brevo-hosted template. Sender: `SATORI_MANIFEST_MAIL_FROM` /
`SATORI_MANIFEST_MAIL_FROM_NAME`. Every send is logged on its application, and the
`satori_manifest_pre_send_email` filter can short-circuit delivery (e.g. queueing).

---

## Configuration in YAML

All editable configuration is YAML, parsed by the plugin's own dependency-free reader
(`includes/core/class-yaml.php` — block maps/lists, quoted scalars, `|`/`>` blocks,
single-level `[]`/`{}` flow collections, comments).

| File | Purpose |
|---|---|
| `config/acf/brand.yaml` | Brand tab: name, tagline, logo, currency, colours, fonts |
| `config/acf/header.yaml` | Header tab: layout, navigation toggle, sticky, button |
| `config/acf/footer.yaml` | Footer tab: address, hours, phone, email, socials, note |
| `config/fonts.yaml` | Font library offered in the font selects (Google Fonts) |
| `config/demo/*.yaml` | Demo clients for `wp satori-manifest demo seed` |
| `config/plans.yaml` | Pricing plans shown on the registration form (title, price text, features) |
| `config/emails/*.yaml` | Email subjects and bodies (application received / staff notice / approved / declined) |
| `designs/*/design.yaml` | Design metadata |

ACF field YAML is plain ACF settings; `key` is optional (generated as
`field_sm_{file}_{name}`). The top-level `screen:` block controls the tab on the Branding
screen.

**Theme overrides** — a theme can override any config file by placing the same relative
path under `{theme}/satori-manifest/` (child theme first, then parent, then plugin), and
add or replace designs under `{theme}/satori-manifest/designs/{slug}/` (with a compiled
`style.css`). The plugin never requires the theme.

---

## Client isolation

UI (`Admin_Lockdown`) and enforcement (`Access_Control`) are separate.

| Rule | Where |
|---|---|
| Allow-list of admin screens; everything else redirects to Home | `Admin_Lockdown::guard_screen` |
| Cannot create pages, posts, manifests, patterns, templates, navigation posts — any path | `Access_Control::block_new_posts` (`wp_insert_post_empty_content`) |
| Admin, AJAX and REST queries return only their own content (pages, manifests, media, patterns) | `Access_Control::restrict_queries` + media/link-search filters |
| Menus screen limited to their own menu; no create/delete/locations | `Access_Control::guard_nav_menus_screen`, `filter_nav_menus` |
| Theme-level REST routes (menus, navigation, settings, widgets, posts…) blocked; templates/global styles read-only | `Access_Control::restrict_rest` |
| Customizer, theme switching, template/navigation/global-style editing denied | `Access_Control::map_meta_cap` |
| No edit/delete of others' content, no deleting own page/manifest | role capabilities (`Client_Role::capabilities`) |
| Block editor: no Query Loop, Navigation, Latest Posts, HTML, template-part blocks; no code editor; plugin patterns only | `Admin_Lockdown::allowed_blocks`, `editor_settings` |

Clients still get `edit_posts` (needed by parts of the block editor) and
`edit_theme_options` (needed by Appearance → Menus); both are narrowed by the rules above.

---

## Directory structure

```
satori-manifest.php        bootstrap, constants, autoloader (includes/{folder}/class-*.php)
includes/
  class-plugin.php         hook wiring
  core/                    Yaml, Config (theme overrides), Security, Sanitizer, Multisite
  manifest/                Post_Types, Manifest_Repository, Meta_Box, Block, Patterns
  clients/                 Client_Role, Client_Repository, Provisioner, Access_Control
  designs/                 Design_Registry
  site/                    Site_Page, Site_Installer (public home/register pages)
  registration/            Registration_Form, Applications, Applications_Screen
  mail/                    Mailer, Brevo_Transport
  fields/                  Field_Loader (YAML → ACF local field groups)
  frontend/                Client_Page, Brand, Fonts, Icons, Editor
  admin/                   Admin_Menu, Admin_Lockdown, Assets, Client_Home,
                           Design_Screen, Branding_Screen, Clients_Screen
  cli/                     Cli (wp satori-manifest …)
admin/views/               screen templates
templates/                 client-page.php, site-page.php, parts/, email/, site-content/,
                           registration-form.php
assets/site/               static images for the site pages
block/                     block.json, render.php, src/ (editor JS)
designs/                   one folder per design
config/                    YAML config (acf/, fonts.yaml, demo/)
src/js/, src/scss/         sources → build/ (never edit build/)
```

---

## Data model

`_satori_manifest_sections` (JSON):

```json
[
  {
    "title": "Colour",
    "description": "Skin test required 48 hours before any colour service.",
    "sort_order": 0,
    "items": [
      { "label": "Balayage", "description": "Hand-painted colour", "price": "145.00",
        "price_prefix": "from", "badge": "Signature", "sort_order": 0 }
    ]
  }
]
```

An item with an empty price renders as a sub-heading. All input passes through
`Sanitizer::sanitize_sections()`.

---

## Multisite

Single-site today. `Multisite` handles network activation/uninstall housekeeping; a
site-per-client model is a possible future direction.

## License

GPL-2.0-or-later.
