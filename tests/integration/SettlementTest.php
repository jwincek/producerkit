<?php
/**
 * WooCommerce settlement.
 *
 * The test environment loads this plugin but not WooCommerce, so the module
 * bootstrap gates itself off and its includes never run. That is itself worth
 * asserting — "does nothing without WooCommerce" is the contract — and the
 * part that carries real risk, turning free-text catalogue prices into an
 * amount to charge, has no WooCommerce dependency and is loaded directly.
 */

declare(strict_types=1);

use ProducerKit\WooCommerce\Checkout;
use ProducerKit\WooCommerce\Settlement;

final class SettlementTest extends WP_UnitTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Loaded by hand: the module bootstrap returns early with no
		// WooCommerce, which is exactly the behaviour asserted below.
		require_once dirname( __DIR__, 2 ) . '/modules/woocommerce/includes/settlement.php';
		require_once dirname( __DIR__, 2 ) . '/modules/woocommerce/includes/checkout.php';

		// The plugins_loaded upgrade never ran for the same reason, so apply
		// the columns here rather than leaving half the tables without them.
		foreach ( Settlement\tables() as $table ) {
			Settlement\add_columns( $table );
		}
	}

	/* ── The gate ─────────────────────────────────────────────── */

	/**
	 * The module is registered and its bootstrap has already run — it just
	 * returned early. So the assertion is about effects, not about loading it
	 * again: none of the WooCommerce hooks may be wired up.
	 */
	public function test_the_module_stays_out_of_the_way_without_woocommerce(): void {
		$this->assertFalse(
			class_exists( '\WooCommerce' ),
			'This test asserts the no-WooCommerce path; the environment should not have it.'
		);

		$this->assertTrue(
			\ProducerKit\is_module_active( 'woocommerce' ),
			'The module should still be registered and active — it gates itself internally.'
		);

		foreach (
			[
				'woocommerce_order_status_processing',
				'woocommerce_order_status_completed',
				'woocommerce_order_status_cancelled',
				'woocommerce_order_status_refunded',
			] as $hook
		) {
			$this->assertFalse( has_action( $hook ), "{$hook} should not have been wired up." );
		}
	}

	public function test_generating_a_product_refuses_without_woocommerce(): void {
		$result = Checkout\product_for_commission( 1 );

		$this->assertWPError( $result );
		$this->assertSame( 'no_woocommerce', $result->get_error_code() );
	}

	/* ── Schema ───────────────────────────────────────────────── */

	public function test_settlement_columns_are_declared_for_both_request_types(): void {
		$tables = Settlement\tables();

		$this->assertArrayHasKey( 'preorder', $tables );
		$this->assertArrayHasKey( 'commission', $tables );
	}

	public function test_columns_default_to_direct_settlement(): void {
		$columns = Settlement\columns();

		$this->assertArrayHasKey( 'settlement', $columns );
		$this->assertStringContainsString(
			"DEFAULT 'direct'",
			$columns['settlement'],
			'An existing request must not become a WooCommerce one just because the module was switched on.'
		);
	}

	public function test_adding_columns_is_idempotent(): void {
		$table = \ProducerKit\Commissions\Store\table_name();

		Settlement\add_columns( $table );
		Settlement\add_columns( $table );

		foreach ( array_keys( Settlement\columns() ) as $column ) {
			$this->assertTrue(
				Settlement\has_column( $table, $column ),
				"Column '{$column}' was not added."
			);
		}
	}

	/* ── Pricing a pre-order ──────────────────────────────────── */

	/**
	 * The reason this refuses rather than guessing: parse_price() is a
	 * heuristic built for schema.org markup, where a wrong number is
	 * cosmetic. "2 for $5" reads as 2.00. Charging that is not cosmetic.
	 */
	public function test_an_unparseable_line_price_refuses_the_whole_checkout(): void {
		$result = Checkout\price_preorder(
			[
				'items' => [
					[
						'product_id' => 1,
						'qty'        => 2,
						'title'      => 'Salad Greens',
						'price'      => '4.00',
					],
					[
						'product_id' => 2,
						'qty'        => 1,
						'title'      => 'Heritage Tomatoes',
						'price'      => 'market price',
					],
				],
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'unpriceable', $result->get_error_code() );
		$this->assertStringContainsString(
			'Heritage Tomatoes',
			$result->get_error_message(),
			'The operator needs to know which product to fix.'
		);
	}

	public function test_a_fully_priced_order_totals_by_quantity(): void {
		$result = Checkout\price_preorder(
			[
				'items' => [
					[
						'product_id' => 1,
						'qty'        => 3,
						'title'      => 'Sourdough',
						'price'      => '$6.50/loaf',
					],
					[
						'product_id' => 2,
						'qty'        => 2,
						'title'      => 'Salad Greens',
						'price'      => '4.00',
					],
				],
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 27.50, $result['total'] );
		$this->assertCount( 2, $result['lines'] );
		$this->assertSame( 6.50, $result['lines'][0]['price'] );
	}

	public function test_an_empty_order_is_refused(): void {
		$result = Checkout\price_preorder( [ 'items' => [] ] );

		$this->assertWPError( $result );
		$this->assertSame( 'empty_order', $result->get_error_code() );
	}

	public function test_quantity_is_floored_at_one(): void {
		$result = Checkout\price_preorder(
			[
				'items' => [
					[
						'product_id' => 1,
						'qty'        => 0,
						'title'      => 'Sourdough',
						'price'      => '6.00',
					],
				],
			]
		);

		$this->assertSame( 6.00, $result['total'] );
	}

	/* ── Settlement state ─────────────────────────────────────── */

	public function test_attaching_and_settling_a_commission(): void {
		$c = \ProducerKit\Commissions\Store\create(
			[
				'name'        => 'Dana Rivers',
				'email'       => 'dana@example.com',
				'description' => 'A walnut bowl.',
			]
		);

		$this->assertTrue( Settlement\attach_order( 'commission', $c['id'], 4242, 99 ) );

		$found = Settlement\find_by_order( 4242 );
		$this->assertSame( 'commission', $found['type'] );
		$this->assertSame( (int) $c['id'], $found['id'] );

		$row = \ProducerKit\Commissions\Store\get( $c['id'] );
		$this->assertSame( 'wc', $row['settlement'] );
		$this->assertSame( 99, (int) $row['wc_product_id'] );
		$this->assertNull( $row['settled_at'], 'Raising an order is not the same as being paid.' );

		$this->assertTrue( Settlement\mark_settled( 'commission', $c['id'] ) );
		$this->assertNotNull( \ProducerKit\Commissions\Store\get( $c['id'] )['settled_at'] );
	}

	public function test_an_unknown_order_matches_nothing(): void {
		$this->assertNull( Settlement\find_by_order( 999999 ) );
		$this->assertNull( Settlement\find_by_order( 0 ) );
	}
}
