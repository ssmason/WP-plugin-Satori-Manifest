=== Satori Manifest ===
Contributors: ssmason
Tags: price list, menu, qr code, restaurant, salon
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 3.11.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hosted, branded price-list pages for businesses that put a QR code on their counter, tables or window.

== Description ==

Satori Manifest turns one WordPress site into a hosted price-list service. Each client gets a login, one branded page, a price list (sections and items) and a navigation menu, all created automatically when the client role is assigned. Clients manage everything from a slimmed-down wp-admin; staff approve sign-ups, take payments and look after accounts.

**For clients**

* A price list editor: sections with an optional icon, items with description, badge, "from" prefix and price; leave a price empty for a sub-heading.
* Five designs (Atelier, Studio, Noir, Bloom, Mono) and branding: logo, colours, fonts, background image, header and footer.
* A QR code (opens the page with `?src=qr`), a print-ready PDF of the price list and a snippet to embed the list on their own website.
* Visit and QR scan counts, with an optional monthly summary email.

**For staff**

* A public registration block with plans (monthly or yearly), spam protection and an approval queue.
* Stripe Checkout and the Stripe customer portal for subscriptions, with signed webhooks. No Stripe SDK.
* Branded HTML emails with YAML templates, sent through Brevo or `wp_mail()`.
* Client isolation enforced on the server: clients only see and change their own page, price list, menu and media.

**Built to be self-contained**

* No runtime Composer packages and no theme dependencies: client pages render through the plugin's own template.
* Configuration is YAML (fields, fonts, designs, plans, emails, layouts, help); a theme can override any file under `{theme}/satori-manifest/`.
* Its own QR encoder and PDF writer.
* Security extras: login throttling, XML-RPC off, private REST user lists and generic login errors.

== Installation ==

1. Upload the `satori-manifest` folder to `/wp-content/plugins/` and activate it.
2. Optional: install Advanced Custom Fields (free or Pro) for the branding screens.
3. Add the Registration block to a page, then approve applications under Manifest → Applications.
4. Optional: add `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` to the environment to take payments, and `BREVO_API_KEY` to send email through Brevo.

== Frequently Asked Questions ==

= Does it need a particular theme? =

No. Client pages use the plugin's own template and styles; the theme only styles your marketing pages.

= Can a client see another client's prices? =

No. Ownership is checked on the server for admin screens, REST requests, media, menus and the price list block.

= Where are payments handled? =

On Stripe's own pages (Checkout and the customer portal). Card details never reach your site.

== Changelog ==

= 3.11.0 =
* Headings on the page: each price list heading gets its own group with a Price list heading block (the banner) and, for new headings, its own menu group below it - added, moved and removed automatically when the price list is saved, in price list order. Switched-off headings and their menus disappear from the page. Menus never draw headings.
* QR builder: clients design a printable QR sign - large (top text, subtitle, own colour; one per A4 page) or small (short text, last word in red; four per A4 page) - with a live preview, and download it as a vector PDF. The sign shows on the Home dashboard with its PDF; linked from Branding too.

= 3.10.0 =
* Menu headings in the price list (e.g. "Starters & Snacks", "Main Menu"): each groups the sections below it, has an optional note, its own banner colours and size, and a "Show on page" switch that hides the whole menu from the page and PDF.
* Header colours: optional header background and text colour under Branding → Header (override the design; also settable by the AI assistant).
* Jump links: tick "In jump links" on a heading to add a link to it in a bar under the page header (separate from the navigation; one scrollable row on phones; only headings that are on and shown on the page).
* PDF of the whole price list or one heading's group (e.g. just the Main Menu), in A4 or A3, chosen on the client Home screen.
* Clients' admin bar: "Dashboard" and "Price list" links, also on phones; "View page" only inside wp-admin.
* Phones: the price list editor no longer scrolls sideways (wrapping toolbar, roomier section titles).
* AI assistant: invalid connection links are limited per address (20 per 15 minutes).
* Drag and drop for sections, headings and items in the price list editor (arrows still work).
* AI assistant: add_heading and update_heading tools; set-up can start headings from a menu; price changes can target everything under a heading.

= 3.9.0 =
* AI assistant (MCP): clients connect Claude, ChatGPT or another AI app with a private link and set up or update their own price list, page details and design in plain language - from a photo of their menu, one line at a time or as a previewed price rise. Every change is logged and can be undone.
* The assistant can only reach the client's own page (no billing, account or other WordPress data); its key works on its endpoint only and is stored hashed.
* Included per plan (config/ai-assistant.yaml) or switched on/off per client under Manifest → AI assistant.

= 3.8.1 =
* Price list PDF in A3 as well as A4: the same layout enlarged for posters and boards ("PDF (A3)" on the client Home screen, "A3" on the staff Clients screen).

= 3.8.0 =
* Sign-up goes straight to Stripe Checkout with a free trial (card up front, first charge when the trial ends); customers are logged in and asked to choose a password on return.
* Printed QR codes: some included, extras added to the first payment, more can be ordered from the dashboard; Stripe collects the postal address.
* Five complete page designs with photos (Noir, Harbour, Morning, Serene, Edge), light and dark mode for every design, and full-page previews on the Design screen.
* Demo pages show an examples bar linking to every other example; demo clients for every design.
* Optional Cloudflare Turnstile spam check, unfinished sign-ups removed after 7 days, page-cache support (clears client pages on change, keeps forms uncached).
* With a Brevo key, all WordPress email (login-screen password resets, two-factor codes, admin notices) is sent through Brevo too.
* Contact form block (public Contact page) and a "Need a hand?" panel on the client dashboard; messages go to the staff address with Reply-To set to the sender.
* Payments screen shows trials separately from monthly revenue.

= 3.7.0 =
* Code structure: Stripe webhooks, registration, client access and the price list block each split into single-purpose classes.
* Registration form uses the shared nonce helper; visit counter validates its input.

= 3.6.0 =
* Six layout patterns for richer pages, with titles that scale on phones.

= 3.5.0 =
* Section icons, a page background image; the public PDF link was removed (the PDF stays in the client dashboard).

= 3.4.0 =
* European price format (4,50 €), section anchors for menu links and the Harbour Kitchen demo.

== Upgrade Notice ==

= 3.8.0 =
Free trials, printed QR code orders and new designs. Add printed_codes and trial_days to a theme override of plans.yaml if you use one.

= 3.7.0 =
Internal tidy-up; no changes to settings or content.
