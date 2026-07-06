=== Farm Stand Manager ===
Contributors: jeromewincek
Tags: farm stand, farmers market, availability, products, events
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
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

= Stand status =

* **Stand status banner block** — a live open/closed banner with an optional message ("Back at 2 PM"), polling for changes while the page is open.
* **Admin-bar quick toggle** — open or close the stand from anywhere in the admin.
* **Location info block** — address, hours, and payment handle for each sales location.

= Events =

* **Farm events** — pizza nights, potlucks, workshops, tours — with event types, schedules, and optional donation links.
* **RSVPs** — visitors can RSVP with a party size; each RSVP gets a cancellation token. Rate-limited, with optional email notifications.
* **Event list and event card blocks** for upcoming and past events.

= Built for the modern block editor =

* Nine blocks under a dedicated category, server-rendered with live front-end updates via the WordPress Interactivity API.
* REST API endpoints for products, availability, locations, stand status, and events.
* **Abilities API** — on WordPress 6.9+, ten operations (list products, get/update availability, toggle stand status, RSVP to an event, and more) are registered as Abilities, so AI agents and automation tools can discover and call them with full input/output schemas and permission checks.
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

Yes. Feature modules (stand status, availability board, event manager, notifications) can be disabled via the `lfuf_active_modules` filter; only the core data layer is required.

= What are Abilities? =

The WordPress Abilities API (WordPress 6.9+) lets plugins register operations that AI agents and automation tools can discover and execute. Farm Stand Manager registers its read and write operations as abilities with JSON Schema input/output definitions and capability checks. Write operations require `edit_posts`.

= Does the RSVP feature store personal data? =

RSVPs store the name, optional email, party size, and note a visitor submits, in a table on your own site. Nothing is sent to any external service.

== Screenshots ==

1. Availability board block on the front end.
2. Stand status banner with live open/closed state.
3. Admin dashboard with module status and content gaps.
4. Availability quick entry screen.
5. Product CSV import/export.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Products, sources, locations, and events as custom post types.
* Availability board, badge, stand status banner, location info, product card, event list, event card, stand hours, and stand toggle blocks.
* Availability quick entry, CSV product import/export, admin dashboard.
* REST API and Abilities API coverage for all core operations.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
