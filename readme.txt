=== ProducerKit ===
Contributors: jeromewincek
Tags: availability, pre-orders, farmers market, artisan, events
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.8.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Catalog, sales locations, live availability, pickup pre-orders and events for small independent producers — farms, makers and beekeepers.

== Description ==

ProducerKit gives small farms, makers, beekeepers and market gardeners a complete toolkit for running their web presence: a product catalog, sales locations, a live availability board, an open/closed stand banner, and events with RSVPs — all managed from the WordPress admin, with no build step and no external services.

= Products and sources =

* **Products** as a custom post type with price, unit, product type, and season.
* **Sources** — record where what you sell came from: who, where, and what was done to it in between. Link sources to products to tell the story behind what you make. The fields are named for your trade, so a beekeeper fills in an Apiary with Forage Notes and a musician a Label with a Studio.
* **CSV import/export** — bulk-manage products from a spreadsheet, with a downloadable template and optional featured-image sideloading from URLs.

= Availability =

* **Availability board block** — a live, filterable board of what's available right now, grouped by product type, with status badges (abundant, available, limited, sold out, unavailable).
* **Quick entry screen** — batch-update availability for every product from one admin page. Statuses can carry a note ("~3 bunches left") and expire automatically.
* **Availability badge block** — an inline status badge for any single product.
* **Shops that carry your goods** — mark a location as a **Retailer** and record what they have in stock. Their page gains an "Available here" list, and every product page gains a "Where to find it" line naming the shops that currently have it. Sold-out items drop off both, so nobody makes a wasted trip. Retailers need no accounts — you update it from your own delivery notes.

= Payments (at the stand, not online) =

* **Payment options per location** — list every way customers can pay: Venmo, Cash App, PayPal, a custom payment link, plus accepted-payment badges for cash, check, SNAP/EBT, and WIC/Senior FMNP market vouchers.
* **QR codes** — show a scannable code for your payment link right in the location card; the print stylesheet enlarges it for stand signage. Generated locally by a bundled library — no external service.
* **Pre-orders** — visitors reserve products for a pickup date and pay at the stand, and can view or cancel their own order from a link in the confirmation. Orders are rate-limited and spam-protected, staff manage them from a dedicated admin screen (pending → confirmed → ready → picked up), and email notifications keep both sides informed. No payment processing, no checkout.
* **Harvest list** — active pre-orders aggregated into per-pickup-date totals of each product to have ready. Print it and take it to the field.
* **Deposits, per product** — some things a producer will not hold without money down. Mark a product to take a deposit (a fixed amount per item, or a percentage) or the full amount when someone pre-orders it, and leave everything else as a reservation. A $50 deposit on a nucleus colony takes $100 for two and leaves $300 for pickup; the jars in the same order ask for nothing. The balance is yours to collect either way — send a payment link, or take it at the table and mark it paid. Requires the WooCommerce module.
* **Smart pickup dates** — pre-order dates are validated against the location's weekly schedule, season dates, and a per-location closed-dates list, so nobody orders for a day the stand is shut.

= Producer profiles =

* **Sixteen trades** — farm, bakery, beekeeping, musician, author, comics, painting, screen printing, taxidermy, and seven crafts. Each re-labels the plugin for the trade you actually practise: a potter's Material is a Clay Body, a beekeeper's is a Floral Source, a printer's is a Substrate. Individual fields follow too — a musician's source is a Label with a Studio and Mastering Notes — and so does the word for a made-to-order request, which is a Commission to a potter and an Enquiry to a beekeeper.
* **Optional fields switch on per trade** — Material, Finish and Component exist only for profiles that ask for them, so a farm never sees them, and they render on the product card and single product page under your own labels.
* **More than one trade on a site** — a farm that also bakes, or two people sharing an install. Which fields exist and which vocabulary is seeded combine; the wording resolves per person, so each of you reads the same field in your own trade's words.

= Made-to-order requests =

* **Ask, quote, agree** — a customer describes what they want, you quote a price and a date, and they accept or decline from a link in their email. The confirmation page shows the terms before anything is agreed.
* **Called what your trade calls it.** A potter takes commissions; a beekeeper asked about bulk honey, queens or wax answers an enquiry, and a grower takes a special order. The form, the emails, the confirmation page and the admin menu all follow your producer profile, so nobody's customer is asked to "commission a piece" of honey.
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
* **RSVPs** — visitors RSVP with a party size and get a confirmation email with a link to their booking, which they can cancel themselves. Rate-limited and spam-protected.
* **Guest list screen** — see who is coming to each event with a headcount and spots remaining, cancel a booking on someone's behalf, and download the list as CSV to take to the door.
* **Event list and event card blocks** for upcoming and past events.

