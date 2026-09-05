# ProducerKit

Catalog, sales locations, live availability, pickup pre-orders and events for small independent producers — farms, makers and beekeepers. Blocks and Abilities API support.

Single plugin, modular architecture. No build step required.

## Quick Start

1. Clone into `wp-content/plugins/`:
   ```
   git clone https://github.com/jwincek/producerkit.git
   ```
2. Activate in WordPress admin.
3. Go to **ProducerKit** in the sidebar.
4. Click **Load Sample Data** to see the blocks in action with realistic test content.
5. Read [`GETTING-STARTED.md`](GETTING-STARTED.md) for the full walkthrough.

## Requirements

- WordPress 6.9+ (Abilities API, Interactivity API directive SSR)
- PHP 8.1+

## Architecture

```
producerkit/
├── producerkit.php                 # Bootstrap, module loader, block registration
├── includes/
│   ├── admin-dashboard.php            # Admin dashboard with module status
│   ├── sample-data.php                # Load/remove sample data toggle
│   └── sample-data-markers.php        # Front-end "Sample" badges, admin notices
├── assets/js/
│   ├── store.js                       # Shared Interactivity store (one namespace)
│   └── interactivity/                 # Feature modules; blocks import these
│   ├── editor-location.js             # Location CPT sidebar panels
│   ├── editor-product.js              # Product CPT sidebar panels
│   └── editor-event.js                # Event CPT sidebar panels
├── modules/
│   ├── core/                          # Always loaded
│   │   ├── bootstrap.php
│   │   └── includes/
│   │       ├── post-types.php         # CPTs: product, source, location, event
│   │       ├── taxonomies.php         # product_type, season, event_type (auto-seeded)
│   │       ├── meta-fields.php        # All post meta (show_in_rest)
│   │       ├── availability-table.php # Custom DB table + CRUD + expiration cron
│   │       ├── rest-api.php           # REST routes under producerkit/v1
│   │       ├── abilities.php          # WP 6.9+ Abilities API
│   │       ├── single-content.php     # CPT single page meta display
│   │       ├── single-styles.php      # Front-end styles for single CPTs
│   │       ├── admin-columns.php      # Custom columns for CPT list tables
│   │       └── product-import-export.php # CSV import/export for products
│   ├── producer-profiles/             # Trade vocabulary + optional fields
│   │   ├── bootstrap.php
│   │   ├── includes/
│   │   │   ├── profiles.php           # Registry, lazy per-file loading
│   │   │   ├── taxonomies.php         # Optional material/finish/component
│   │   │   └── admin-settings.php     # Profile picker
│   │   └── profiles/                  # 16 profiles, one file each
│   ├── commissions/                   # Made-to-order requests
│   │   ├── bootstrap.php
│   │   └── includes/
│   │       ├── commissions-table.php  # Table, status machine, lifecycle
│   │       ├── rest-extensions.php    # Public submit + tokenized decisions
│   │       └── admin-commissions.php  # Request queue + quote form
│   ├── woocommerce/                   # Optional settlement layer
│   │   ├── bootstrap.php              # Gated on the WooCommerce class
│   │   └── includes/
│   │       ├── settlement.php         # Settlement columns on both tables
│   │       ├── checkout.php           # Product + pending order + pay URL
│   │       └── order-sync.php         # Order paid -> request settled
│   ├── stand-status/
│   │   ├── bootstrap.php
│   │   └── includes/
│   │       ├── meta-extensions.php    # _pkit_ss_* meta on locations
│   │       ├── rest-extensions.php    # /stand/{id}/status, /stand/{id}/info
│   │       ├── admin-bar.php          # Admin bar quick-toggle
│   │       └── abilities.php          # Stand-specific abilities
│   ├── availability-board/
│   │   ├── bootstrap.php
│   │   └── includes/
│   │       ├── rest-extensions.php    # /board endpoint with grouped data
│   │       ├── admin-quick-entry.php  # Batch availability update page
│   │       └── abilities.php          # Board abilities
│   └── event-manager/
│       ├── bootstrap.php
│       └── includes/
│           ├── meta-extensions.php    # _pkit_em_* meta on events
│           ├── rsvp-table.php         # Custom RSVP table + CRUD + rate limiting
│           ├── rest-extensions.php    # Event listing, RSVP endpoints
│           ├── render-helpers.php     # Shared render functions for event blocks
│           └── abilities.php          # Event abilities
│   └── notifications/
│       ├── bootstrap.php
│       └── includes/
│           └── email-notifications.php # RSVP, stand, expiration emails
├── blocks/                            # Flat directory, all blocks
│   ├── product-card/                  # Single product display
│   ├── availability-badge/            # Inline status badge
│   ├── location-info/                 # Location card with Venmo
│   ├── stand-status-banner/           # Interactivity API, live polling
│   ├── stand-toggle/                  # Editor-only admin control
│   ├── stand-hours-schedule/          # Weekly schedule grid (semantic table)
│   ├── availability-board/            # Interactivity API, client-side filtering
│   ├── event-list/                    # Interactivity API, inline RSVP
│   ├── event-card/                    # Single event embed with RSVP
│   └── commission-form/               # Public custom-work request
├── languages/
├── GETTING-STARTED.md                 # Walkthrough for farm operators
├── composer.json
├── .editorconfig
└── README.md
```

