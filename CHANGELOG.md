# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-09-02

A breaking release. **There is no upgrade path from 1.x.** Every stored
identifier was renamed, and no migration is provided — see *Removed* below
before updating any site that ran 1.1.0 or earlier.

### Added

- **Producer profiles.** Sixteen trades — Farm, Bakery, Beekeeping, Musician,
  Author, Comics & Graphic Novels, Painting & Drawing, Screen Printing,
  Taxidermy, and seven crafts — each re-labelling the product taxonomies and
  seeding that trade's vocabulary. A site may run **more than one**: which
  fields exist and which terms are seeded union across the active profiles,
  while the wording resolves per person, so two people sharing an install each
  read the same field in their own trade's words.
- **Commissions module**, merged in from wc-artisan-tools: made-to-order
  requests with a quote round-trip, its own table, an enforced status machine,
  an admin queue, a request-form block, and five emails.
- **Optional WooCommerce module.** Absent WooCommerce, requests settle
  directly; present, they can settle through it.
- **Three optional product taxonomies** — Material, Finish, Component —
  registered only when a profile asks for them, and rendered on the Product
  Card and the single product page under the viewer's own labels.
- `Core\Requests`, a shared substrate behind every public form: salted IP
  hashing, client-IP resolution, token issue, honeypot, and spam-guard
  delegation that degrades to allow when Onsite Spam Guard is absent.

### Changed

- **Admin menu consolidated.** Five top-level items became three, kept
  adjacent by a `menu_order` filter. Sources and Locations are nested; the
  catalogue and events stay top-level because nesting a post type also hides
  its taxonomy submenus.
- **Menu labels avoid collisions.** The catalogue reads **Catalog** and events
  read **Calendar**, because WooCommerce owns "Products" and The Events
  Calendar owns "Events" in the same sidebar. The content is still Products
  and Events wherever the word appears in a sentence.
- **One Interactivity store.** Six per-block stores became a shared
  `producerkit` namespace with feature modules; block view scripts are now
  import shims. Per-block JavaScript fell from 800 lines to 147.

### Fixed

- **RSVP through an Event Card did not record that an event had filled.** The
  card and the list each carried their own copy of the submit action in the
  same store namespace; the copies had drifted, and on a page holding both
  blocks whichever loaded second silently decided the behaviour for both.
- Commission rate-limit keys hashed the client IP with bare `md5()`, which is
  reversible by brute force over the IPv4 space. All IP hashing is now salted
  with `wp_salt( 'auth' )`.
- The Onsite Spam Guard call passed a single wrapper array where the function
  takes `( array $fields, string $context )`, so the context was sent as a
  field and the guard's hidden fields never reached it from a JSON body.
- The QR helper's registered path and its filename disagreed after the rename,
  so the script 404'd.
- Block wrapper attributes were run through `wp_kses_data()` in one block,
  which double-encodes `&` in an attribute string.

### Removed

- **The `lfuf` prefix, with no migration.** Post types, taxonomies, meta keys,
  tables, options, cron hooks, transients, block names and the REST namespace
  were all renamed: `lfuf_*` to `pkit_*`, and `lfuf/*` to `producerkit/*`.
  Content stored by 1.x is **not** read by 2.0.0 and is not converted.

  This was done deliberately while the plugin had no installed base. If you
  are running 1.x with real data, do not update — export first, or stay on
  1.1.0.


## [1.1.0] - 2026-08-03

### Added
- Products with no featured image now fall back to a muted illustration chosen
  by their product type, so the availability board and product cards stay
  visually even on a site that has not uploaded photos yet. Art ships for the
  five types the plugin seeds by default; a product whose type has no art
  renders no image, exactly as before.
- `pkit_product_placeholder_url` filters the chosen placeholder. Return `''`
  to switch the feature off entirely.

## [1.0.2] - 2026-08-03

### Fixed
- The availability table was never created on MySQL servers running with
  `STRICT_TRANS_TABLES` — the default since MySQL 5.7. Its `notes` column was
  declared `TEXT NOT NULL DEFAULT ''`, and MySQL forbids defaults on BLOB/TEXT
  columns: a permissive server drops the default with a warning, but a strict
  one rejects the `CREATE TABLE` outright. Every feature that reads
  availability — the board, the badge, quick entry, the Fresh Sheet, the REST
  endpoints and the abilities — silently had no table to read. The column
  needed no default in the first place, since `upsert()` is its only writer and
  always supplies a value.
