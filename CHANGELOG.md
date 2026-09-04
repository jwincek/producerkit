# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- A recurrence-rule reader, and a refusal for the rules it cannot honour.
  `_pkit_recurrence_rule` had stored an RFC 5545 RRULE since it was registered
  and nothing ever read it — worse than an unused field, because
  `/events/upcoming` filters on the single start date, so a weekly market that
  began eight weeks ago is absent from the upcoming feed and present in the
  past one. As far as the API is concerned the market is over.

  This is a deliberate subset: `FREQ` (daily, weekly, monthly, yearly),
  `INTERVAL`, `COUNT`, `UNTIL`, `BYDAY` including ordinals like `1SA` and
  `-1SU`, `BYMONTHDAY` including `-1` for the last day, and `BYMONTH`.
  Anything else is refused **by name**. A rule carrying `BYSETPOS` that
  expanded without it would produce confidently wrong dates, and a producer
  would find out when nobody came.

  Expansion works in the site's timezone rather than on timestamps, so a
  market that opens at 09:00 still opens at 09:00 the week the clocks change.
  Monthly rules step from the first of the month, because PHP's own
  `+1 month` on 31 January gives 3 March — which would skip February and land
  twice in March. A month with no 31st simply does not fire, as RFC 5545 says.

  An invalid rule can no longer be saved. Note the limit: a REST write of a
  bad rule returns 200 with the value dropped, because a `sanitize_callback`
  is handed no object id and `register_post_meta()` has no validate hook. The
  editor panel that explains why comes with the generator.

  This is the first of three steps. It reads and refuses rules; it does not
  yet create anything. Occurrences become real event posts — so RSVPs,
  capacity and guest lists work unchanged — in the step after next.

- Featured products on an event. `_pkit_featured_product_ids` had been
  returned by two REST readers since it was registered and written by
  nothing, so a client could read the key and always get an empty array —
  the same shape as the sources bug, on a different post type. This is the
  writer: a picker in the event sidebar, mirroring the one products already
  use for sources.

  A farm at a festival can now name the three things worth the trip, and
  `/events/<id>/details` and `/events/upcoming` reflect it.

  The panel keeps its linked-product lookup separate from the list it offers
  to add, so a featured product beyond the first fifty, or one since
  unpublished, still renders as a row that can be removed rather than
  vanishing from the panel while staying in the meta.

## [2.4.0] - 2026-09-05

Three sales formats a beekeeper actually uses, and two features that turned
out never to have worked.

Retailer stock answers the question a combined availability board cannot:
someone in town wanting a jar today needs to know which of four shops to walk
to. Deposits answer the one a nucleus colony raises, and building them
uncovered that pre-orders could not be paid for through WooCommerce at all —
the pricing and the order-raising both existed and nothing called them
together. Sources had four fields with nowhere to type them, complete on
every layer except the one a person touches.

### Added

- A committed translation template at `languages/producerkit.pot`, generated
  by `bin/make-pot.sh`. WordPress.org builds catalogues from the hosted plugin
  once it is listed, so this is not required — but a template in the
  repository lets someone translate before the listing exists, and a diff on
  it shows which strings a change added or removed, which is the cheapest way
  to notice a control that shipped hard-coded.

  Checked in three places, because the failure is silent: a stale template
  still loads and still translates, just without whatever was added since.
  `bin/validate-config.php` fails if it is missing, was generated for another
  text domain, or declares a version other than the plugin's. CI regenerates
  and compares. The build refuses to package without it.

  The comparison ignores `POT-Creation-Date`, `X-Generator` and
  `Project-Id-Version`, which churn on every run for reasons that have nothing
  to do with the strings — a byte comparison would fail on all three and teach
  everyone to ignore the check. It also separates its two failure modes:
  strings appearing or vanishing is translation work, while source references
  moving because a line shifted is a one-command fix that loses nothing.

  Also adds the `Domain Path` header, which was missing.

- Producer profiles now re-word fields, not just taxonomies and post types.
  A musician cataloguing a release read "Farm / Origin Name", "Location",
  "History" and "Milling / Process Notes"; they now read Label, Studio,
  Background and Mastering Notes. A beekeeper reads Apiary, Yard Location,
  Forage Notes and Extraction Notes.

  The data model was already right — who this came from, where, and what was
  done to it in between are the right three questions for a record label, a
  tannery or a mill. Only the words were wrong, so the meta keys are
  untouched and a test asserts data survives a profile switch.

  Help text moves with the label, because re-labelling a field and leaving
  the sentence under it talking about grind and cure is the same mistake one
  line further down. A profile may override either half of the pair.

  Fourteen profiles carry wording; the rest keep the farm defaults. Labels
  reach the editor through the same inline settings object that already
  carries the request vocabulary, so no new REST route was needed.

  This also settles a disagreement nobody had noticed: the front-end template
  said "Farm" and "Milling Notes" where the sidebar said "Farm / Origin Name"
  and "Milling / Process Notes", for the same two fields. Both now read from
  one source, and a test fails if either stops.