= Built for the modern block editor =

* Eleven blocks under a dedicated category, server-rendered with live front-end updates via the WordPress Interactivity API.
* REST API endpoints for products, sources, taxonomies, availability, locations, stand status, events, RSVPs, pre-orders and commissions.
* **Abilities API** — on WordPress 6.9+, twenty operations (list products, get/update availability, toggle stand status, RSVP to an event, read a guest list, create and manage pre-orders and commissions, send a quote, build a harvest list, and more) are registered as Abilities, so AI agents and automation tools can discover and call them with full input/output schemas and permission checks.
* Modular architecture — disable feature modules you don't need with a single filter.
* **Your data stays yours.** Deleting an event or a location removes the bookings attached to it rather than leaving names and addresses behind, and deleting the plugin only removes your content if you have asked it to.

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

The WordPress Abilities API (WordPress 6.9+) lets plugins register operations that AI agents and automation tools can discover and execute. ProducerKit registers its read and write operations as abilities with JSON Schema input/output definitions and capability checks — including reading a commission queue, sending a quote, and reading or cancelling an event's RSVPs.

Write operations require `edit_posts`. Anything that touches a customer's contact details — commissions, and the RSVP guest list — requires `edit_others_posts` instead, because those records hold names and email addresses and a quote is a price the business is held to. Tokens are never returned: a quote token lets its holder accept a binding price and an RSVP token lets its holder cancel a booking, so both reach the person by email rather than through an ability response.

= Does the RSVP feature store personal data? =

RSVPs store the name, optional email, party size, and note a visitor submits, in a table on your own site. Nothing is sent to any external service.

A guest who leaves an email address gets a link to their own booking and can cancel it themselves. Deleting an event removes its RSVPs. If you delete the plugin, you choose under **ProducerKit → Producer Profile** whether your content goes with it; the default is to leave it alone.

= Do pre-orders process payments? =

Not on their own. A pre-order is a reservation: products, quantities, a pickup date, and contact details, with payment happening at pickup — the confirmation shows the location's accepted payment methods, including any payment links.

If WooCommerce is active you can additionally enable the WooCommerce module, which raises a pending order and hands the customer a payment link — for an accepted commission, and for a pre-order of anything you have marked as taking a deposit or full payment up front. That is opt-in and off by default; with the module off, or WooCommerce absent, nothing about the plugin touches payment processing, and a pre-order stays a reservation paid at pickup.

= Where do the QR codes come from? =

They are generated in the visitor's browser by a bundled open-source library (qrcode-generator, MIT). No external QR service is called.

== Screenshots ==

1. The availability board: what you have this week, grouped by type and filterable by status, with your own notes on each.
2. Sixteen trades to choose from. Your choice names the fields and adds the ones that trade needs — a beekeeper gets Floral Source and Container, a potter gets Clay Body and Glaze.
3. Updating availability: a status and a note for everything you sell, on one screen, once a week.
4. Pre-order payment, set per product: reserve only, take a deposit, or take the full price when they order.
5. The stand status banner — open or closed, with your message, address and payment link.
6. Recurring events: pick a pattern, see the dates before you publish. Each one becomes a real event people can book.
7. A shop that carries your goods, showing what is on their shelf right now.
8. Events and series together, with the recurrence rule at a glance.

== Changelog ==

= Unreleased =
* Added: switch off the parts you do not use — Pre-Orders, Commissions, Notifications and WooCommerce Settlement — from the ProducerKit dashboard. It tells you what each one is holding first, and never deletes anything.

= 2.8.0 =
* Added: a Getting Started guide inside the dashboard, written in your trade's words — a beekeeper reads about their home yard and hive notes, a potter about their studio and clay supplier.
* Fixed: on a site with no other block plugin installed, none of ProducerKit's blocks appeared in the editor. They do now — and their editor labels can be translated for the first time.
* Changed: the availability board's filters are clearer — statuses are checkboxes, types and trade fields are radio groups, and the board states what it is showing in a sentence you can undo a piece at a time.
* Fixed: the board's item count stayed in English after the first filter change, and ignored trade-field filters.