## Modules

### Core (always active)

The shared data layer. Registers four custom post types (`pkit_product`, `pkit_source`, `pkit_location`, `pkit_event`), three always-on taxonomies with auto-seeded default terms (plus three more the producer profile may switch on), a custom `{prefix}_pkit_availability` table for time-sensitive product status with daily expiration cron, 46 REST routes under `producerkit/v1`, Abilities API abilities for AI/automation discoverability, single CPT page enhancements with structured meta tables, custom admin columns for all CPT list tables, a "Needs Attention" dashboard widget that flags missing content, and CSV import/export for bulk product management.

### Admin menu

Three top-level items, not five, and adjacent — **ProducerKit**, **Catalog**, **Calendar** sit together wherever the parent lands.

Adjacency comes from a `menu_order` filter rather than `menu_position`, because a position cannot deliver it: a plugin that sets none is placed at `++$_wp_last_object_menu`, which starts at 25 — the same range any content plugin would choose — so on a busy site a neighbour drops between two of ours whatever number we pick. The reorder is additive: everything else keeps its relative order, and anything missing (a module switched off) is skipped.
 **Catalog** and **Events** stay top-level because they own taxonomies; **Sources** and **Locations** are nested under **ProducerKit**, because they own none and are configured once rather than worked in daily.

That split is forced by core: `wp-admin/menu.php` skips a post type whose `show_in_menu` is a string, so nesting one drops its "Add New" entry *and* every taxonomy submenu it has. Nesting Catalog would put up to five taxonomies out of reach of the menu entirely.

Both of the obvious names are taken by plugins ProducerKit is likely to sit beside: WooCommerce registers a top-level **Products**, The Events Calendar a top-level **Events**. So the sidebar says **Catalog** and **Calendar** while the content stays Products and Events everywhere the word appears in a sentence. A producer profile can re-word them again — a musician sees *Merch* and *Shows*.

### Producer Profiles

Re-labels the product taxonomies for the trade the site actually practises, and seeds that trade's vocabulary. Sixteen profiles ship: **Farm** (the default), **Bakery**, **Beekeeping**, **Musician**, **Author**, **Comics & Graphic Novels**, **Painting & Drawing**, **Screen Printing**, **Taxidermy**, and seven crafts — Woodworking, Pottery, Jewelry, Metalwork, Fiber Arts, Leather and General.

**General** is the deliberate fallback: all three fields, no vocabulary. A trade whose words we would only be guessing at is better served by a blank slate than by someone else's wrong list.

A profile does two things:

- **Re-labels.** "Material" becomes *Floral Source* for a beekeeper, *Wood Species* for a woodworker, *Clay Body* for a potter. All eleven WordPress labels are derived from one singular/plural pair.
- **Switches on optional fields.** `pkit_material`, `pkit_finish` and `pkit_component` register only for profiles that ask for them, so a farm never sees them. They render on the Product Card block and the single product page, labelled as the viewer's profile names them — core asks which taxonomies count via the `pkit_detail_taxonomies` filter, so with this module off the templates behave exactly as before.

Switching is **additive** — it seeds new terms and never deletes a term or a product, so changing your mind is safe.

**More than one trade on a site** is supported, and the two halves of a profile behave differently:

- **Structure unions.** Which optional fields exist and which vocabulary is seeded are physical facts about the install, so they combine — a farm that also bakes gets both. Seeding only ever inserts, so there is no conflict to resolve.
- **Wording resolves per person.** There is one Material field with one label, and "Wood Species" and "Flour" have no sensible merge. But a label is display, so each admin picks which trade's words *they* read: the grower sees Material, the baker sees Flour, over the same field. Logged-out visitors and anyone who hasn't chosen get the first active profile, so the front end stays deterministic.

Both are set under **ProducerKit → Producer Profile** — the site list needs `manage_options`, the personal choice only `edit_posts`.

Core knows nothing about this module: it exposes two filters, `pkit_taxonomy_names` and `pkit_taxonomy_default_terms`, and the module answers them. Deactivate it and core falls back to its own farm vocabulary unchanged.

### Pre-Orders

Cartless reservations for collection. A visitor picks products and a pickup date; no money moves through the plugin, so payment happens at the counter or through one of the location's payment links.

Pickup dates are validated against data other modules already hold — the location's weekly schedule, its season dates, and its blackout dates — so the form cannot offer a day the stand is shut. The **Harvest List** aggregates active orders per pickup date into per-product totals: what to have ready, and how much.

Shares the request substrate in `Core\Requests` with RSVPs and commissions: salted IP hashing, honeypot, spam-guard delegation, token issue.

### Commissions

Made-to-order requests, for makers who take custom work. A customer describes something that does not exist yet; the maker quotes a price and an estimated date; the customer accepts or declines from a link in their email.

Kept as its own table rather than folded into pre-orders, because at submission a commission has no pickup date and no product — the point is that the maker will make one — while `pkit_preorders` requires both.

- **Enforced transitions.** `new → quoted → accepted → in_progress → complete`, with `declined` and `cancelled` as terminal branches. Illegal moves are refused, so a stale admin tab cannot revive a decision the customer already made.
- **Two tokens.** A long-lived one lets the customer see their own request; a short-lived quote token (30 days) spends itself on accept or decline, so the emailed link cannot be replayed.
- **Guests, not accounts.** Accept and decline authenticate with the quote token alone, over POST rather than GET, so a link preview or mail-client prefetch cannot accept on the customer's behalf.

Managed under **ProducerKit → Commissions**: the request queue with a status filter, an inline quote form, and one-click moves along the machine. The **Commission Request Form** block puts the public form on any page — its type and material dropdowns come from your producer profile, so a woodworker's customers pick a Wood Species and a beekeeper's pick a Floral Source with no configuration.

No WooCommerce required. Without it, an accepted commission is one the maker arranges payment for directly.

### WooCommerce (optional)

The compatibility layer. Everything else works with no store at all — a pre-order is paid at the stand, a commission is invoiced by the maker — and this module adds the option of settling either one through WooCommerce checkout instead.

Both request tables gain four columns (`settlement`, `wc_order_id`, `wc_product_id`, `settled_at`), added only when the module runs, so a site that never installs WooCommerce never grows them. `settlement` defaults to `direct`, so switching the module on never reclassifies an existing request.

- **A commission becomes** a hidden product at the quoted price plus a pending order, and the customer gets a pay link. Hidden, not published — "Dana's walnut bowl, $185" is not something another customer should be able to find and buy.
- **A pre-order is priced** from the catalogue. Catalogue prices are free text on purpose ("$4/bunch", "market price"), so any line that does not parse to a clean number **refuses the whole checkout and names the product** rather than guessing — "2 for $5" reads as 2.00, and charging that would undercharge silently.
- **Payment flows one way.** WooCommerce is the authority on whether money arrived; the request follows. A paid commission moves to In Progress through the same transition table everything else uses, so it cannot resurrect a cancelled one. A refund deliberately does *not* cancel the request — that is a human's call.

Loading is gated on the `WooCommerce` class rather than `is_plugin_active()`, which needs an admin include, reads an option that lies on multisite, and would still be true during the request in which WooCommerce is being deactivated.

### Stand Status

Real-time open/closed status for the roadside stand. Admin bar toggle, REST endpoints for status changes, and three blocks:
- **Stand Status Banner** — front-end display with Interactivity API reactive updates and optional polling
- **Stand Quick Toggle** — editor-only control panel for toggling status
- **Stand Hours Schedule** — semantic `<table>` with today highlighted, `aria-current="date"`

