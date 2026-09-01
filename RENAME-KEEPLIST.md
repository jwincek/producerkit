# ProducerKit rename — frozen identifiers

Enumerated before the rename. Every value below is persisted in the database
or in post content. Renaming any of them breaks existing installs silently.

## Written to the database

| Kind | Values |
|---|---|
| Options | `lfuf_availability_db_version`, `lfuf_preorder_db_version`, `lfuf_rsvp_db_version`, `lfuf_sample_data_loaded` |
| Cron hook | `lfuf_availability_cleanup` |
| Transient | `lfuf_import_results` |
| Tables | `{prefix}lfuf_availability`, `{prefix}lfuf_rsvps`, `{prefix}lfuf_preorders` |
| Post types (`wp_posts.post_type`) | `lfuf_product`, `lfuf_source`, `lfuf_location`, `lfuf_event` |
| Taxonomies (`term_taxonomy.taxonomy`) | `lfuf_product_type`, `lfuf_season`, `lfuf_event_type` |
| Post meta | 37 keys, all `_lfuf_*` |

## Written to post content

| Kind | Values |
|---|---|
| Block names | `lfuf/product-card`, `lfuf/availability-badge`, `lfuf/location-info`, `lfuf/stand-status-banner`, `lfuf/stand-toggle`, `lfuf/stand-hours-schedule`, `lfuf/availability-board`, `lfuf/event-list`, `lfuf/event-card`, `lfuf/preorder-form` |

## Kept deliberately (not persisted, but public API)

`lfuf` stays as the internal prefix. It is already independent of the
marketing name, and every stored identifier above is frozen at `lfuf_*`
anyway — introducing a second prefix would leave the codebase permanently
split between two conventions.

- 21 filter/action hooks, all `lfuf_*`
- REST namespace `lfuf/v1`

## Changing

Public identity only, plus the namespace (separate commit):

- Plugin Name, Plugin URI, directory, main file → `producerkit`
- Text Domain `farm-stand-manager` → `producerkit` (WordPress.org requires
  text domain == slug; the tooling derives the slug from the main file's
  basename)
- `block.json` `textdomain` field (not persisted — safe)
- 14 ability names `farm-stand-manager/*` → `producerkit/*` (not persisted)
- Namespace `Leftfield` → `ProducerKit` — "Leftfield" names one specific
  farm and is actively misleading for a plugin serving makers and beekeepers
