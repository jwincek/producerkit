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

	/**
	 * The upgrade used to open with a version-option early return, so a site
	 * that enabled the commissions module *after* this one had stamped the
	 * option got a table with no settlement columns and no way to acquire
	 * them — every later attach_order() failed silently, for good.
	 */
	public function test_columns_are_added_even_when_the_version_option_is_already_stamped(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'pkit_commissions';

		update_option( Settlement\OPTION, Settlement\DB_VERSION );
		foreach ( array_keys( Settlement\columns() ) as $column ) {
			$wpdb->query( "ALTER TABLE {$table} DROP COLUMN {$column}" ); // phpcs:ignore
		}
		Settlement\flush_column_cache();

		$this->assertFalse( Settlement\has_column( $table, 'wc_order_id' ), 'Precondition: the column is gone.' );

		Settlement\maybe_upgrade();
		Settlement\flush_column_cache();

		$this->assertTrue(
			Settlement\has_column( $table, 'wc_order_id' ),
			'The columns are the source of truth, not the option.'
		);
	}

	/**
	 * on_paid() is hooked to both `processing` and `completed`, so a normal
	 * payment followed by the maker marking the order complete fires it twice.
	 * Without the guard the second pass overwrote settled_at with the
	 * fulfilment time and re-fired pkit_request_settled.
	 */
	public function test_settling_twice_only_counts_once(): void {
		global $wpdb;

		$commission = \ProducerKit\Commissions\Store\create(
			[
				'name'        => 'Dana',
				'email'       => 'dana@example.com',
				'description' => 'A walnut bowl.',
			]
		);
		$this->assertNotWPError( $commission );

		$id = (int) $commission['id'];

		$this->assertTrue( Settlement\mark_settled( 'commission', $id ), 'First settlement takes.' );

		$table = $wpdb->prefix . 'pkit_commissions';
		// phpcs:ignore
		$first = $wpdb->get_var( $wpdb->prepare( "SELECT settled_at FROM {$table} WHERE id = %d", $id ) );

		$this->assertFalse( Settlement\mark_settled( 'commission', $id ), 'Second is a no-op.' );

		// phpcs:ignore
		$second = $wpdb->get_var( $wpdb->prepare( "SELECT settled_at FROM {$table} WHERE id = %d", $id ) );

		$this->assertSame( $first, $second, 'settled_at must record when it was paid, not when it was fulfilled.' );
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
	 * Every string here was charged wrongly, or refused wrongly, before
	 * chargeable_price() existed. The earlier guard used parse_price(), a
	 * schema.org heuristic that takes the first run of digits — fine when a
	 * wrong number is cosmetic, a mischarge when it is money.
	 *
	 * @dataProvider price_provider
	 */
	public function test_only_an_unambiguous_amount_is_chargeable( string $input, ?float $expected ): void {
		$this->assertSame( $expected, Checkout\chargeable_price( $input ) );
	}

	public function price_provider(): array {
		return [
			'plain'                  => [ '4.00', 4.00 ],
			'currency prefix'        => [ '$4.00', 4.00 ],
			'per-unit suffix'        => [ '$6.50/loaf', 6.50 ],
			'unit word'              => [ '$5 each', 5.00 ],
			'thousands separator'    => [ '$1,200.00', 1200.00 ],

			// parse_price() answered 2.00 here — a 60% undercharge on every order.
			'quantity then price'    => [ '2 for $5', null ],
			// parse_price() answered 1.00 here — off by a factor of 1200.
			'comma read as a break'  => [ '1,200.00', 1200.00 ],
			'two numbers'            => [ '50% off $10', null ],
			'no number at all'       => [ 'market price', null ],
			// The absint() trap: a minus swallowed as punctuation would charge
			// for what should be a credit.
			'negative'               => [ '-5', null ],
			'negative with currency' => [ '$-5.00', null ],
			'zero'                   => [ '0', null ],
			'sub-cent precision'     => [ '4.005', null ],
			'empty'                  => [ '', null ],
		];
	}

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

	/**
	 * The shape that actually loses money: it parses, so nothing errors, and
	 * the customer is simply charged less than the sign says.
	 */
	public function test_a_quantity_priced_line_refuses_rather_than_undercharging(): void {
		$result = Checkout\price_preorder(
			[
				'items' => [
					[
						'product_id' => 1,
						'qty'        => 1,
						'title'      => 'Sweetcorn',
						'price'      => '2 for $5',
					],
				],
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'unpriceable', $result->get_error_code() );
		$this->assertStringContainsString( 'Sweetcorn', $result->get_error_message() );
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