= 2.7.0 =
* Fixed: payment QR codes never appeared on the Fresh Sheet or the Location Info block in any released version — the library they need was being left out of the plugin package.
* Changed: the "Sharing the booth" field on an event can now link to the other producer — paste their web address after their name.
* Added: a Generate Default Pages button that builds the page set for your trade, with blocks already configured — as drafts, never overwriting anything you have.

= 2.6.0 =
* Fixed: clicking "ProducerKit" in the admin menu opened an empty Sources list instead of the dashboard, which made the sample-data controls unreachable without knowing the URL.
* Fixed: removing sample data also deleted unrelated leftover availability and RSVP rows belonging to content you had deleted yourself.
* Changed: the Load Sample Data button now says plainly that the example content is published and visible to visitors.
* Added: when two businesses share one site, products and events now say which one made them — "Grown by Leftfield Urban Farm", "Baked by Slowbird Bread Co." The wording follows the trade of whoever published it, and nothing appears at all on a site with a single producer. Set your business name on your own user profile.
* Added: the sample data now matches your trade. A beekeeper gets Wildflower Honey at The Home Yard, a potter a Stoneware Mug at The Studio — rather than eight farm products whoever you are.
* Added: seven trades had no event types at all. A potter now gets Open Studio, Kiln Opening and Craft Fair; a jeweller a Trunk Show.
* Fixed: a latent crash in the editor sidebars. The panels checked which post type they were on before reading its data, which breaks a rule React relies on — it happened to work, and would have stopped without warning.
* Added: events can now say who else is on the bill or sharing the booth, when people can arrive, who can come, and where tickets are sold. The words follow your trade — a band's support acts are a farm's shared booth.
* Changed: the spreadsheet column `growing_notes` is now `production_notes`, since it is where a potter writes about making and a musician about a release. A spreadsheet exported by an earlier version still imports.
* Changed: field names stored behind the scenes no longer carry a farm's vocabulary, or the name of the part of the plugin that happened to add them. Your data is renamed for you when you update. This only matters if you have written code against those names — see the upgrade notice.

= 2.5.0 =
* Fixed: on the availability board, a product-type button now fades when the statuses you are showing leave it with nothing, instead of looking available and then emptying the board.
* Fixed: a shop's page listed everything you make instead of what they carry. Products marked available everywhere now show at your own stand and market, not on a retailer's shelf.
* Fixed: the availability board offered product-type filters that showed nothing when clicked, and counted products rather than what was on the board.
* Added: the plugin now asks what you make the first time you use it. It names everything after your trade, and until you say, it uses a farm's words — so a beekeeper was quietly getting "Growing / Baking Notes" where they meant hive notes.
* Added: set a repeat from the event editor — every week, every other week, the first Saturday of the month, and so on — and see the next few dates before you publish. If a rule cannot be honoured exactly, it now tells you why instead of quietly clearing itself.
* Added: a recurring event with no end date keeps generating. It stays about a year ahead of today rather than a year ahead of when you created it, so a market never quietly runs out of dates.
* Added: a recurring event now creates a real event for each date, so people can RSVP to one Saturday rather than to the series. Any single one can be edited or cancelled — for a holiday, say — and it stays that way when the series changes.
* Added: recurring events are now understood — weekly markets, first-Saturday classes, the last day of the month. Rules the plugin cannot honour exactly are refused rather than quietly expanded to the wrong dates.
* Added: pick the products you are featuring at an event. The field was already returned by the API but there was no way to set it, so it was always empty.

= 2.4.0 =
* Added: a translation template ships with the plugin, so it can be translated into another language without waiting for the WordPress.org listing.
* Added: your producer profile now names the fields too, not just the categories. A beekeeper's source is an Apiary with Forage Notes; a musician's is a Label with a Studio and Mastering Notes. The help text under each field follows the label.
* Added: sources now have an editor panel. Their four fields were shown on the front end and returned by the API but there was nowhere in the editor to enter them.
* Fixed: the plugin's editor panels and block controls are now translatable throughout. The PHP was fully translated and the JavaScript beside it was hard-coded English — around 270 strings a translator could not reach.
* Changed: made-to-order requests are now called what your trade calls them. "Commission" is right for a potter and wrong for a beekeeper answering an enquiry about bulk honey, or a grower taking a special order — the wording now follows your producer profile everywhere a customer or you can see it.
* Added: per-product deposits on pre-orders — take a fixed amount, a percentage, or the full price up front, and leave the rest for pickup. The balance can be collected with a payment link or taken in person and marked paid.
* Fixed: pre-orders could never actually be paid for through WooCommerce. The pricing and the order-raising both existed but were never joined up, so the payment link this plugin promised for "a request" only ever appeared for commissions.
* Added: ProducerKit now identifies itself to WooCommerce as an extension, and declares compatibility with High-Performance Order Storage and the Cart & Checkout Blocks. Without this a store could be prevented from turning HPOS on.

