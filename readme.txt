=== ProducerKit ===
Contributors: jeromewincek
Tags: availability, pre-orders, farmers market, artisan, events
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.1.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Catalog, sales locations, live availability, pickup pre-orders and events for small independent producers — farms, makers and beekeepers.

== Description ==

ProducerKit gives small farms, makers, beekeepers and market gardeners a complete toolkit for running their web presence: a product catalog, sales locations, a live availability board, an open/closed stand banner, and events with RSVPs — all managed from the WordPress admin, with no build step and no external services.

= Products and sources =

* **Products** as a custom post type with price, unit, product type, and season.
* **Sources** — document where ingredients come from: partner farms, grain origins, milling notes. Link sources to products to tell the story behind what you sell.
* **CSV import/export** — bulk-manage products from a spreadsheet, with a downloadable template and optional featured-image sideloading from URLs.

= Availability =

* **Availability board block** — a live, filterable board of what's available right now, grouped by product type, with status badges (abundant, available, limited, sold out, unavailable).
* **Quick entry screen** — batch-update availability for every product from one admin page. Statuses can carry a note ("~3 bunches left") and expire automatically.
* **Availability badge block** — an inline status badge for any single product.

= Payments (at the stand, not online) =

* **Payment options per location** — list every way customers can pay: Venmo, Cash App, PayPal, a custom payment link, plus accepted-payment badges for cash, check, SNAP/EBT, and WIC/Senior FMNP market vouchers.
* **QR codes** — show a scannable code for your payment link right in the location card; the print stylesheet enlarges it for stand signage. Generated locally by a bundled library — no external service.
* **Pre-orders** — visitors reserve products for a pickup date and pay at the stand. Orders are rate-limited and spam-protected, staff manage them from a dedicated admin screen (pending → confirmed → ready → picked up), and email notifications keep both sides informed. No payment processing, no checkout.
* **Harvest list** — active pre-orders aggregated into per-pickup-date totals of each product to have ready. Print it and take it to the field.
* **Smart pickup dates** — pre-order dates are validated against the location's weekly schedule, season dates, and a per-location closed-dates list, so nobody orders for a day the stand is shut.

= Producer profiles =

* **Sixteen trades** — farm, bakery, beekeeping, musician, author, comics, painting, screen printing, taxidermy, and seven crafts. Each re-labels the product fields for the trade you actually practise: a potter's Material is a Clay Body, a beekeeper's is a Floral Source, a printer's is a Substrate.
* **Optional fields switch on per trade** — Material, Finish and Component exist only for profiles that ask for them, so a farm never sees them, and they render on the product card and single product page under your own labels.
* **More than one trade on a site** — a farm that also bakes, or two people sharing an install. Which fields exist and which vocabulary is seeded combine; the wording resolves per person, so each of you reads the same field in your own trade's words.

= Commissions =

* **Made-to-order requests** — a customer describes what they want, you quote a price and a date, and they accept or decline from a link in their email. The confirmation page shows the terms before anything is agreed.
* **A request form block** whose type and material options come from your producer profile.
* **An admin queue** with an enforced status flow, quotes that can be revised or reissued, and five emails.

= Print and search =

* **Fresh Sheet** — a print-ready one-pager of today's availability with prices, hours, payment options, and a payment QR code. Print it each morning and tape it to the stand.
* **Structured data** — products emit schema.org Product/Offer markup (price and live availability) and locations emit LocalBusiness (address, coordinates, opening hours, accepted payments), helping farms surface in local search.
* **Get directions** — a one-tap directions link on the location card, built from the location's coordinates or address.

= Stand status =

* **Stand status banner block** — a live open/closed banner with an optional message ("Back at 2 PM"), polling for changes while the page is open.
* **Admin-bar quick toggle** — open or close the stand from anywhere in the admin.
* **Location info block** — address, hours, and payment options for each sales location, with an optional QR code.

= Events =

* **Farm events** — pizza nights, potlucks, workshops, tours — with event types, schedules, and optional donation links.
* **RSVPs** — visitors can RSVP with a party size; each RSVP gets a cancellation token. Rate-limited, with optional email notifications.
* **Event list and event card blocks** for upcoming and past events.

= Built for the modern block editor =

* Eleven blocks under a dedicated category, server-rendered with live front-end updates via the WordPress Interactivity API.
* REST API endpoints for products, sources, taxonomies, availability, locations, stand status, events, RSVPs, pre-orders and commissions.
* **Abilities API** — on WordPress 6.9+, fourteen operations (list products, get/update availability, toggle stand status, RSVP to an event, create and manage pre-orders, build a harvest list, and more) are registered as Abilities, so AI agents and automation tools can discover and call them with full input/output schemas and permission checks.
* Modular architecture — disable feature modules you don't need with a single filter.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/producerkit`, or install through the WordPress plugins screen.
2. Activate the plugin.
3. Go to **ProducerKit** in the admin sidebar and click **Load Sample Data** to explore the blocks with realistic content (remove it any time with one click).
4. Create your real **Locations** and **Products**, then add the blocks to your pages.

== Frequently Asked Questions ==

= Does this plugin need a build step or external services? =

No. It is plain PHP and vanilla JavaScript — no bundler, no API keys, no third-party calls.

= How do visitors see availability changes? =

The availability board and stand status banner are server-rendered and then kept fresh with the WordPress Interactivity API, polling the plugin's REST endpoints while the page is open.

= Can I turn off features I don't use? =

