<?php
/**
 * Schedule-aware pickup dates: weekly-schedule weekdays, season bounds,
 * and blackout dates, enforced in create_order().
 */

declare(strict_types=1);

use function ProducerKit\PreOrder\Orders\create_order;
use function ProducerKit\PreOrder\Orders\pickup_constraints;

final class PickupDatesTest extends WP_UnitTestCase {

	private int $product;
	private int $location;
	private string $saturday;
	private string $monday;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pkit_preorder_rate_limit', fn () => 100 );

		$this->product  = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);
		$this->location = self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);

		// Saturday-only schedule, generous season.
		update_post_meta(
			$this->location,
			'_pkit_ss_schedule',
			wp_json_encode(
				[
					[
						'day'   => 6,
						'open'  => '09:00',
						'close' => '16:00',
					],
				]
			)
		);
		update_post_meta( $this->location, '_pkit_ss_season_start', current_time( 'Y-m-d' ) );
		update_post_meta( $this->location, '_pkit_ss_season_end', gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +60 days' ) ) );

		$day = current_time( 'Y-m-d' );
		while ( (int) gmdate( 'w', strtotime( $day . ' 12:00:00' ) ) !== 6 ) {
			$day = gmdate( 'Y-m-d', strtotime( $day . ' +1 day' ) );
		}
		$this->saturday = $day;
		$this->monday   = gmdate( 'Y-m-d', strtotime( $day . ' +2 days' ) );
	}

	private function order_for( string $date, ?int $location = null ): array|\WP_Error {
		return create_order(
			[
				'name'        => 'T',
				'pickup_date' => $date,
				'location_id' => $location ?? $this->location,
				'items'       => [
					[
						'product_id' => $this->product,
						'qty'        => 1,
					],
				],
			]
		);
	}

	public function test_open_day_is_accepted(): void {
		$this->assertNotWPError( $this->order_for( $this->saturday ) );
	}

	public function test_closed_weekday_is_refused_naming_open_days(): void {
		$result = $this->order_for( $this->monday );
		$this->assertWPError( $result );
		$this->assertSame( 'pickup_day_closed', $result->get_error_code() );
		$this->assertStringContainsString( 'Saturday', $result->get_error_message() );
	}

	public function test_blackout_date_is_refused(): void {
		update_post_meta( $this->location, '_pkit_pickup_blackouts', [ $this->saturday ] );
		$result = $this->order_for( $this->saturday );
		$this->assertWPError( $result );
		$this->assertSame( 'pickup_blackout', $result->get_error_code() );
	}

	public function test_out_of_season_date_is_refused(): void {
		update_post_meta( $this->location, '_pkit_ss_season_end', gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +2 days' ) ) );
		$far_saturday = gmdate( 'Y-m-d', strtotime( $this->saturday . ' +14 days' ) );
		$result       = $this->order_for( $far_saturday );
		$this->assertWPError( $result );
		$this->assertSame( 'pickup_out_of_season', $result->get_error_code() );
	}

	public function test_locationless_orders_only_enforce_the_window(): void {
		$this->assertNotWPError( $this->order_for( $this->monday, 0 ) );
	}

	public function test_location_without_schedule_allows_any_weekday(): void {
		delete_post_meta( $this->location, '_pkit_ss_schedule' );
		$this->assertNotWPError( $this->order_for( $this->monday ) );
	}

	public function test_constraints_shape(): void {
		update_post_meta( $this->location, '_pkit_pickup_blackouts', [ '2026-12-25' ] );
		$constraints = pickup_constraints( $this->location );
		$this->assertSame( [ 6 ], $constraints['allowed_days'] );
		$this->assertSame( [ '2026-12-25' ], $constraints['blackouts'] );
		$this->assertNotSame( '', $constraints['season_start'] );

		$none = pickup_constraints( 0 );
		$this->assertNull( $none['allowed_days'] );
		$this->assertSame( [], $none['blackouts'] );
	}

	public function test_form_renders_pickup_day_hint(): void {
		update_post_meta( $this->product, '_pkit_price', '$4' );
		$html = do_blocks( sprintf( '<!-- wp:producerkit/preorder-form {"locationId":%d} /-->', $this->location ) );
		$this->assertStringContainsString( 'Pickup days: Saturday.', $html );
		$this->assertStringContainsString( 'allowedDays', $html, 'constraints must reach the Interactivity context' );
	}
}