= 2.3.0 =
* Added: filter the availability board by your trade's own fields — clay body, glaze, ink, format. The fields narrow together, and a farm profile, which uses none of them, sees no extra filters.
* Added: a **Trade Details** panel in the product editor, so those fields sit beside price and unit instead of in WordPress's generic taxonomy boxes further down the page.
* Added: six more Abilities, bringing the total to twenty. Commissions had none, and RSVPs only the visitor-facing one — so an assistant could report on pre-orders but not on commissions or a guest list.
* Changed: the icons no longer assume you farm. A carrot sat on the product editor, in the block inserter and at the top of every email the plugin sent; the commission form had a hammer. The catalogue is a tag everywhere now.
* Fixed: one of the optional taxonomies exposed a misspelled REST route name.

= 2.2.0 =
* **Every request can now be reached by the person who made it.** Commission quotes, RSVPs and pre-orders each open from a link showing what was booked, with the one action that makes sense.
* Added: RSVP guests receive a confirmation email. Previously every notification went to the site admin and the guest received nothing — and could not cancel once they closed the tab, which silently held a seat at a capped event.
* Added: the pre-order confirmation now carries a link to view or cancel the order. The form had been asking people to keep a "cancellation code" that had nowhere to go.
* Added: an RSVPs admin screen with the guest list, headcount, spots left, one-click cancellation and CSV export. The plugin had been collecting names and email addresses with no way to read them.
* Added: `uninstall.php`. Plugin bookkeeping is always cleaned up; your content only if you ask, under ProducerKit → Producer Profile.
* Changed: deleting an event or a pickup location now removes the RSVPs and pre-orders attached to it. Trashing still keeps them.
* Changed: reading a guest list now requires the Editor role rather than Contributor.

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

= 2.8.0 =
Important if you installed ProducerKit on a site without another block plugin: its blocks never appeared in the editor. They do now. Nothing to do beyond updating — pages you already built were never affected, only the ability to add new blocks.

Also adds a Getting Started guide inside the dashboard, written in your trade's words, and makes the availability board's filters clearer about which ones include and which ones choose.

= 2.7.0 =
Recommended, and worth taking if you print the Fresh Sheet: the payment QR code has never appeared in any released version — the library that draws it was being left out of the plugin package. Same for the QR on the Location Info block. Nothing to do on your side beyond updating.

Also adds a Generate Default Pages button. This plugin has no automatic archive pages, so the pages are yours to build; that button builds them for your trade, as drafts, and never touches a page you already have.

= 2.6.0 =
Stored field names are corrected on update — automatically, with your data intact. If you have written a template, snippet or integration against names like _pkit_em_ticket_url or _pkit_growing_notes, they are now _pkit_ticket_url and _pkit_production_notes. The spreadsheet column growing_notes is now production_notes; older exports still import.

If your products and events were published by more than one WordPress user, they will now carry a byline naming the producer. Set the name each user should be shown as on their own profile, or filter pkit_show_producer_byline to turn bylines off.

= 2.5.0 =
Recommended. Recurring events now create a real event for each date, so people can RSVP to one Saturday rather than to the series. One thing to know if you set a recurrence rule by hand before this release, through WordPress's custom fields box: that event becomes a series the next time it is saved. It stops appearing on the site itself and the dated events it generates appear instead, reachable from the occurrences link on its row. Nothing is deleted. If you never touched that field — and there was no way to, until now — this does not affect you.

= 2.4.0 =
Safe from 2.3.0. Adds retailer stock, deposits on pre-orders, and per-trade names for your fields. Nothing changes on its own: pre-orders stay reservations until you mark a product as taking a deposit, and a site that never chose a producer profile reads exactly as it did before.

= 2.3.0 =
Safe from 2.2.0 — no data changes. Worth taking if you use a producer profile: the trade fields it switches on can now be filtered on the board and edited beside your other product fields.

= 2.2.0 =
Recommended. RSVP guests now get a confirmation and can cancel; pre-order customers can finally use the cancellation code the form asks them to keep; and there is at last a screen showing who has RSVP'd.

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
