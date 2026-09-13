<?php
/**
 * Attribution when two businesses share one site (#23).
 *
 * The interesting cases are all about *whose* words get used and *when* the
 * byline appears at all, rather than about storing a name.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\Core\Producers;
use ProducerKit\ProducerProfiles\Profiles;

class ProducerBylineTest extends WP_UnitTestCase {

	private int $grower;
	private int $baker;

	public function set_up(): void {
		parent::set_up();

		// reset_post_types() runs in the parent and unregisters everything,
		// taking registered meta and REST schemas with it.
		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();

		$this->grower = self::factory()->user->create(
			[
				'role'         => 'editor',
				'display_name' => 'Jamie',
			]
		);
		$this->baker  = self::factory()->user->create(
			[
				'role'         => 'editor',
				'display_name' => 'Robin',
			]
		);

		update_user_meta( $this->grower, Producers\USER_META, 'Example Farm' );
		update_user_meta( $this->baker, Producers\USER_META, 'Example Bakery' );

		update_user_meta( $this->grower, Profiles\USER_META, 'farm' );
		update_user_meta( $this->baker, Profiles\USER_META, 'bakery' );

		update_option( Profiles\OPTION, [ 'farm', 'bakery' ] );
	}

	private function product_by( int $author ): int {
		return (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_author' => $author,
			]
		);
	}

	/**
	 * The point of the issue: the loaf and the lettuce say different things.
	 */
	public function test_each_business_gets_its_own_byline(): void {
		$lettuce = $this->product_by( $this->grower );
		$loaf    = $this->product_by( $this->baker );

		$this->assertSame( 'Grown by Example Farm', Producers\byline_for( $lettuce ) );
		$this->assertSame( 'Baked by Example Bakery', Producers\byline_for( $loaf ) );
	}

	/**
	 * The trap this design exists to avoid.
	 *
	 * Every other vocabulary filter resolves through labelling_slug(), which
	 * answers "what words does the person READING this want" and falls back to
	 * the first active profile. Used here it would label the bread "Grown by"
	 * for a logged-out visitor, and would change wording depending on who
	 * logged in last.
	 */
	public function test_wording_follows_the_author_not_the_reader(): void {
		$this->product_by( $this->grower );
		$loaf = $this->product_by( $this->baker );

		// A grower is reading the site.
		wp_set_current_user( $this->grower );
		$this->assertSame( 'Baked by Example Bakery', Producers\byline_for( $loaf ) );

		// Nobody is.
		wp_set_current_user( 0 );
		$this->assertSame( 'Baked by Example Bakery', Producers\byline_for( $loaf ) );
	}

	/**
	 * A single-producer site — most of them — sees nothing.
	 */
	public function test_no_byline_when_one_producer_holds_the_whole_catalogue(): void {
		$this->product_by( $this->grower );
		$only = $this->product_by( $this->grower );

		$this->assertSame( 1, Producers\count_on_site() );
		$this->assertFalse( Producers\should_show_byline() );
		$this->assertSame( '', Producers\byline_for( $only ) );
	}

	/**
	 * Two profiles that happen to be the same trade still attribute apart.
	 *
	 * This is the case that would break if attribution were derived from the
	 * producer profile rather than from post_author.
	 */
	public function test_two_producers_of_the_same_trade_are_distinguishable(): void {
		update_user_meta( $this->baker, Profiles\USER_META, 'farm' );
		update_option( Profiles\OPTION, [ 'farm' ] );

		$mine   = $this->product_by( $this->grower );
		$theirs = $this->product_by( $this->baker );

		$this->assertSame( 'Grown by Example Farm', Producers\byline_for( $mine ) );
		$this->assertSame( 'Grown by Example Bakery', Producers\byline_for( $theirs ) );
	}

	/**
	 * An event is hosted, not grown — whatever the trade.
	 */
	public function test_events_use_a_neutral_verb(): void {
		$this->product_by( $this->baker );

		$event = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
				'post_author' => $this->grower,
			]
		);

		$this->assertSame( 'Hosted by Example Farm', Producers\byline_for( $event ) );
	}

	/**
	 * Never filled in, so fall back to the display name rather than to blank.
	 */
	public function test_display_name_is_the_fallback(): void {
		delete_user_meta( $this->grower, Producers\USER_META );

		$this->product_by( $this->baker );
		$lettuce = $this->product_by( $this->grower );

		$this->assertSame( 'Grown by Jamie', Producers\byline_for( $lettuce ) );
	}

	/**
	 * Structured data does NOT take that fallback.
	 */
	public function test_structured_data_omits_brand_without_a_declared_name(): void {
		delete_user_meta( $this->grower, Producers\USER_META );

		$declared = $this->product_by( $this->baker );
		$bare     = $this->product_by( $this->grower );

		$with = \ProducerKit\Core\StructuredData\product_data( get_post( $declared ) );
		$this->assertSame( 'Example Bakery', $with['brand']['name'] );

		$without = \ProducerKit\Core\StructuredData\product_data( get_post( $bare ) );
		$this->assertArrayNotHasKey( 'brand', $without );
	}

	/**
	 * The CPTs have to declare author support, or the column is populated with
	 * no way to see or reassign it.
	 */
	public function test_bylined_post_types_support_authors(): void {
		foreach ( Producers\bylined_post_types() as $type ) {
			$this->assertTrue(
				post_type_supports( $type, 'author' ),
				"{$type} does not support authors, so a producer cannot be set or changed."
			);
		}
	}

	/**
	 * A filter cannot blank a slot or invent one.
	 *
	 * A blanked slot falls back to the neutral default rather than to the
	 * profile's word, because the profile IS a filter — by the time the shape
	 * is rebuilt there is no way to tell which filter set what. Same semantics
	 * as MetaLabels\labels() and Commissions\Vocabulary\words().
	 */
	public function test_wording_filter_cannot_break_the_shape(): void {
		add_filter(
			'pkit_producer_words',
			static fn (): array => [
				'byline'   => '',
				'invented' => 'nope',
			]
		);

		$words = Producers\words_for_user( $this->grower );

		$this->assertSame( 'Made by', $words['byline'], 'A blanked slot should fall back to the default.' );
		$this->assertArrayHasKey( 'name_label', $words );
		$this->assertArrayNotHasKey( 'invented', $words );
	}
}
