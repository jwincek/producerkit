# Frozen identifiers

Everything below is written to the database or into post content. Renaming any
of it breaks existing installs silently — no error, no failing test, just data
that stops being found.

There is no way to change these once the plugin ships. Adding is free;
renaming is not.

## Written to the database

| Kind | Values |
|---|---|
| Options | `pkit_availability_db_version`, `pkit_preorder_db_version`, `pkit_rsvp_db_version`, `pkit_commissions_db_version`, `pkit_sample_data_loaded`, `pkit_producer_profile` |
| User meta | `pkit_producer_profile` |
| Cron hook | `pkit_availability_cleanup` |
| Transients | `pkit_import_results`, `pkit_preorder_rate_*`, `pkit_rsvp_rate_*`, `pkit_commission_rate_*` |
| Tables | `{prefix}pkit_availability`, `{prefix}pkit_rsvps`, `{prefix}pkit_preorders`, `{prefix}pkit_commissions` |
| Post types (`wp_posts.post_type`) | `pkit_product`, `pkit_source`, `pkit_location`, `pkit_event` |
| Taxonomies (`term_taxonomy.taxonomy`) | `pkit_product_type`, `pkit_season`, `pkit_event_type`, `pkit_material`, `pkit_finish`, `pkit_component` |
| Post meta | 37 keys, all `_pkit_*` |

## Written to post content

| Kind | Values |
|---|---|
| Block names | the eleven `producerkit/*` blocks |

## Public contract, not persisted

Renameable in principle, but consumers break. Treat as frozen without a reason:

- REST namespace `producerkit/v1`
- 14 ability names `producerkit/*`
- ~25 filter and action hooks, all `pkit_*`
- Interactivity store namespaces `producerkit/*` — emitted at render, so these
  are the cheapest of the group to change

## History

Two renames, and why the second could do what the first could not.

**2026-09-01, `farm-stand-manager` → `producerkit`.** Public identity only:
name, slug, directory, main file, text domain, ability names, artwork. The
internal prefix stayed `lfuf` — short for Leftfield Urban Farm, the one site
this began as — because at that point every identifier above held live data
and renaming would have orphaned it.

**Later the same day, `lfuf` → `pkit`.** A row count found the install
genuinely empty: zero posts of all four types, zero rows in all four tables,
zero `_lfuf_*` meta, zero posts containing a block. The keep-list protected
nothing, so it was discarded and the prefix renamed throughout — 128
identifiers across 129 files. Tables were dropped and recreated rather than
migrated.

That window is now closed. The table above is the real one.
