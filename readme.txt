=== Farm Stand Manager ===
Contributors: jeromewincek
Tags: farm stand, farmers market, availability, products, events
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Products, sales locations, real-time availability, stand status, and events for small farms and farm stands.

== Description ==

Farm Stand Manager gives small farms, farm stands, and market gardeners a complete toolkit for running their web presence: a product catalog, sales locations, a live availability board, an open/closed stand banner, and donation-friendly farm events with RSVPs — all managed from the WordPress admin, with no build step and no external services.

= Products and sources =

* **Products** as a custom post type with price, unit, product type, and season.
* **Sources** — document where ingredients come from: partner farms, grain origins, milling notes. Link sources to products to tell the story behind what you sell.
* **CSV import/export** — bulk-manage products from a spreadsheet, with a downloadable template and optional featured-image sideloading from URLs.

= Availability =

* **Availability board block** — a live, filterable board of what's available right now, grouped by product type, with status badges (abundant, available, limited, sold out).
* **Quick entry screen** — batch-update availability for every product from one admin page. Statuses can carry a note ("~3 bunches left") and expire automatically.
* **Availability badge block** — an inline status badge for any single product.

= Payments (at the stand, not online) =

* **Payment options per location** — list every way customers can pay: Venmo, Cash App, PayPal, a custom payment link, plus accepted-payment badges for cash, check, SNAP/EBT, and WIC/Senior FMNP market vouchers.
* **QR codes** — show a scannable code for your payment link right in the location card; the print stylesheet enlarges it for stand signage. Generated locally by a bundled library — no external service.
* **Pre-orders** — visitors reserve products for a pickup date and pay at the stand. Orders are rate-limited and spam-protected, staff manage them from a dedicated admin screen (pending → confirmed → ready → picked up), and email notifications keep both sides informed. No payment processing, no checkout.
* **Harvest list** — active pre-orders aggregated into per-pickup-date totals of each product to have ready. Print it and take it to the field.
* **Smart pickup dates** — pre-order dates are validated against the location's weekly schedule, season dates, and a per-location closed-dates list, so nobody orders for a day the stand is shut.

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

* Ten blocks under a dedicated category, server-rendered with live front-end updates via the WordPress Interactivity API.
* REST API endpoints for products, availability, locations, stand status, and events.
* **Abilities API** — on WordPress 6.9+, fourteen operations (list products, get/update availability, toggle stand status, RSVP to an event, create and manage pre-orders, build a harvest list, and more) are registered as Abilities, so AI agents and automation tools can discover and call them with full input/output schemas and permission checks.
* Modular architecture — disable feature modules you don't need with a single filter.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/farm-stand-manager`, or install through the WordPress plugins screen.
2. Activate the plugin.
3. Go to **Farm Stand** in the admin sidebar and click **Load Sample Data** to explore the blocks with realistic content (remove it any time with one click).
4. Create your real **Locations** and **Products**, then add the blocks to your pages.

== Frequently Asked Questions ==

= Does this plugin need a build step or external services? =

No. It is plain PHP and vanilla JavaScript — no bundler, no API keys, no third-party calls.

= How do visitors see availability changes? =

The availability board and stand status banner are server-rendered and then kept fresh with the WordPress Interactivity API, polling the plugin's REST endpoints while the page is open.

= Can I turn off features I don't use? =

Yes. Feature modules (stand status, availability board, event manager, notifications, pre-orders) can be disabled via the `lfuf_active_modules` filter; only the core data layer is required.

= What are Abilities? =

The WordPress Abilities API (WordPress 6.9+) lets plugins register operations that AI agents and automation tools can discover and execute. Farm Stand Manager registers its read and write operations as abilities with JSON Schema input/output definitions and capability checks. Write operations require `edit_posts`.

= Does the RSVP feature store personal data? =

RSVPs store the name, optional email, party size, and note a visitor submits, in a table on your own site. Nothing is sent to any external service.

= Do pre-orders process payments? =

No. A pre-order is a reservation: products, quantities, a pickup date, and contact details. Payment happens at pickup — the confirmation shows the location's accepted payment methods (including any payment links). If you need online card payments, use a dedicated e-commerce plugin.

= Where do the QR codes come from? =

They are generated in the visitor's browser by a bundled open-source library (qrcode-generator, MIT). No external QR service is called.

== Screenshots ==

1. Availability board block on the front end.
2. Stand status banner with live open/closed state.
3. Admin dashboard with module status and content gaps.
4. Availability quick entry screen.
5. Product CSV import/export.

== Changelog ==

= 1.1.0 =
* Products without a photo now show a muted illustration based on their product type, so the availability board and product cards no longer have gaps. Filter `lfuf_product_placeholder_url` to override or disable.

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

= 1.1.0 =
Products without a photo now show a type-based placeholder image. Filter `lfuf_product_placeholder_url` and return an empty string to keep the previous behaviour.

= 1.0.2 =
Important if your host runs MySQL in strict mode: the availability table was never created. This release fixes the schema and repairs existing sites automatically.

= 1.0.1 =
Fixes relative timestamps and the daily cleanup schedule on sites not set to UTC.

= 1.0.0 =
Initial public release.