- An editor panel for sources. The `pkit_source` post type registered four
  fields — origin name, location, history and milling notes — rendered them
  on the front end, returned them from REST and reported them through the
  list-sources ability, and offered nowhere to type any of them. The
  editor-script map covered products, locations and events; `pkit_source` was
  never in it and `editor-source.js` did not exist.

  Not strictly unwritable: the post type declares `custom-fields` support, so
  someone who enabled that panel and knew the meta keys could set them by
  hand. That is the absence of a feature rather than one.

  A test now cross-checks the thing that let this through — every layer was
  individually complete, so nothing was obviously broken. For each post type
  with an editor script, every registered meta key must appear in it or be
  listed with a reason. It found two more the same day (issue #37).

### Added

- Per-product deposits on pre-orders. A product can now take a fixed amount
  per item, a percentage of the line, or the full price up front, with
  everything else left for pickup — and reserve-only stays the default, so an
  existing catalogue does not start charging when this ships.

  A fixed deposit scales with quantity, because "$50 per nuc" means $100 for
  two. A deposit larger than the line it sits on is charged as the full amount
  rather than raised as an order that owes the customer money, and a deposit of
  zero is treated as a reservation instead of a $0.00 order. The two halves of
  a split always sum to the line exactly: the balance is derived by
  subtraction, since two independently rounded figures can miss by a cent and
  a customer quoted $50.00 now and $149.99 later on a $200.00 line will notice.

  The balance is collected either way, chosen per pre-order rather than baked
  into the product: raise a second order and send a payment link, or take the
  money at the pickup table and mark it paid. The in-person route deliberately
  creates no WooCommerce order — money handed over at a table is not something
  the store witnessed, and inventing an order to represent it would make its
  takings wrong.

### Fixed

- Editor-facing JavaScript is now translatable. The PHP side of this plugin
  has always been fully translated, and every control sitting beside it — the
  three CPT sidebar panels and ten of the eleven blocks — was hard-coded
  English. 161 strings a translator simply could not reach, because
  `wp i18n make-pot` only sees what is wrapped. Running it before and after:
  685 strings and 26 JavaScript references become 858 and 241.

  Wrapping alone would not have been enough for the sidebar panels. They are
  not blocks, so nothing registered a translation catalogue against their
  handles, and their `__()` calls would have run and resolved to themselves.
  Blocks get that for free from `block.json`'s `textdomain`, which
  `WP_Scripts::set_translations()` honours by also adding the `wp-i18n`
  dependency.

  Option values, class names, icons and CSS stay untranslated, which matters
  more than the wrapping: a translated `value` is not a cosmetic bug but a
  broken control, and it would break only in locales nobody here tests in. A
  test asserts both directions.

  One Placeholder message was assembled by string concatenation and is now
  built with `sprintf`, since word order differs by language and the fragments
  could not be reassembled correctly.

### Changed

- Made-to-order requests are now worded per producer profile. "Commission" is
  the right word for a potter, a painter or a taxidermist, and the wrong one
  for most other people this plugin serves: a beekeeper asked about bulk
  honey, mated queens or wax answers an enquiry, and a grower takes a special
  order. Showing a beekeeper's customer the word "commission" makes the form
  read as though it belongs to someone else's business.

  Post types and taxonomies already re-labelled themselves through
  `pkit_post_type_names` and `pkit_taxonomy_names`. Commissions are rows in a
  custom table rather than posts, so they flowed through neither and were left
  speaking one trade's language to everybody. A new `pkit_commission_names`
  filter closes that gap, with four slots rather than the usual triple —
  the concept also needs a verb, since "Commission a piece" and "Ask about
  bulk orders" are not the same sentence with a noun swapped.

  Six profiles now override it: beekeeping (Enquiry), farm and bakery (Special
  Order), musician (Booking), author and comics (Request). The craft profiles
  keep "Commission", because for them it is correct.

  Only wording moved. The `'commission'` type string, the table, the REST
  routes, the hook names, the four ability names and the block name are all
  identifiers other people build against, and a test asserts each one is
  unchanged. The request form block's inserter title is now the trade-neutral
  "Request Form".

### Fixed

- Pre-orders could never be paid for through WooCommerce. `price_preorder()`
  had computed lines and totals since the module was written, and
  `create_order()` had known how to raise an order for either request type,
  but nothing ever called the two together — the only caller was
  `checkout_for_commission()`. The pricing function's sole references outside
  the module were in its own tests, which is why it looked finished. The
  readme meanwhile promised a payment link for "a request", which is a
  pre-order or a commission; it was only ever true for commissions.

  Same shape as the commissions bug in 2.0.0: two individually-correct halves
  with nothing joining them, and tests on each half that made the whole look
  done.

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

[Unreleased]: https://github.com/jwincek/producerkit/compare/v2.4.0...HEAD
[2.4.0]: https://github.com/jwincek/producerkit/compare/v2.3.0...v2.4.0
[1.1.0]: https://github.com/jwincek/producerkit/releases/tag/v1.1.0
[1.0.2]: https://github.com/jwincek/producerkit/releases/tag/v1.0.2
[1.0.1]: https://github.com/jwincek/producerkit/releases/tag/v1.0.1
[1.0.0]: https://github.com/jwincek/producerkit/releases/tag/v1.0.0
