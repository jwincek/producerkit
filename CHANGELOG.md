# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- WooCommerce feature-compatibility declarations for High-Performance Order
  Storage (`custom_order_tables`) and Cart & Checkout Blocks
  (`cart_checkout_blocks`), plus the `WC requires at least` and
  `WC tested up to` plugin headers that make WooCommerce recognise this as an
  extension in the first place.

  The two halves have to ship together. WooCommerce only inspects plugins
  carrying a `WC tested up to` header, so without it ProducerKit was invisible
  to the compatibility screen — it appeared in no bucket at all. But adding
  that header alone is worse than omitting it: `custom_order_tables` defaults
  to treating undeclared plugins as incompatible, and WooCommerce then greys
  out the HPOS setting for the whole store. Verified on WooCommerce 11.1.0 —
  header without declaration put the plugin in the blocking list; header with
  declaration cleared it.

  Both claims are true rather than aspirational. Nothing here reads or writes
  order storage directly: settlement keeps its own `order_id` mapping in its
  own table and checkout goes through `$order` CRUD, and the entire WooCommerce
  surface is four `woocommerce_order_status_*` transitions, which behave
  identically under HPOS and under the block checkout. A test pins that surface
  so a future cart or gateway hook cannot quietly falsify the declaration.

## [2.3.0] - 2026-09-04

The trade fields a producer profile switches on now do something everywhere
they appear, and the plugin stops looking like it is only for farms.

### Added

- **Filter the availability board by trade field.** A potter with a hundred
  pieces can ask for everything in stoneware. Each field the active profile
  switches on gets its own row, and the fields narrow together — Stoneware and
  Ash Glaze shows what is both. Rows are built from the terms actually on the
  board, and a field with only one value gets no row, because that is a control
  that cannot change what you see.
- **A Trade Details panel in the product editor.** These fields were reaching
  the editor through WordPress's generic taxonomy panels, so a maker filled in
  price and unit in one place and Clay Body somewhere else. They are now
  together, and the duplicate panels are gone.
- **Six new Abilities**, bringing the total to twenty. Commissions had none at
  all and RSVPs had only the visitor-facing create, so an agent could answer
  "which pre-orders are due for pickup?" and not "which commissions are waiting
  on a quote?" or "who is coming Saturday?". Adds `list-commissions`,
  `count-commissions-by-status`, `send-commission-quote`,
  `update-commission-status`, `list-event-rsvps` and `cancel-rsvp` — all gated
  on `edit_others_posts`, matching the screens that guard the same records, and
  none of them return a token.

### Changed

- **The icons no longer name one trade.** A carrot sat on the product editor
  panel and in the block inserter, a hammer on the commission form, a leafy
  green and a sheaf of grain on the dashboard — and a carrot at the top of
  every email the plugin sent, to a potter's customers and a band's mailing
  list alike. The catalogue is now a tag everywhere it appears.

### Fixed

- The optional taxonomies pluralised their REST base by appending "s", giving
  `finishs`. A REST base is a public route name, so it is now spelled out.

## [2.2.0] - 2026-09-03

Every request this plugin takes from a visitor can now be reached again by the
person who made it. Previously none of them could: a commission quote link
404'd, an RSVP was unreachable once the tab closed, and a pre-order handed out
a "cancellation code" with nowhere to enter it.

### Added

- **A page behind every request.** Commission quotes, RSVPs and pre-orders each
  open from a link with the visitor's own token, showing what they booked and
  offering the one action that makes sense — accept or decline, cancel, cancel.
  Actions are POSTs with a nonce, never GETs, so a mail client prefetching a
  link cannot cancel someone's booking.
- **Guest confirmation emails for RSVPs**, which did not exist at all: every
  notification went to the site admin. The confirmation carries the event, the
  date, the party size and the link to the booking.
- **A cancellation link in the pre-order confirmation.** The form asked people
  to keep a code and the email left it out.
- **An RSVPs admin screen** — the guest list per event with headcount and spots
  left, one-click cancellation, and CSV export for working the door. Cells that
  a spreadsheet would execute as formulas are escaped; a guest controls their
  own name.

### Changed

- Reading a guest list requires `edit_others_posts` rather than `edit_posts`,
  matching commissions. Filterable via `pkit_rsvp_manage_cap`.
- Deleting an event or a pickup location now removes the RSVPs and pre-orders
  that belonged to it, rather than leaving names and email addresses pointing
  at a post that no longer exists. Trashing still keeps them, since a trashed
  event can be restored.

### Added (housekeeping)

- **`uninstall.php`**, which did not exist. Schema versions, the cron hook and
  rate-limit records always go; products, locations, events and the requests
  attached to them only when the site has ticked a box under ProducerKit →
  Producer Profile. Off by default, because deleting a plugin to troubleshoot
  should not destroy a catalogue.


## [2.1.0] - 2026-09-02

Commissions did not work in 2.0.0. A maker could send a quote and no customer
could accept it. This release makes the feature function and fixes the money
handling around it.

### Added

- **A quote confirmation page.** The accept/decline route is POST-only so that
  a mail client prefetching a link cannot accept a quote on a customer's
  behalf — but the email could only send a clickable link, which is a GET, so
  every customer who clicked got a 404. The link now opens a page showing the
  price, the estimated date and what was asked for, with buttons that post the
  decision. Served at `?pkit_quote=<token>`, `noindex`, and `no-referrer` so a
  quote link cannot travel further than the person it was sent to.
- **Re-quoting.** A quote can be revised, or reissued after the 30-day expiry.
  Previously the customer was told to ask for a new link and the maker had no
  way to send one.
- **Pagination** on the commissions screen, which stopped at 100 rows.

### Fixed

- **Pre-orders could be undercharged.** The checkout guard used a schema.org
  price heuristic that reads the first run of digits: "2 for $5" became 2.00
  and "$1,200.00" became 1.00. Any line that is not one unambiguous amount now
  refuses the checkout and names the product.
- **Customer details were publicly readable.** The hidden product raised for a
  commission was saved with WordPress's default published status, so the
  customer's name and their verbatim request were served at `/?p=<id>` to
  anyone who guessed the id. Now private, and the description no longer
  carries the customer's words at all.
- **One admin click could permanently break a commission**, leaving it marked
  quoted with no price and no token, in a state the quote form would not
  reopen.
- **An accepted commission never produced a payment link**, because the code
  that raises the order had no caller.
- **Paying an order could settle nothing**, because the link between order and
  request was not checked before a payment URL was handed out.
- Settlement ran twice per payment, overwriting the payment time with the
  fulfilment time.
- Reading customer records and issuing binding quotes required only
  Contributor. Now Editor, filterable via `pkit_commission_manage_cap`.
- The public commission endpoint returned database columns added by the
  WooCommerce module.
- Retrying a failed checkout created a duplicate hidden product each time.
- Commission rate limiting hashed visitor IP addresses without a salt.
- A commission's type and material displayed as raw slugs
  ("live-edge-walnut") in the admin and in emails.
- Settlement columns could never be added on a site that enabled the
  commissions module after the WooCommerce one, and a failed table creation
  was never retried.
- Around twenty database queries were run to draw the commission screen's
  filter row, and four per order status change store-wide.


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
