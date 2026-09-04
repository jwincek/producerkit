<?php
/**
 * Trade-specific field wording.
 *
 * Taxonomies and post types have re-labelled themselves per profile since
 * multi-profile support landed. Post meta did not, so a musician cataloguing
 * a release read "Farm / Origin Name" and "Milling / Process Notes".
 *
 * The data model was already right — who it came from, where, and what was
 * done in between are the right three questions for a record label, a tannery
 * or a mill. Only the words were wrong, which is why the meta keys are
 * untouched and asserted to stay that way.
 */

declare(strict_types=1);

use ProducerKit\Core\MetaLabels;

final class MetaLabelsTest extends WP_UnitTestCase {

	private function use_profile( string ...$slugs ): void {
		update_option( 'pkit_producer_profile', $slugs );
	}

	public function tear_down(): void {
		delete_option( 'pkit_producer_profile' );
		parent::tear_down();
	}

	public function test_the_farm_wording_is_the_fallback(): void {
		$this->use_profile( 'general' );

		$this->assertSame( 'Farm / Origin Name', MetaLabels\label( '_pkit_source_farm_name' ) );
		$this->assertSame( 'Milling / Process Notes', MetaLabels\label( '_pkit_milling_notes' ) );
	}

	public function test_a_beekeeper_reads_apiary_and_extraction(): void {
		$this->use_profile( 'beekeeping' );

		$this->assertSame( 'Apiary', MetaLabels\label( '_pkit_source_farm_name' ) );
		$this->assertSame( 'Extraction Notes', MetaLabels\label( '_pkit_milling_notes' ) );
	}

	public function test_the_issues_own_example(): void {
		// "a musician editing a release still sees Source with Farm Name …
		// when what they mean is Label, with a studio … and mastering notes."
		$this->use_profile( 'musician' );

		$this->assertSame( 'Label', MetaLabels\label( '_pkit_source_farm_name' ) );
		$this->assertSame( 'Studio', MetaLabels\label( '_pkit_source_location' ) );
		$this->assertSame( 'Mastering Notes', MetaLabels\label( '_pkit_milling_notes' ) );
	}

	public function test_help_text_follows_the_label(): void {
		// Re-labelling a field and leaving the sentence underneath talking
		// about grind and cure is the same mistake one line further down.
		$this->use_profile( 'musician' );
		$this->assertStringContainsString( 'mastering', MetaLabels\help( '_pkit_milling_notes' ) );

		$this->use_profile( 'beekeeping' );
		$this->assertStringContainsString( 'spin', MetaLabels\help( '_pkit_milling_notes' ) );
	}

	public function test_a_profile_may_override_only_some_fields(): void {
		// Bakery re-words three and leaves History alone.
		$this->use_profile( 'bakery' );

		$this->assertSame( 'Mill / Farm', MetaLabels\label( '_pkit_source_farm_name' ) );
		$this->assertSame( 'History', MetaLabels\label( '_pkit_source_history' ) );
	}

	public function test_the_labelling_profile_decides(): void {
		$this->use_profile( 'beekeeping', 'pottery' );
		$this->assertSame( 'Apiary', MetaLabels\label( '_pkit_source_farm_name' ) );

		$this->use_profile( 'pottery', 'beekeeping' );
		$this->assertSame( 'Clay Supplier', MetaLabels\label( '_pkit_source_farm_name' ) );
	}

	public function test_a_blank_override_keeps_the_default(): void {
		add_filter(
			'pkit_meta_labels',
			static fn (): array => [ '_pkit_source_farm_name' => [ '', '' ] ],
			99
		);

		$this->assertSame( 'Farm / Origin Name', MetaLabels\label( '_pkit_source_farm_name' ) );
		$this->assertNotSame( '', MetaLabels\help( '_pkit_source_farm_name' ) );
	}

	public function test_a_half_declared_pair_keeps_the_other_half(): void {
		add_filter(
			'pkit_meta_labels',
			static fn (): array => [ '_pkit_milling_notes' => [ 'Curing Notes' ] ],
			99
		);

		$this->assertSame( 'Curing Notes', MetaLabels\label( '_pkit_milling_notes' ) );
		$this->assertStringContainsString(
			'grind',
			MetaLabels\help( '_pkit_milling_notes' ),
			'Overriding a label must not blank the sentence under it.'
		);
	}

	public function test_the_returned_shape_is_exactly_the_known_keys(): void {
		add_filter(
			'pkit_meta_labels',
			static fn ( array $l ): array => $l + [ 'invented' => [ 'x', 'y' ] ],
			99
		);

		$this->assertSame(
			[
				'_pkit_source_farm_name',
				'_pkit_source_location',
				'_pkit_source_history',
				'_pkit_milling_notes',
				'_pkit_growing_notes',
			],
			array_keys( MetaLabels\labels() )
		);
	}

	public function test_the_editor_payload_is_flat_pairs(): void {
		$this->use_profile( 'beekeeping' );

		$editor = MetaLabels\for_editor();

		$this->assertSame( 'Apiary', $editor['_pkit_source_farm_name']['label'] );
		$this->assertArrayHasKey( 'help', $editor['_pkit_source_farm_name'] );
	}

	/* ── Only the words moved ───────────────────────────── */

	public function test_the_meta_keys_are_untouched(): void {
		// The whole reason this is a labelling change: these are in the
		// database, and every profile reads and writes the same ones.
		$this->use_profile( 'musician' );

		$id = self::factory()->post->create( [ 'post_type' => 'pkit_source' ] );
		update_post_meta( $id, '_pkit_source_farm_name', 'Tiny Global' );

		$this->assertSame( 'Tiny Global', get_post_meta( $id, '_pkit_source_farm_name', true ) );

		$this->use_profile( 'beekeeping' );
		$this->assertSame(
			'Tiny Global',
			get_post_meta( $id, '_pkit_source_farm_name', true ),
			'Switching profiles must not strand data under a different key.'
		);
	}

	public function test_both_surfaces_use_the_same_source(): void {
		// The template said "Farm" while the sidebar said "Farm / Origin
		// Name" for the same field. One source now, so they cannot drift.
		$template = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/modules/core/includes/single-content.php'
		);
		$editor   = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/editor-source.js' );

		// Whitespace-insensitive, and reported as a short list: prettier may
		// put an argument on its own line, and asserting against the whole
		// file prints the whole file on failure.
		$squashed = (string) preg_replace( '/\s+/', '', $editor );
		$missing  = [];

		foreach (
			[
				'_pkit_source_farm_name',
				'_pkit_source_location',
				'_pkit_source_history',
				'_pkit_milling_notes',
			] as $key
		) {
			if ( ! str_contains( $template, "MetaLabels\\label( '" . $key . "' )" ) ) {
				$missing[] = 'template: ' . $key;
			}

			if ( ! str_contains( $squashed, "fieldText('" . $key . "'" ) ) {
				$missing[] = 'editor: ' . $key;
			}
		}

		$this->assertSame( [], $missing, implode( "\n", $missing ) );
	}
}
