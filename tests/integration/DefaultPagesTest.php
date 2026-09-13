<?php
/**
 * Generating the default page set.
 *
 * The rules that matter are the ones about restraint: drafts rather than
 * published, and never touching a page the producer already has.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\DefaultPages;


class DefaultPagesTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// reset_post_types() runs in the parent and unregisters everything.
		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
	}

	private function make_location( string $type = 'stand' ): int {
		$id = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
				'post_title'  => 'The Stand',
			]
		);

		update_post_meta( $id, '_pkit_location_type', $type );

		return $id;
	}

	/**
	 * Published pages on a live site is the mistake #68 was about. A whole page
	 * set is a larger version of it.
	 */
	public function test_pages_are_created_as_drafts(): void {
		$result = DefaultPages\generate();

		$this->assertNotEmpty( $result['created'] );

		foreach ( $result['created'] as $page ) {
			$this->assertSame(
				'draft',
				get_post_status( $page['id'] ),
				"{$page['title']} was published rather than drafted."
			);
		}
	}

	/**
	 * Re-running fills gaps; it never duplicates or overwrites.
	 */
	public function test_rerunning_creates_nothing_and_overwrites_nothing(): void {
		$first = DefaultPages\generate();
		$this->assertNotEmpty( $first['created'] );

		$second = DefaultPages\generate();

		$this->assertSame( [], $second['created'], 'A second run created pages again.' );
		$this->assertCount( count( $first['created'] ), $second['skipped'] );
	}

	/**
	 * A page the producer already wrote is left exactly as it was.
	 */
	public function test_an_existing_page_is_never_touched(): void {
		$mine = (int) self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'My Own Visit Page',
				'post_name'    => 'visit',
				'post_content' => 'Hand-written, do not touch.',
			]
		);

		$this->make_location();

		DefaultPages\generate();

		$after = get_post( $mine );

		$this->assertSame( 'Hand-written, do not touch.', $after->post_content );
		$this->assertSame( 'publish', $after->post_status );
		$this->assertSame( 'My Own Visit Page', $after->post_title );
	}

	/**
	 * Blocks have to arrive pointed at the site's own location — a block
	 * rendering an empty box teaches nothing.
	 */
	public function test_blocks_are_configured_with_the_location(): void {
		$location = $this->make_location();

		DefaultPages\generate();

		$visit = get_page_by_path( 'visit', OBJECT, 'page' );

		$this->assertInstanceOf( \WP_Post::class, $visit );
		$this->assertStringContainsString(
			'"locationId":' . $location,
			$visit->post_content,
			'The Visit page did not carry the location id.'
		);
	}

	/**
	 * A retailer that carries your goods is not where someone visits you.
	 */
	public function test_a_retailer_is_not_used_as_the_visit_location(): void {
		$retailer = $this->make_location( 'retailer' );
		$own      = $this->make_location( 'stand' );

		$this->assertSame( $own, DefaultPages\primary_location_id() );
		$this->assertNotSame( $retailer, DefaultPages\primary_location_id() );
	}

	/**
	 * With nowhere to visit, the Visit page is not invented.
	 */
	public function test_no_visit_page_without_a_location(): void {
		$slugs = wp_list_pluck( DefaultPages\planned(), 'slug' );

		$this->assertNotContains( 'visit', $slugs );

		$this->make_location();

		$this->assertContains( 'visit', wp_list_pluck( DefaultPages\planned(), 'slug' ) );
	}

	/**
	 * Titles follow the trade, so a musician is not sent to a "Calendar".
	 */
	public function test_titles_follow_the_trade(): void {
		$events = get_post_type_object( 'pkit_event' )->labels->menu_name;

		$this->assertContains(
			$events,
			wp_list_pluck( DefaultPages\planned(), 'title' ),
			'The events page is not named for this trade.'
		);
	}

	/**
	 * Every block written must be one that is actually registered, or the page
	 * renders an "invalid block" warning.
	 */
	public function test_every_block_written_exists(): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( DefaultPages\planned( 1 ) as $page ) {
			preg_match_all( '/<!-- wp:(producerkit\/[a-z-]+)/', $page['content'], $m );

			$this->assertNotEmpty( $m[1], "{$page['title']} has no blocks." );

			foreach ( $m[1] as $block_name ) {
				$this->assertNotNull(
					$registry->get_registered( $block_name ),
					"{$page['title']} uses {$block_name}, which is not registered."
				);
			}
		}
	}

	/**
	 * The content has to parse back into real blocks, not a wall of text.
	 */
	public function test_generated_content_parses_as_blocks(): void {
		foreach ( DefaultPages\planned( 1 ) as $page ) {
			$blocks = array_values(
				array_filter(
					parse_blocks( $page['content'] ),
					static fn ( array $b ): bool => ! empty( $b['blockName'] )
				)
			);

			$this->assertNotEmpty( $blocks, "{$page['title']} did not parse into blocks." );

			foreach ( $blocks as $b ) {
				$this->assertStringStartsWith( 'producerkit/', (string) $b['blockName'] );
			}
		}
	}

	/**
	 * Every generated page must render something.
	 *
	 * The pre-order form bails out entirely when nothing is orderable, so on a
	 * fresh install its page came out blank — the state this button exists to
	 * serve. A page that renders nothing is worse than a page that is missing.
	 */
	public function test_no_generated_page_renders_empty(): void {
		$this->make_location();

		foreach ( DefaultPages\planned() as $page ) {
			$this->assertNotSame(
				'',
				trim( do_blocks( $page['content'] ) ),
				"The {$page['title']} page would render as a blank page."
			);
		}
	}

	/**
	 * And once there is something to order, the page appears.
	 */
	public function test_the_preorder_page_waits_for_a_product(): void {
		if ( ! \ProducerKit\is_module_active( 'pre-order' ) ) {
			$this->markTestSkipped( 'Pre-order module is not active.' );
		}

		$this->assertNotContains( 'pre-orders', wp_list_pluck( DefaultPages\planned(), 'slug' ) );

		self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);

		$this->assertContains( 'pre-orders', wp_list_pluck( DefaultPages\planned(), 'slug' ) );
	}

	/**
	 * Generated pages are identifiable afterwards.
	 */
	public function test_generated_pages_are_marked(): void {
		$result = DefaultPages\generate();

		foreach ( $result['created'] as $page ) {
			$this->assertSame( '1', get_post_meta( $page['id'], DefaultPages\GENERATED_META, true ) );
		}
	}
}
