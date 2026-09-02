# Tests

Two suites, mirroring the plugin's module layout:

- **Unit** (`composer test:unit`) — pure helpers (price parsing, schema
  mapping, payment URL building) with WP-function stubs. No database,
  no WordPress; runs in milliseconds.
- **Integration** (`composer test:integration`) — everything else, on
  the WordPress PHPUnit test library: one test class per feature area
  (abilities, payment methods, meta sanitizers, availability table,
  pre-orders, pickup dates, harvest list, structured data). Each test
  runs in a rolled-back transaction, so nothing leaks between tests.

`composer test` runs both.

## One-time setup

```sh
composer install
bash bin/install-wp-tests.sh wordpress_test <db-user> <db-pass> <db-host> <wp-version>
```

The test library installs to `$TMPDIR/wordpress-tests-lib` (override
with `WP_TESTS_DIR`) and is wiped on reboot — rerun the install script
if the integration suite reports "WordPress test library not found".

**Local by Flywheel:** MySQL listens on a per-site socket, not
localhost. Find it and pass it as the host:

```sh
SOCK=$(find ~/Library/Application\ Support/Local/run -name mysqld.sock | head -1)
bash bin/install-wp-tests.sh wordpress_test root root "localhost:${SOCK}" 7.0
```

(If multiple Local sites are running, pick the socket whose run-id
matches this site in Local's `sites.json`.)

## Conventions

- Integration tests raise the pre-order rate limit via the
  `pkit_preorder_rate_limit` filter when a test legitimately creates
  several orders; the rate-limit test itself uses the default.
- Don't assert on block markup details beyond the load-bearing bits
  (data attributes, escaping) — markup is free to change.
- The `AbilitiesTest` class executes every read-only ability and lets
  `WP_Ability::execute()` validate output against the declared schemas;
  it is the early-warning system for core Abilities API drift.
