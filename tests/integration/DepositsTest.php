<?php
/**
 * Per-product deposits: the settings, and the pricing they drive.
 *
 * WooCommerce is absent here, so the order-raising half of the flow is
 * verified by hand against the local store rather than in this suite. What is
 * covered here is everything up to that point — sanitisation of the three
 * product settings, and the split price_preorder() computes from them, which
 * is where a wrong number would become a wrong charge.
 */

declare(strict_types=1);

use ProducerKit\Core\Deposits;
use ProducerKit\WooCommerce\Checkout;

final class DepositsTest extends WP_UnitTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Same reasoning as SettlementTest: the module bootstrap returns early
		// without WooCommerce, and price_preorder() has no dependency on it.
		require_once dirname( __DIR__, 2 ) . '/modules/woocommerce/includes/checkout.php';
	}

	private function product( string $price, array $policy = [] ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => 'Nucleus Colony',
			]
		);

		update_post_meta( $id, '_pkit_price', $price );

		foreach ( $policy as $key => $value ) {
			update_post_meta( $id, '_pkit_' . $key, $value );
		}

		return $id;
	}

	private function line( int $product_id, int $qty, string $price ): array {
		return [
			'items' => [
				[
					'product_id' => $product_id,
					'title'      => get_the_title( $product_id ),
					'qty'        => $qty,
					'price'      => $price,
				],
			],
		];
	}

	/* ── Settings ─────────────────────────────────────────── */

	public function test_an_unrecognised_payment_mode_never_charges(): void {
		$id = $this->product( '200.00', [ 'payment_mode' => 'sometimes' ] );

		$this->assertSame(
			Deposits\MODE_NONE,
			Deposits\for_product( $id )['mode'],
			'An unknown mode must fall back to charging nothing, never to charging.'
		);
	}

	public function test_a_negative_deposit_is_clamped_not_flipped(): void {
		$id = $this->product(
			'200.00',
			[
				'payment_mode'  => 'deposit',
				'deposit_value' => -50,
			]
		);

		$this->assertSame(
			0.0,
			Deposits\for_product( $id )['value'],
			'absint() would turn -50 into a 50 the producer never asked for.'
		);
	}

	public function test_deposit_kind_defaults_to_fixed(): void {
		$id = $this->product( '200.00', [ 'deposit_kind' => 'nonsense' ] );

		$this->assertSame( 'fixed', Deposits\for_product( $id )['kind'] );
	}

	public function test_products_default_to_reserve_only(): void {
		$policy = Deposits\for_product( $this->product( '200.00' ) );

		$this->assertSame(
			Deposits\MODE_NONE,
			$policy['mode'],
			'Existing catalogues must not start charging when the feature ships.'
		);
	}

	/* ── Pricing ──────────────────────────────────────────── */

	public function test_a_fixed_deposit_scales_with_quantity(): void {
		$id = $this->product(
			'200.00',
			[
				'payment_mode'  => 'deposit',
				'deposit_kind'  => 'fixed',
				'deposit_value' => 50,
			]
		);

		$priced = Checkout\price_preorder( $this->line( $id, 2, '200.00' ) );

		$this->assertSame( 400.00, $priced['total'] );
		$this->assertSame( 100.00, $priced['due_now'], '"$50 per nuc" means $100 for two.' );
		$this->assertSame( 300.00, $priced['balance'] );
	}

	public function test_a_percentage_deposit_is_proportional(): void {
		$id = $this->product(
			'9.00',
			[
				'payment_mode'  => 'deposit',
				'deposit_kind'  => 'percent',
				'deposit_value' => 33,
			]
		);

		$priced = Checkout\price_preorder( $this->line( $id, 3, '9.00' ) );

		$this->assertSame( 27.00, $priced['total'] );
		$this->assertSame( 8.91, $priced['due_now'] );
		$this->assertSame( 18.09, $priced['balance'] );
		$this->assertSame( 27.00, round( $priced['due_now'] + $priced['balance'], 2 ) );
	}

	public function test_full_mode_charges_the_whole_line(): void {
		$id = $this->product( '9.00', [ 'payment_mode' => 'full' ] );

		$priced = Checkout\price_preorder( $this->line( $id, 4, '9.00' ) );

		$this->assertSame( 36.00, $priced['due_now'] );
		$this->assertSame( 0.0, $priced['balance'] );
	}

	public function test_reserve_only_leaves_everything_for_pickup(): void {
		$id = $this->product( '9.00' );

		$priced = Checkout\price_preorder( $this->line( $id, 2, '9.00' ) );

		$this->assertSame( 0.0, $priced['due_now'] );
		$this->assertSame( 18.00, $priced['balance'] );
	}

	public function test_a_mixed_order_charges_only_the_lines_that_ask(): void {
		$nuc = $this->product(
			'200.00',
			[
				'payment_mode'  => 'deposit',
				'deposit_kind'  => 'fixed',
				'deposit_value' => 50,
			]
		);
		$jar = $this->product( '9.00' );

		$priced = Checkout\price_preorder(
			[
				'items' => [
					[
						'product_id' => $nuc,
						'title'      => 'Nucleus Colony',
						'qty'        => 2,
						'price'      => '200.00',
					],
					[
						'product_id' => $jar,
						'title'      => 'Honey Bear',
						'qty'        => 3,
						'price'      => '9.00',
					],
				],
			]
		);

		$this->assertSame( 427.00, $priced['total'] );
		$this->assertSame( 100.00, $priced['due_now'], 'Only the nucs ask for money up front.' );
		$this->assertSame( 327.00, $priced['balance'] );

		$modes = wp_list_pluck( $priced['lines'], 'mode' );
		$this->assertSame( [ 'deposit', 'none' ], $modes );
	}

	public function test_a_deposit_above_the_line_becomes_full_payment(): void {
		$id = $this->product(
			'40.00',
			[
				'payment_mode'  => 'deposit',
				'deposit_kind'  => 'fixed',
				'deposit_value' => 60,
			]
		);

		$priced = Checkout\price_preorder( $this->line( $id, 1, '40.00' ) );

		$this->assertSame( 40.00, $priced['due_now'], 'Never take more than the item is worth.' );
		$this->assertSame( 0.0, $priced['balance'] );
	}

	public function test_an_unpriceable_line_still_refuses_the_whole_order(): void {
		$id = $this->product( 'donation', [ 'payment_mode' => 'full' ] );

		$this->assertWPError(
			Checkout\price_preorder( $this->line( $id, 1, 'donation' ) ),
			'A deposit on something with no numeric price cannot be computed, so it must not be guessed.'
		);
	}
}