### Availability Board

Live product availability display grouped by product type. REST endpoint aggregates data from the availability table into a grouped structure. Includes:
- **Availability Board** — Interactivity API client-side filtering by status and product type, object-map state with full proxy dependency tracking
- Admin quick-entry page with product thumbnails, price display, "Copy Last Week" for fast updates, and mobile-optimized touch targets

### Event Manager

Farm events with RSVP support. Five-layer RSVP security: honeypot, IP rate limiting (salted SHA-256), duplicate detection, party size cap, atomic cap enforcement via `SELECT FOR UPDATE`.
- **Event List** — Interactivity API, inline RSVP form with client-side validation
- **Event Card** — Single event embed with RSVP, cancellation badge

### Notifications

Email notifications to the site admin for key farm events. All notifications are filterable and can be individually suppressed. Listeners for:
- **RSVP added** — guest name, party size, headcount vs cap, "FULL" alert
- **RSVP cancelled** — who dropped, updated headcount
- **Stand status toggled** — styled OPEN/CLOSED confirmation with timestamp
- **Availability expired** — daily summary of purged rows

## Editor Experience

### Sidebar Panels

Each CPT has custom sidebar panels using `PluginDocumentSettingPanel` with `useEntityProp`:

**Location** — Location Details (type, address, hours, Venmo, coordinates, open toggle, status message) + Schedule & Season (date pickers, auto-toggle, visual weekly schedule builder)

**Product** — Product Details (price, unit dropdown with custom option, growing notes) + Sources (search and link source posts)

**Event** — Event Details (date/time pickers, location selector, donation link, validation notice) + RSVP Settings (enable, cap slider, label, close toggle) + Event Info (cost note, what to bring, cancelled toggle)

### Admin Columns

**Products** — price (sortable), availability status badge with color coding

**Events** — start date/time (sortable, default sort ascending), location link, RSVP headcount with cap/closed/full indicators, cancelled badge, past event marker

**Locations** — type (sortable), open/closed pill badge, address

## Block Development

All blocks use the **no-build IIFE** pattern for editor scripts — plain JS using `wp.blocks`, `wp.element`, `wp.blockEditor`, and `wp.components`. No webpack, no `@wordpress/scripts`.

Front-end view scripts use the **Interactivity API** (WP 6.5+) where reactivity is needed, loaded as ES modules via `viewScriptModule` in `block.json`.

### Interactivity API Patterns (WP 6.9)

Lessons learned and patterns established during development:

- **Store state for shared data, context for per-element identifiers.** `wp_interactivity_state()` for filter state; `data-wp-context` only for read-only values like `filterStatus`, `itemStatus`, `groupSlug`.
- **Never declare defaults in `store()` for server-initialized properties.** Client-side defaults overwrite `wp_interactivity_state()` values.
- **Object maps for multi-value filters.** `{ abundant: true, limited: false }` instead of arrays. The proxy tracks individual property flips but not array reassignment.
- **Iterate `allStatuses` array, not `for...in`.** The proxy may not track `for...in` enumeration as a dependency on individual properties.
- **Read ALL properties without `break`.** Early exit skips dependency registration for unread properties.
- **Add `[hidden] { display: none }` overrides** when CSS sets explicit `display` on elements using the `hidden` attribute.

## Accessibility

All blocks follow WCAG 2.1 AA:
- `<section>`/`<article>` landmarks with `aria-label`
- `screen-reader-text` labels, `aria-live="polite"` on dynamic regions
- `aria-pressed` on toggle buttons, `role="toolbar"` on filter groups
- `role="status"` on badges, `focus-visible` outlines
- `prefers-reduced-motion: reduce`, `forced-colors: active` for Windows High Contrast
- New-tab warnings on external links
- Honeypot field with `aria-hidden="true"` and `tabindex="-1"`

## REST API Endpoints

All under `producerkit/v1`. 25 custom endpoints plus standard WP REST for each CPT.

