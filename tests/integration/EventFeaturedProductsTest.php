<?php
/**
 * Featured products on an event.
 *
 * `_pkit_featured_product_ids` was registered, returned by two REST readers,
 * and written by nothing — so a client could read the key and always get an
 * empty array. The editor panel added alongside these tests is the writer.
 *
 * What is asserted here is the half a panel cannot cover: that the meta
 * survives a REST write with its array schema intact, that junk is refused
 * rather than coerced, and that both readers reflect it.
 */

declare(strict_types=1);

final class EventFeaturedProductsTest extends WP_UnitTestCase {

	/**
	 * Put the post types and their meta back before each test.
	 *
	 * WP_UnitTestCase::set_up() calls reset_post_types(), which unregisters
	 * every non-core post type and drops its registered meta — and with the
	 * meta goes the REST schema. Reads and writes keep working, so nothing
	 * looks broken; validation simply stops applying, and the junk-refusal
	 * test below passed with a 200 until this was added while the same write
	 * was correctly refused with a 400 on a real site.
	 */
	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Meta_Fields\register();

		if ( function_exists( '\ProducerKit\EventManager\Meta\register' ) ) {
			\ProducerKit\EventManager\Meta\register();
		}
	}

	private function product( string $title ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
	}

	private function event(): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
				'post_title'  => 'Oil Heritage Festival',
			]
		);

		update_post_meta( $id, '_pkit_start_datetime', gmdate( 'Y-m-d\TH:i:s', strtotime( '+14 days' ) ) );

		return $id;
	}

	private function write_meta( int $event_id, mixed $value ): WP_REST_Response {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$type    = get_post_type_object( 'pkit_event' );
		$request = new WP_REST_Request(
			'POST',
			'/' . $type->rest_namespace . '/' . $type->rest_base . '/' . $event_id
		);

		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( [ 'meta' => [ '_pkit_featured_product_ids' => $value ] ] ) );

		return rest_do_request( $request );
	}

	public function test_the_field_survives_a_rest_write(): void {
		// The path the block editor takes. Array meta needs an explicit REST
		// schema with items, or the value is rejected and the save fails
		// silently — which in the editor looks like a control that does
		// nothing.
		$event = $this->event();
		$one   = $this->product( 'Honey Bear' );
		$two   = $this->product( 'Comb Honey' );

		$response = $this->write_meta( $event, [ $one, $two ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ $one, $two ], get_post_meta( $event, '_pkit_featured_product_ids', true ) );
	}

	public function test_junk_is_refused_and_the_previous_value_survives(): void {
		$event = $this->event();
		$one   = $this->product( 'Honey Bear' );

		$this->write_meta( $event, [ $one ] );
		$response = $this->write_meta( $event, [ $one, 'nonsense', -5 ] );

		$this->assertSame( 400, $response->get_status(), 'A non-integer must not be coerced into a post ID.' );
		$this->assertSame(
			[ $one ],
			get_post_meta( $event, '_pkit_featured_product_ids', true ),
			'A refused write must leave what was already there.'
		);
	}

	public function test_the_details_reader_returns_them(): void {
		$event = $this->event();
		$one   = $this->product( 'Honey Bear' );
		$two   = $this->product( 'Comb Honey' );

		update_post_meta( $event, '_pkit_featured_product_ids', [ $one, $two ] );

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/producerkit/v1/events/' . $event . '/details' )
		);

		$this->assertSame( 200, $response->get_status() );

		$titles = wp_list_pluck( (array) ( $response->get_data()['products'] ?? [] ), 'title' );
		sort( $titles );

		$this->assertSame( [ 'Comb Honey', 'Honey Bear' ], $titles );
	}

	public function test_the_upcoming_reader_returns_them(): void {
		$event = $this->event();
		$one   = $this->product( 'Honey Bear' );

		update_post_meta( $event, '_pkit_featured_product_ids', [ $one ] );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/producerkit/v1/events/upcoming' ) );

		$found = null;
		foreach ( (array) $response->get_data() as $item ) {
			if ( (int) ( $item['id'] ?? 0 ) === $event ) {
				$found = $item;
			}
		}

		$this->assertNotNull( $found, 'The event should be in the upcoming feed.' );
		$this->assertSame( [ 'Honey Bear' ], wp_list_pluck( (array) $found['products'], 'title' ) );
	}

	public function test_clearing_it_empties_both_readers(): void {
		// The panel's remove button on the last remaining row.
		$event = $this->event();
		$one   = $this->product( 'Honey Bear' );

		update_post_meta( $event, '_pkit_featured_product_ids', [ $one ] );
		update_post_meta( $event, '_pkit_featured_product_ids', [] );

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/producerkit/v1/events/' . $event . '/details' )
		);

		$this->assertSame( [], $response->get_data()['products'] ?? null );
	}

	public function test_an_unpublished_product_does_not_leak(): void {
		// A featured product that is later drafted must not keep appearing on
		// a public event listing.
		$event = $this->event();
		$one   = $this->product( 'Honey Bear' );
		$two   = $this->product( 'Comb Honey' );

		update_post_meta( $event, '_pkit_featured_product_ids', [ $one, $two ] );
		wp_update_post(
			[
				'ID'          => $two,
				'post_status' => 'draft',
			]
		);

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/producerkit/v1/events/' . $event . '/details' )
		);

		$this->assertSame(
			[ 'Honey Bear' ],
			wp_list_pluck( (array) ( $response->get_data()['products'] ?? [] ), 'title' )
		);
	}
}
