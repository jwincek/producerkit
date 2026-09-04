<?php
/**
 * What this trade calls a quote-then-make job.
 *
 * "Commission" is right for a potter and wrong for a beekeeper, who answers
 * an enquiry about bulk honey. These tests hold two lines: that the wording
 * follows the active profile, and that nothing but the wording moved — the
 * type string, the table, the routes, the hooks and the ability names are
 * identifiers other people build against.
 */

declare(strict_types=1);

use ProducerKit\Commissions\Vocabulary;

final class RequestVocabularyTest extends WP_UnitTestCase {

	private function use_profile( string ...$slugs ): void {
		update_option( 'pkit_producer_profile', $slugs );
	}

	public function tear_down(): void {
		delete_option( 'pkit_producer_profile' );
		parent::tear_down();
	}

	public function test_a_craft_profile_keeps_the_craft_word(): void {
		$this->use_profile( 'pottery' );

		$this->assertSame( 'Commission', Vocabulary\singular() );
		$this->assertSame( 'Commissions', Vocabulary\plural() );
	}

	public function test_a_beekeeper_answers_enquiries(): void {
		$this->use_profile( 'beekeeping' );

		$this->assertSame( 'Enquiry', Vocabulary\singular() );
		$this->assertSame( 'Enquiries', Vocabulary\plural() );
		$this->assertSame( 'Ask about bulk orders', Vocabulary\action() );
	}

	public function test_a_grower_takes_special_orders(): void {
		$this->use_profile( 'farm' );

		$this->assertSame( 'Special Order', Vocabulary\singular() );
	}

	public function test_an_unset_option_uses_the_default_profile_not_the_default_word(): void {
		// DEFAULT_SLUG is 'farm', a holdover from when the plugin only served
		// one trade. So a site that has never chosen gets the farm wording,
		// not the bare fallback inside words() — which is only reached when no
		// profile declares request_names at all.
		delete_option( 'pkit_producer_profile' );

		$this->assertSame( 'Special Order', Vocabulary\singular() );
	}

	public function test_a_profile_without_an_override_gets_the_bare_default(): void {
		$this->use_profile( 'general' );

		$this->assertSame( 'Commission', Vocabulary\singular() );
		$this->assertSame( 'Commission a piece', Vocabulary\action() );
	}

	public function test_the_labelling_profile_decides_on_a_multi_profile_site(): void {
		// The same rule every other name on the site already follows.
		$this->use_profile( 'beekeeping', 'pottery' );
		$this->assertSame( 'Enquiries', Vocabulary\plural() );

		$this->use_profile( 'pottery', 'beekeeping' );
		$this->assertSame( 'Commissions', Vocabulary\plural() );
	}

	public function test_mid_sentence_forms_are_lowercased(): void {
		$this->use_profile( 'farm' );

		$this->assertSame( 'special order', Vocabulary\singular_lower() );
		$this->assertSame( 'special orders', Vocabulary\plural_lower() );
	}

	public function test_a_blank_slot_keeps_its_default(): void {
		add_filter(
			'pkit_commission_names',
			static fn (): array => [
				'singular' => '',
				'plural'   => '   ',
			],
			99
		);

		$this->assertSame( 'Commission', Vocabulary\singular(), 'An empty override must not render an empty label.' );
		$this->assertSame( 'Commissions', Vocabulary\plural() );
		$this->assertSame( 'Commission a piece', Vocabulary\action(), 'A slot the filter omitted keeps its default.' );
	}

	public function test_the_returned_shape_is_exactly_the_documented_slots(): void {
		add_filter(
			'pkit_commission_names',
			static fn ( array $w ): array => $w + [ 'invented' => 'nonsense' ],
			99
		);

		$this->assertSame(
			[ 'singular', 'plural', 'menu', 'action' ],
			array_keys( Vocabulary\words() ),
			'Callers index this array directly, so the promised shape must be the actual one.'
		);
	}

	/* ── Nothing but the wording moved ──────────────────── */

	public function test_the_ability_roster_is_unchanged(): void {
		// Wording is not identity. These names are what an agent calls.
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/modules/commissions/includes/abilities.php'
		);

		foreach (
			[
				'producerkit/list-commissions',
				'producerkit/count-commissions-by-status',
				'producerkit/send-commission-quote',
				'producerkit/update-commission-status',
			] as $ability
		) {
			$this->assertStringContainsString( "'" . $ability . "'", $source );
		}
	}

	public function test_the_request_type_string_is_unchanged(): void {
		// Settlement rows, order meta and the pkit_request_settled hook all
		// carry this literal. Re-wording it would strand every existing row.
		$this->assertStringContainsString(
			"'commission'",
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/modules/woocommerce/includes/settlement.php' )
		);
	}

	public function test_the_block_keeps_its_name(): void {
		$block = json_decode(
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/commission-form/block.json' ),
			true
		);

		$this->assertSame(
			'producerkit/commission-form',
			$block['name'],
			'A renamed block orphans it in every post that already uses it.'
		);
	}
}