- On permissive servers the same declaration made `dbDelta()` retry an
  impossible `ALTER … SET DEFAULT` on every run, logging a database error each
  time.
- The availability table now self-heals on `plugins_loaded` when its stored
  schema version is behind, matching what the RSVP and pre-order tables already
  did. Sites whose activation failed under strict mode get their table without
  needing a manual deactivate/reactivate. `pkit_availability_db_version` was
  previously written but never read.
## [1.0.1] - 2026-08-02

### Fixed
- Relative times on the stand status banner and the admin dashboard were off
  by the site's UTC offset — a stand toggled seconds ago read "4 hours ago" on
  a UTC-4 site. `strtotime()` returns a true epoch while
  `current_time( 'timestamp' )` returns epoch plus the offset, and the two were
  being compared directly.
- The daily availability cleanup was scheduled from that same local-wall-clock
  value, but `wp_schedule_event()` expects a real epoch, so the "03:00" job
  actually ran `gmt_offset` hours away from 3am — only correct on UTC sites.
  Now resolved through `wp_timezone()` and covered by a regression test across
  four timezones.

## [1.0.0] - 2026-08-02

### Added
- Initial public release.
- **Products and sources.** Products carry price, unit, product type and
  season. Sources document where things come from — partner farms, grain
  origins, milling notes — and link to the products they feed into.
- **CSV import/export** for products, with a downloadable template and
  optional featured-image sideloading from URLs.
- **Availability board block** — a live, filterable board of what is available
  right now, grouped by product type, with abundant/available/limited/sold-out
  badges. Plus an inline **availability badge** block for a single product.
- **Availability quick entry**, an admin screen that batch-updates every
  product from one page. Statuses can carry a note ("~3 bunches left") and
  expire on their own.
- **Per-location payment options** — Venmo, Cash App, PayPal, a custom payment
  link, and accepted-payment badges for cash, check, SNAP/EBT and WIC/Senior
  FMNP vouchers. Payment links render as a scannable QR code, generated in the
  browser by a bundled MIT library rather than an external service.
- **Pay-at-pickup pre-orders.** Visitors reserve products for a pickup date and
  pay at the stand — no payment processing and no checkout. Orders are rate
  limited and honeypot-protected, staff move them through pending → confirmed →
  ready → picked up from a dedicated screen, and both sides get email.
- **Harvest list**, aggregating active pre-orders into per-pickup-date totals
  of each product to have ready.
- **Schedule-aware pickup dates**, validated against the location's weekly
  schedule, season dates and per-location closed-dates list, so nobody can
  order for a day the stand is shut.
- **Fresh Sheet** — a print-ready one-pager of today's availability with
  prices, hours, payment options and a payment QR code.
- **Structured data**: products emit schema.org Product/Offer with price and
  live availability, locations emit LocalBusiness with address, coordinates,
  opening hours and accepted payments.
- **Stand status** — a live open/closed banner block with an optional message,
  an admin-bar quick toggle, and a location info block for address, hours and
  payment options.
- **Farm events** with event types, schedules and optional donation links.
  Visitors RSVP with a party size and receive a cancellation token; RSVPs are
  rate limited, capacity is enforced atomically, and email is optional.
- **Ten blocks** in a dedicated category, server-rendered and kept fresh on the
  front end with the WordPress Interactivity API.
- **REST API** for products, availability, locations, stand status and events.
- **Abilities API** support on WordPress 6.9+: fourteen operations registered
  with JSON Schema input/output and capability checks, so agents and automation
  can discover and call them.
- **Modular architecture** — every feature module except the core data layer
  can be switched off through the `pkit_active_modules` filter.

[Unreleased]: https://github.com/jwincek/producerkit/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/jwincek/producerkit/releases/tag/v1.1.0
[1.0.2]: https://github.com/jwincek/producerkit/releases/tag/v1.0.2
[1.0.1]: https://github.com/jwincek/producerkit/releases/tag/v1.0.1
[1.0.0]: https://github.com/jwincek/producerkit/releases/tag/v1.0.0
