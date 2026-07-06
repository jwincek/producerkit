# Farm Stand Manager

Products, sales locations, real-time availability, stand status, and events for small farms and farm stands — with blocks and Abilities API support.

Single plugin, modular architecture. No build step required.

## Quick Start

1. Clone into `wp-content/plugins/`:
   ```
   git clone https://github.com/jwincek/farm-stand-manager.git
   ```
2. Activate in WordPress admin.
3. Go to **🥕 Farm Stand** in the sidebar.
4. Click **Load Sample Data** to see the blocks in action with realistic test content.
5. Read [`GETTING-STARTED.md`](GETTING-STARTED.md) for the full walkthrough.

## Requirements

- WordPress 6.9+ (Abilities API, Interactivity API directive SSR)
- PHP 8.1+

## Architecture

```
farm-stand-manager/
├── farm-stand-manager.php                 # Bootstrap, module loader, block registration
├── includes/
│   ├── admin-dashboard.php            # Admin dashboard with module status
│   ├── sample-data.php                # Load/remove sample data toggle
│   └── sample-data-markers.php        # Front-end "Sample" badges, admin notices
├── assets/js/
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
│   │       ├── rest-api.php           # REST routes under lfuf/v1
│   │       ├── abilities.php          # WP 6.9+ Abilities API
│   │       ├── single-content.php     # CPT single page meta display
│   │       ├── single-styles.php      # Front-end styles for single CPTs
│   │       ├── admin-columns.php      # Custom columns for CPT list tables
│   │       └── product-import-export.php # CSV import/export for products
│   ├── stand-status/
│   │   ├── bootstrap.php
│   │   └── includes/
│   │       ├── meta-extensions.php    # _lfuf_ss_* meta on locations
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
│           ├── meta-extensions.php    # _lfuf_em_* meta on events
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
│   └── event-card/                    # Single event embed with RSVP
├── languages/
├── GETTING-STARTED.md                 # Walkthrough for farm operators
├── composer.json
├── .editorconfig
└── README.md
```

## Modules

### Core (always active)

The shared data layer. Registers four custom post types (`lfuf_product`, `lfuf_source`, `lfuf_location`, `lfuf_event`), three taxonomies with auto-seeded default terms, a custom `{prefix}_lfuf_availability` table for time-sensitive product status with daily expiration cron, 16 REST API endpoints under `lfuf/v1`, Abilities API abilities for AI/automation discoverability, single CPT page enhancements with structured meta tables, custom admin columns for all CPT list tables, a "Needs Attention" dashboard widget that flags missing content, and CSV import/export for bulk product management.

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

All under `lfuf/v1`. 16 custom endpoints plus standard WP REST for each CPT.

### Core
| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/availability` | Public | All current availability |
| POST | `/availability` | Editor+ | Upsert a status row |
| DELETE | `/availability/{id}` | Editor+ | Delete a status row |
| GET | `/products/{id}/sources` | Public | Sources linked to a product |
| GET | `/events/{id}/details` | Public | Event + location + products |
| PATCH | `/locations/{id}/toggle` | Editor+ | Toggle open/closed |

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

10 abilities across 4 categories, registered via `wp_register_ability()` with `function_exists()` guard for backward compatibility.

## Admin Tools

### Needs Attention Dashboard

The 🥕 Farm Stand dashboard shows a "Needs Attention" section that flags content gaps: products without photos or prices, events without start dates, locations without addresses, products with stale availability (over a week old), and products not listed on the board at all. Each item links directly to the relevant admin page. The section disappears when everything is filled in.

### Availability Quick-Entry

The weekly availability update page shows product thumbnails, prices, and a "Copy Last Week" button that pre-fills the form from current availability. Status dropdowns and note inputs have larger touch targets for mobile use. On narrow screens, less-critical columns hide automatically.

### Product Import / Export

CSV import and export under **🥕 Farm Stand → Product Import**. Export downloads all products with every field. Import creates or updates products matched by title, handles pipe-separated taxonomy terms, resolves source links by title, and optionally sideloads featured images from URLs. A collapsible format reference documents every column.

## Automation

### Availability Expiration Cron

A daily WP-Cron job (`lfuf_availability_cleanup`) runs at 3:00 AM and deletes availability rows with an `expires_date` in the past. The board already hides expired rows via date filtering — the cron just cleans up the database. Self-healing: if the cron event is missing (e.g., after a git pull without reactivation), it re-schedules on the next page load.

### Email Notifications

All filterable via `lfuf_notify_*` hooks. Recipients default to the site admin email and can be extended via the `lfuf_notify_recipients` filter. Individual notifications can be suppressed with `add_filter('lfuf_notify_rsvp_added', '__return_false')`.

## Sample Data

The admin dashboard provides a one-click toggle to load 8 products, 2 locations, 3 events, and availability entries. Sample content is tagged with `_lfuf_sample_data` meta for clean removal. Front-end shows amber "Sample" badges via `the_title` filter.

## License

GPL-2.0-or-later