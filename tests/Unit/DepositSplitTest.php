<?php
/**
 * The deposit split: what a line collects now and what it leaves for pickup.
 *
 * Pure arithmetic with no WordPress in it, so it belongs in the unit suite —
 * and money arithmetic is exactly the kind that looks obviously right and is
 * off by a cent.
 */

declare(strict_types=1);

use function ProducerKit\Core\Deposits\split_line;

final class DepositSplitTest extends PHPUnit\Framework\TestCase {

	private function policy( string $mode, string $kind = 'fixed', float $value = 0 ): array {
		return compact( 'mode', 'kind', 'value' );
	}

	public function test_reserve_only_charges_nothing(): void {
		$split = split_line( 200.00, $this->policy( 'none' ) );

		$this->assertSame( 0.0, $split['due_now'] );
		$this->assertSame( 200.00, $split['balance'] );
	}

	public function test_full_charges_everything(): void {
		$split = split_line( 200.00, $this->policy( 'full' ) );

		$this->assertSame( 200.00, $split['due_now'] );
		$this->assertSame( 0.0, $split['balance'] );
	}

	public function test_fixed_deposit_leaves_the_rest(): void {
		// Two nucs at $200, $50 down each: the caller passes the line total.
		$split = split_line( 400.00, $this->policy( 'deposit', 'fixed', 50.00 ) );

		$this->assertSame( 50.00, $split['due_now'], 'Fixed is per unit; the caller multiplies.' );
		$this->assertSame( 350.00, $split['balance'] );
	}

	public function test_percentage_deposit(): void {
		$split = split_line( 200.00, $this->policy( 'deposit', 'percent', 25 ) );

		$this->assertSame( 50.00, $split['due_now'] );
		$this->assertSame( 150.00, $split['balance'] );
	}

	/**
	 * @dataProvider awkward_totals
	 */
	public function test_the_two_halves_always_sum_to_the_line( float $total, float $percent ): void {
		$split = split_line( $total, $this->policy( 'deposit', 'percent', $percent ) );

		$this->assertSame(
			round( $total, 2 ),
			round( $split['due_now'] + $split['balance'], 2 ),
			'A cent lost to double rounding is a cent the customer is quoted wrongly.'
		);
	}

	public static function awkward_totals(): array {
		return [
			'third of a tenner'   => [ 10.00, 33.333 ],
			'sevenths'            => [ 7.77, 14.2857 ],
			'penny line'          => [ 0.01, 50.0 ],
			'repeating'           => [ 19.99, 33.0 ],
			'large'               => [ 1234.56, 17.5 ],
		];
	}

	public function test_deposit_larger_than_the_line_becomes_full_payment(): void {
		$split = split_line( 40.00, $this->policy( 'deposit', 'fixed', 60.00 ) );

		$this->assertSame( 40.00, $split['due_now'], 'Never charge more than the line is worth.' );
		$this->assertSame( 0.0, $split['balance'] );
		$this->assertSame( 'full', $split['mode'], 'And say plainly that it became full payment.' );
	}

	public function test_percentage_over_a_hundred_is_capped(): void {
		$split = split_line( 100.00, $this->policy( 'deposit', 'percent', 150 ) );

		$this->assertSame( 100.00, $split['due_now'] );
		$this->assertSame( 0.0, $split['balance'] );
	}

	public function test_zero_deposit_is_a_reservation_not_an_empty_order(): void {
		$split = split_line( 200.00, $this->policy( 'deposit', 'fixed', 0 ) );

		$this->assertSame( 0.0, $split['due_now'] );
		$this->assertSame( 200.00, $split['balance'] );
		$this->assertSame( 'none', $split['mode'], 'Raising a WooCommerce order for $0 helps nobody.' );
	}

	public function test_negative_inputs_cannot_produce_a_credit(): void {
		$this->assertSame( 0.0, split_line( -50.00, $this->policy( 'full' ) )['due_now'] );
		$this->assertSame( 0.0, split_line( 100.00, $this->policy( 'deposit', 'fixed', -25.0 ) )['due_now'] );
	}

	public function test_an_unknown_mode_never_charges(): void {
		$split = split_line( 200.00, $this->policy( 'sometimes' ) );

		$this->assertSame( 0.0, $split['due_now'] );
		$this->assertSame( 200.00, $split['balance'] );
	}
}
