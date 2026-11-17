=== Satori Manifest ===
Contributors: ssmason
Tags: price list, menu, qr code, restaurant, salon
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 3.15.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hosted, branded price-list pages for businesses that put a QR code on their counter, tables or window.

== Description ==

Satori Manifest turns one WordPress site into a hosted price-list service. Each client gets a login, one branded page, a price list (sections and items) and a navigation menu, all created automatically when the client role is assigned. Clients manage everything from a slimmed-down wp-admin; staff approve sign-ups, take payments and look after accounts.

**For clients**

* A price list editor: headings that group sections, sections with an optional icon and photo, items with description, badge, "from" prefix and price; leave a price empty for a sub-heading.
* Nine designs (Atelier, Studio, Bloom, Mono, and the full-page Noir, Harbour, Morning, Serene and Edge) and branding: logo, colours, fonts, background image, header and footer.
* A QR code (opens the page with `?src=qr`), a print-ready PDF of the price list and a snippet to embed the list on their own website.
* Visit and QR scan counts, with an optional monthly summary email.
* Support tickets with staff, and an optional AI assistant (ChatGPT, Claude) that edits only their own price list and page, with undo.

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

== External services ==

The plugin only contacts these services when they are configured or used:

* **Stripe** (api.stripe.com) - when `STRIPE_SECRET_KEY` is set: creates Checkout sessions and customer portal links and reads subscription details. Sent: plan, price, customer email and Stripe ids. [Terms](https://stripe.com/legal/ssa), [Privacy](https://stripe.com/privacy).
* **Brevo** (api.brevo.com) - when `BREVO_API_KEY` is set: delivers the plugin's emails. Sent: recipient, subject and message. [Terms](https://www.brevo.com/legal/termsofuse/), [Privacy](https://www.brevo.com/legal/privacypolicy/).
* **Cloudflare Turnstile** (challenges.cloudflare.com) - when Turnstile keys are set: spam check on the registration and contact forms. Sent: the visitor's challenge token and IP address. [Terms](https://www.cloudflare.com/website-terms/), [Privacy](https://www.cloudflare.com/privacypolicy/).
* **Google Fonts** (fonts.googleapis.com) - loads the fonts chosen in a client's branding and on the login screen. Sent: the visitor's IP address and browser details. [Terms](https://developers.google.com/terms), [Privacy](https://policies.google.com/privacy).

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

= What does uninstalling remove? =

Plugin settings, the AI assistant's change history, the scheduled task, the client role and the price lists (manifest posts). Client users, pages, menus, media, applications, payments and support tickets are kept: they are customer and business records.

= Where are payments handled? =

On Stripe's own pages (Checkout and the customer portal). Card details never reach your site.

== Changelog ==

= 3.15.1 =
* Billing fix: Stripe sends several events at once; saving one no longer overwrites what another just stored (the renewal date stayed on the trial end after a yearly trial converted).
* Yearly plan changes settle at once both ways: a downgrade becomes account credit that the next charge uses first, so changing back costs nothing.
* Payment list labels: "First payment: Plus", "Plan change: Plus", "Renewal: Plus" instead of Stripe's first invoice line.
* Tested end to end in Stripe test mode: Plus monthly with setup and an extra code, Starter yearly, trial conversion, and upgrades/downgrades monthly and yearly.

= 3.15.0 =
* Plan changes: clients upgrade (Starter → Plus) or downgrade on the Billing screen, and Starter clients upgrade from the AI assistant screen. A right-hand card shows what they gain or lose and what they pay, with a confirm step. Same billing period and renewal date; Stripe prorates: nothing during the trial, monthly upgrades join the next payment, yearly upgrades pay the difference now, downgrades become a credit. The AI assistant switches on or off at once; the client gets a "Your plan is now…" email and staff are told.
* Billing: the portal hint no longer promises switching monthly/yearly.

= 3.14.3 =
* Register form: the "Set it up for me" box is light green (an offer, not a warning).

= 3.14.2 =
* Register form: "Set it up for me" is a highlighted box with the price in its title (accent colour; solid when ticked).

= 3.14.1 =
* Register form: "Set it up for me" sits directly under the plans.

= 3.14.0 =
* Plans: Starter €12 and Plus €15 (AI assistant); Managed is no longer sold (an existing Managed client keeps working and keeps AI).
* "Set it up for me" is €39 one-off on any plan, paid at checkout; the free trial covers the plan only. The setup payment is recorded as such and never counts as the first subscription payment, so "print and post the codes" still waits for the first payment after the trial.

= 3.13.1 =
* QR builder: makes clear the large and small signs are for printing on paper; the 3D-printed codes are ordered separately under the preview.

= 3.13.0 =
* Plans: Starter €12 (€120/yr, 1 printed code), Plus €15 (€150/yr, 3 codes, AI assistant) and Managed €39 (€390/yr, 5 codes, setup and up to four changes a month). Yearly stays two months free. The Starter slug is still self-managed so existing subscriptions carry on.
* Sign-up: plans include their printed codes (standard size); extra codes on the form are charged on the first payment. New "Set it up for me" option (€49, free with yearly, included with Managed), added to the first payment after the trial; staff are told in the new-subscription email.

= 3.12.5 =
* Printed QR codes: four sizes in line with the Etsy shop (XS €3.50, S €4.50, M €5.50, L €6.50). The order form moved from Home to the QR builder, under the preview, and is more compact.

= 3.12.4 =
* Client dashboard: the logo above the menu is the client's own logo (Branding) when they have one, otherwise the portal logo. Wide logos fit the space.

= 3.12.3 =
* Image rows: choosing a photo in the media library now adds it (the row was removed when the library closed). Section images and image rows show in the page editor preview (My page) as on the page.

= 3.12.2 =
* Support: staff answer with "Send reply" (status-only changes say "Status updated"); Support tickets is its own admin menu below Manifest; a client's Home shows a green notice for each ticket with a reply they have not opened yet, cleared when they open it.

= 3.12.1 =
* Image rows: "Add image" in a price list section places a photo among the items, as wide as the column; move it like any item. Image rows are skipped in the PDF and listed to the AI assistant as image_id.

= 3.12.0 =
* Support tickets: clients raise tickets (Support screen, and the "Need a hand?" panel on Home) and follow the replies; staff answer under Manifest → Support tickets. Statuses Opened / In progress / Closed in colour. New tickets and client replies email the support address (config/support.yaml); staff replies email the client.
* QR builder: border colour, colour for the code's corner squares, and an optional line under TAP or SCAN (e.g. company name) with its own colour and font; the sign grows taller to fit. Live preview is now drawn by the server, exactly as it prints.
* Section image: each price list section can have a photo under its title, the full width of its column (16:9, capped in height). The AI assistant can set or remove it (update_section image_id, client's own images only). Not in the PDF.
* Design screen on phones: two designs per row with smaller previews and text.
* Plugin standards: readme lists the external services (Stripe, Brevo, Turnstile, Google Fonts), what uninstall removes, nine designs, Tested up to 6.9. Uninstall now also removes settings, the AI change history, the daily task and internal options.

= 3.11.0 =
* AI assistant: page blocks - get_page_layout, add_block, update_block, remove_block and list_images let clients add their own content (text, headings, lists, their images, buttons, separators, spacers, quotes, or any allowed editor block as markup) inside the page's groups. Price lists and heading banners stay with the price list. Undo now restores page content reliably (snapshots kept in post meta).
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
