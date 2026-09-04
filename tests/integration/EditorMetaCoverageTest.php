<?php
/**
 * Every registered meta field should have somewhere to type it.
 *
 * The pkit_source post type registered four fields, rendered them on the
 * front end, returned them from REST and reported them through an ability —
 * and offered no editor control for any of them. The post type declares
 * custom-fields support, so a determined person could set them through
 * WordPress's raw key/value box knowing the meta keys, which is the absence
 * of a feature rather than one.
 *
 * Nothing caught it because every layer was individually complete. This test
 * is the missing cross-check: for each post type with an editor script, every
 * registered meta key must be mentioned in it, or be listed below with a
 * reason.
 */

declare(strict_types=1);

final class EditorMetaCoverageTest extends WP_UnitTestCase {

	/**
	 * Post type => editor script, mirroring the map in producerkit.php.
	 */
	private const PANELS = [
		'pkit_location' => 'editor-location.js',
		'pkit_product'  => 'editor-product.js',
		'pkit_event'    => 'editor-event.js',
		'pkit_source'   => 'editor-source.js',
	];

	/**
	 * Keys that deliberately have no control, and why.
	 *
	 * A short list with reasons, not a place to bury new gaps: anything added
	 * here should be either genuinely machine-written or tracked.
	 */
	private const NO_CONTROL_EXPECTED = [
		// Stamped by the toggle itself, in both the REST route and the
		// ability. A control would let someone falsify it.
		'_pkit_ss_last_toggled'      => 'written by the stand toggle, never by hand',

		// Both are read by two REST responses and written nowhere — the same
		// shape as the sources bug, tracked separately rather than fixed in
		// the change that found them.
		'_pkit_featured_product_ids' => 'issue #37',
		'_pkit_recurrence_rule'      => 'issue #37 — and nothing interprets the RRULE yet',
	];

	/**
	 * Put the meta registrations back before each test.
	 *
	 * WP_UnitTestCase::set_up() calls reset_post_types(), which unregisters
	 * every non-core post type and drops that type's registered meta with it,
	 * and `init` does not fire again. Without this the registry drains as the
	 * class runs: these assertions passed alone and failed in sequence, and
	 * the coverage test below would have quietly checked an ever-shorter list
	 * of keys rather than failing.
	 *
	 * The registration functions are called directly rather than by re-firing
	 * `init`, which would also re-register every block and trip
	 * WP_Block_Type_Registry's "already registered" notice — which this suite
	 * turns into a failure.
	 */
	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Meta_Fields\register();

		foreach (
			[
				'\ProducerKit\StandStatus\Meta\register',
				'\ProducerKit\EventManager\Meta\register',
			] as $callback
		) {
			// Optional modules; absent on a site that switched them off.
			if ( function_exists( $callback ) ) {
				$callback();
			}
		}
	}

	public function test_the_registry_is_populated(): void {
		// Guards every assertion below: a drained registry would make the
		// coverage test pass by having nothing left to check.
		foreach ( self::PANELS as $post_type => $script ) {
			$keys = array_filter(
				array_keys( get_registered_meta_keys( 'post', $post_type ) ),
				static fn ( string $key ): bool => str_starts_with( $key, '_pkit_' )
			);

			$this->assertNotEmpty( $keys, $post_type . ' has no registered meta, so nothing below is being checked.' );
		}
	}

	public function test_the_panel_map_matches_the_plugin(): void {
		// A drifted copy of the map would make every assertion below check
		// the wrong files.
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/producerkit.php' );

		foreach ( self::PANELS as $post_type => $script ) {
			$this->assertMatchesRegularExpression(
				"/'" . preg_quote( $post_type, '/' ) . "'\s*=>\s*'" . preg_quote( $script, '/' ) . "'/",
				$source,
				$post_type . ' is not enqueued with ' . $script . ' in producerkit.php.'
			);
		}
	}

	public function test_every_editor_script_exists(): void {
		foreach ( self::PANELS as $post_type => $script ) {
			$this->assertFileExists(
				dirname( __DIR__, 2 ) . '/assets/js/' . $script,
				$post_type . ' is enqueued with a script that is not there.'
			);
		}
	}

	public function test_every_registered_meta_key_has_a_control(): void {
		$uncovered = [];

		foreach ( self::PANELS as $post_type => $script ) {
			$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/' . $script );

			foreach ( array_keys( get_registered_meta_keys( 'post', $post_type ) ) as $key ) {
				if ( ! str_starts_with( $key, '_pkit_' ) ) {
					continue;
				}

				if ( isset( self::NO_CONTROL_EXPECTED[ $key ] ) || str_contains( $js, $key ) ) {
					continue;
				}

				$uncovered[] = $post_type . ' / ' . $key;
			}
		}

		$this->assertSame(
			[],
			$uncovered,
			"These meta fields are registered and rendered but have nowhere to be entered:\n"
				. implode( "\n", $uncovered )
		);
	}

	public function test_the_exception_list_does_not_outlive_its_fields(): void {
		// An exception for a key that no longer exists is a stale excuse, and
		// would silently cover a future field that reused the name.
		$registered = [];

		foreach ( array_keys( self::PANELS ) as $post_type ) {
			$registered = array_merge( $registered, array_keys( get_registered_meta_keys( 'post', $post_type ) ) );
		}

		foreach ( array_keys( self::NO_CONTROL_EXPECTED ) as $key ) {
			$this->assertContains(
				$key,
				$registered,
				$key . ' is excused from needing a control but is no longer registered.'
			);
		}
	}

	public function test_the_source_panel_covers_all_four_fields(): void {
		// The fields this test was written for.
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/editor-source.js' );

		foreach (
			[
				'_pkit_source_farm_name',
				'_pkit_source_location',
				'_pkit_source_history',
				'_pkit_milling_notes',
			] as $key
		) {
			$this->assertStringContainsString( $key, $js );
		}
	}
}