### Core
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/availability` | Public | All current availability |
| POST | `/availability` | Editor+ | Upsert a status row |
| DELETE | `/availability/{id}` | Editor+ | Delete a status row |
| GET | `/products/{id}/sources` | Public | Sources linked to a product |
| GET | `/events/{id}/details` | Public | Event + location + products |
| PATCH | `/locations/{id}/toggle` | Editor+ | Toggle open/closed |

### Commissions
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | `/commissions` | Public | Submit a request |
| GET | `/commissions` | Editor+ | List, optionally by status |
| GET | `/commissions/token/{token}` | Token | The customer's own view |
| POST | `/commissions/quote/{token}/accept` | Token | Accept a quote |
| POST | `/commissions/quote/{token}/decline` | Token | Decline a quote |
| POST | `/commissions/{id}/quote` | Editor+ | Send a quote |
| POST | `/commissions/{id}/status` | Editor+ | Move the status on |

The token routes are POST rather than GET so a link preview or a mail client prefetching the URL cannot accept a quote on the customer's behalf.

### Stand Status
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| PATCH | `/stand/{id}/status` | Editor+ | Toggle + set message |
| GET | `/stand/{id}/info` | Public | Full stand info (polling) |
| GET | `/stands` | Public | List all stand-type locations |

### Availability Board
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/board` | Public | Grouped availability data |
| GET | `/board/last-updated` | Public | Cache-bust timestamp |

### Event Manager
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/events/upcoming` | Public | Future events |
| GET | `/events/past` | Public | Past events |
| POST | `/events/{id}/rsvp` | Public | Submit RSVP (rate limited) |
| DELETE | `/rsvp/{token}` | Public | Cancel via unique token |
| GET | `/events/{id}/rsvps` | Editor+ | RSVP list for an event |

## Abilities (WP 6.9+)

14 abilities across 5 categories, registered via `wp_register_ability()` behind a `function_exists()` guard so the plugin still loads below WordPress 6.9.

## Admin Tools

### Needs Attention Dashboard

The ProducerKit dashboard shows a "Needs Attention" section that flags content gaps: products without photos or prices, events without start dates, locations without addresses, products with stale availability (over a week old), and products not listed on the board at all. Each item links directly to the relevant admin page. The section disappears when everything is filled in.

### Availability Quick-Entry

The weekly availability update page shows product thumbnails, prices, and a "Copy Last Week" button that pre-fills the form from current availability. Status dropdowns and note inputs have larger touch targets for mobile use. On narrow screens, less-critical columns hide automatically.

### Product Import / Export

CSV import and export under **ProducerKit → Product Import**. Export downloads all products with every field. Import creates or updates products matched by title, handles pipe-separated taxonomy terms, resolves source links by title, and optionally sideloads featured images from URLs. A collapsible format reference documents every column.

## Automation

### Availability Expiration Cron

A daily WP-Cron job (`pkit_availability_cleanup`) runs at 3:00 AM and deletes availability rows with an `expires_date` in the past. The board already hides expired rows via date filtering — the cron just cleans up the database. Self-healing: if the cron event is missing (e.g., after a git pull without reactivation), it re-schedules on the next page load.

### Email Notifications

All filterable via `pkit_notify_*` hooks. Recipients default to the site admin email and can be extended via the `pkit_notify_recipients` filter. Individual notifications can be suppressed with `add_filter('pkit_notify_rsvp_added', '__return_false')`.

## Sample Data

The admin dashboard provides a one-click toggle to load 8 products, 2 locations, 3 events, and availability entries. Sample content is tagged with `_pkit_sample_data` meta for clean removal. Front-end shows amber "Sample" badges via `the_title` filter.

## Translations

`languages/producerkit.pot` is committed, so a translator can start from the
repository without a WordPress checkout, and a diff shows which strings a
change added or removed — which is the cheapest way to notice a control that
shipped hard-coded.

Regenerate it whenever user-visible strings change, and after a version bump,
because the version is embedded in the template's `Project-Id-Version` header:

```sh
composer make-pot          # regenerate
composer make-pot:check    # exit 1 if the committed file is stale
```

Both need [wp-cli](https://wp-cli.org). CI runs the check and distinguishes
its two failure modes: strings appearing or vanishing is translation work,
while source references moving because a line shifted is a one-command fix
that loses nothing.

`bin/validate-config.php` separately fails if the template is missing, was
generated for another text domain, or declares a version other than the
plugin's — a stale template still loads and still translates, just without
anything added since, so nothing looks broken until a translator asks where
the new labels went.

## License

GPL-2.0-or-later