Yes. Feature modules — producer profiles, stand status, availability board, event manager, notifications, pre-orders, commissions and WooCommerce — can each be disabled via the `pkit_active_modules` filter. Only the core data layer is required.

= What are Abilities? =

The WordPress Abilities API (WordPress 6.9+) lets plugins register operations that AI agents and automation tools can discover and execute. ProducerKit registers its read and write operations as abilities with JSON Schema input/output definitions and capability checks. Write operations require `edit_posts`, except commission management, which requires `edit_others_posts` because those records hold customer contact details and a quote is binding.

= Does the RSVP feature store personal data? =

RSVPs store the name, optional email, party size, and note a visitor submits, in a table on your own site. Nothing is sent to any external service.

= Do pre-orders process payments? =

Not on their own. A pre-order is a reservation: products, quantities, a pickup date, and contact details, with payment happening at pickup — the confirmation shows the location's accepted payment methods, including any payment links.

If WooCommerce is active you can additionally enable the WooCommerce module, which raises a pending order for a request and hands the customer a payment link. That is opt-in; with the module off, or WooCommerce absent, nothing about the plugin touches payment processing.

= Where do the QR codes come from? =

They are generated in the visitor's browser by a bundled open-source library (qrcode-generator, MIT). No external QR service is called.

== Screenshots ==

1. Availability board block on the front end.
2. Stand status banner with live open/closed state.
3. Admin dashboard with module status and content gaps.
4. Availability quick entry screen.
5. Product CSV import/export.

== Changelog ==

= 2.1.0 =
* **Commissions now work.** In 2.0.0 a maker could send a quote and no customer could accept it: the emailed link was a GET to a POST-only endpoint and returned a 404. Quote links now open a confirmation page showing the price and terms, with accept and decline buttons.
* Fixed: pre-orders could be undercharged. A price written "2 for $5" was charged as $2.00, and "$1,200.00" as $1.00. Any price that is not one unambiguous amount now stops the checkout and names the product.
* Fixed: the hidden product raised for a commission was publicly readable, exposing the customer's name and their request to anyone who guessed its URL.
* Fixed: one click on the commissions screen could put a request into a state it could not be recovered from.
* Fixed: an accepted commission never produced a payment link.
* Fixed: reading customer details and issuing quotes required only the Contributor role. It now requires Editor.
* Added: quotes can be revised or reissued after they expire, and the commissions screen paginates past 100 requests.

= 2.0.0 =
* **Breaking: no upgrade path from 1.x.** Every stored identifier was renamed and nothing is migrated. Content saved by 1.x is not read by 2.0.0. Do not update a site with real data — export first, or stay on 1.1.0.
* Producer profiles: sixteen trades, each re-labelling the product fields and seeding that trade's vocabulary. A site can run more than one, and each person chooses which trade's wording they see.
* Commissions: made-to-order requests with a quote sent by email, accept or decline by link, an admin queue, and a request form block.
* Optional WooCommerce module, so requests can settle through a store when one is present and directly when it is not.
* Admin menu consolidated to three items, with the catalogue and events renamed Catalog and Calendar so they do not collide with WooCommerce and The Events Calendar.
* Fixed: an RSVP placed through an Event Card never recorded that the event had filled.
* Fixed: commission rate limiting hashed visitor IP addresses with an unsalted digest.

= 1.1.0 =
* Products without a photo now show a muted illustration based on their product type, so the availability board and product cards no longer have gaps. Filter `pkit_product_placeholder_url` to override or disable.

= 1.0.2 =
* Fixed the availability table not being created at all on MySQL servers using STRICT_TRANS_TABLES (the default since MySQL 5.7), which left the availability board, quick entry, Fresh Sheet and REST endpoints with no data.
* The availability table now repairs its own schema on load, so sites affected by the above do not need to deactivate and reactivate.

= 1.0.1 =
* Fixed relative times ("Updated N ago") on the stand status banner and the admin dashboard, which were off by the site's UTC offset.
* Fixed the daily availability cleanup running at the wrong hour on any site not set to UTC.

= 1.0.0 =
* Initial public release.
* Products, sources, locations, and events as custom post types.
* Availability board, badge, stand status banner, location info, product card, event list, event card, stand hours, stand toggle, and pre-order form blocks.
* Per-location payment methods (links + SNAP/EBT badges) with optional QR codes.
* Pay-at-pickup pre-orders with harvest list, schedule-aware pickup dates, an admin management screen, and email notifications.
* Printable Fresh Sheet and schema.org structured data for products and locations.
* Availability quick entry, CSV product import/export, admin dashboard.
* REST API and Abilities API coverage for all core operations.

== Upgrade Notice ==

= 2.1.0 =
Recommended for anyone on 2.0.0. The commission workflow did not work in that release — quote links returned a 404 — and pre-orders could be charged less than their listed price. Also fixes a case where a customer's name and request were publicly readable.

= 2.0.0 =
Do not update an existing site without exporting first. This release renames every stored identifier — post types, taxonomies, meta keys and database tables — and provides no migration, so content created by 1.x will not be visible. It is safe on a new install.

= 1.1.0 =
Products without a photo now show a type-based placeholder image. Filter `pkit_product_placeholder_url` and return an empty string to keep the previous behaviour.

= 1.0.2 =
Important if your host runs MySQL in strict mode: the availability table was never created. This release fixes the schema and repairs existing sites automatically.

= 1.0.1 =
Fixes relative timestamps and the daily cleanup schedule on sites not set to UTC.

= 1.0.0 =
Initial public release.
