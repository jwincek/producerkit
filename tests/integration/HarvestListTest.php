<?php
/**
 * Harvest list aggregation: per-date/location totals from active orders.
 */

declare(strict_types=1);

use function ProducerKit\PreOrder\Orders\cancel_order_by_token;
use function ProducerKit\PreOrder\Orders\create_order;
use function ProducerKit\PreOrder\Orders\get_harvest_list;

final class HarvestListTest extends WP_UnitTestCase {

	private int $kale;
	private int $bread;
	private int $location;
	private string $day1;
	private string $day2;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pkit_preorder_rate_limit', fn () => 100 );

		$this->kale = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => 'Kale',
			]
		);
		update_post_meta( $this->kale, '_pkit_unit', 'bunch' );
		$this->bread    = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => 'Bread',
			]
		);
		$this->location = self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
				'post_title'  => 'Stand',
			]
		);

		$this->day1 = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +2 days' ) );
		$this->day2 = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +3 days' ) );
	}

	private function seed_orders(): void {
		create_order(
			[
				'name'        => 'A',
				'pickup_date' => $this->day1,
				'location_id' => $this->location,
				'items'       => [
					[
						'product_id' => $this->kale,
						'qty'        => 3,
					],
					[
						'product_id' => $this->bread,
						'qty'        => 1,
					],
				],
			]
		);
		create_order(
			[
				'name'        => 'B',
				'pickup_date' => $this->day1,
				'location_id' => $this->location,
				'items'       => [
					[
						'product_id' => $this->kale,
						'qty'        => 4,
					],
				],
			]
		);
		create_order(
			[
				'name'        => 'C',
				'pickup_date' => $this->day2,
				'items'       => [
					[
						'product_id' => $this->bread,
						'qty'        => 2,
					],
				],
			]
		);
	}

	public function test_aggregates_by_date_and_location(): void {
		$this->seed_orders();
		$groups = get_harvest_list();

		$this->assertCount( 2, $groups );
		$this->assertSame( $this->day1, $groups[0]['pickup_date'] );
		$this->assertSame( 'Stand', $groups[0]['location_name'] );
		$this->assertSame( 2, $groups[0]['order_count'] );

		$by_title = array_column( $groups[0]['items'], null, 'title' );
		$this->assertSame( 7, $by_title['Kale']['total_qty'] );
		$this->assertSame( 2, $by_title['Kale']['order_count'] );
		$this->assertSame( 1, $by_title['Bread']['total_qty'] );
	}

	public function test_cancelled_orders_are_excluded(): void {
		$this->seed_orders();
		$big = create_order(
			[
				'name'        => 'D',
				'pickup_date' => $this->day1,
				'location_id' => $this->location,
				'items'       => [
					[
						'product_id' => $this->kale,
						'qty'        => 50,
					],
				],
			]
		);
		cancel_order_by_token( $big['token'] );

		$groups   = get_harvest_list();
		$by_title = array_column( $groups[0]['items'], null, 'title' );
		$this->assertSame( 7, $by_title['Kale']['total_qty'], 'cancelled quantities must not count' );
	}

	public function test_filters_by_location_and_date_range(): void {
		$this->seed_orders();

		$only_stand = get_harvest_list( [ 'location_id' => $this->location ] );
		$this->assertCount( 1, $only_stand );
		$this->assertSame( $this->day1, $only_stand[0]['pickup_date'] );

		$only_day2 = get_harvest_list(
			[
				'date_from' => $this->day2,
				'date_to'   => $this->day2,
			]
		);
		$this->assertCount( 1, $only_day2 );
		$this->assertSame( $this->day2, $only_day2[0]['pickup_date'] );

		$this->assertSame(
			[],
			get_harvest_list(
				[
					'date_from' => 'garbage',
					'date_to'   => 'more-garbage',
				]
			)
		);
	}

	public function test_rest_route_is_staff_only(): void {
		$this->seed_orders();

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', '/producerkit/v1/preorders/harvest' ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/producerkit/v1/preorders/harvest' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data() );
	}
}
