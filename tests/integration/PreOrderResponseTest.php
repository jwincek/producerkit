<?php
/**
 * Whether a customer can reach a pre-order after placing it.
 *
 * The form told them to "keep this cancellation code in case your plans
 * change" and then gave them nowhere to use it: the code appeared once on the
 * success panel, the confirmation email left it out, and the only thing that
 * accepted it was a REST route nobody outside the plugin would call.
 */

declare(strict_types=1);

use ProducerKit\PreOrder\OrderResponse;
use ProducerKit\PreOrder\Orders;

final class PreOrderResponseTest extends WP_UnitTestCase {

	/** @var array<int, array> */
	private array $mail = [];

	public function set_up(): void {
		parent::set_up();

		$this->mail = [];
		add_filter(
			'wp_mail',
			function ( array $args ): array {
				$this->mail[] = $args;
				return $args;
			}
		);
	}

	/* ── The link the customer is given ───────────────────────── */

	public function test_the_confirmation_email_carries_a_link_to_the_order(): void {
		$order = $this->an_order( 'dana@example.com' );

		$to_customer = null;
		foreach ( $this->mail as $mail ) {
			if ( in_array( 'dana@example.com', (array) $mail['to'], true ) ) {
				$to_customer = $mail;
			}
		}

		$this->assertNotNull( $to_customer, 'The customer should be written to when they leave an address.' );
		$this->assertStringContainsString( 'pkit_preorder=', $to_customer['message'] );
		$this->assertStringContainsString( $order['token'], $to_customer['message'], 'Without the token the link resolves to nothing.' );
	}

	/**
	 * add_query_arg() drops the "=" for an empty value, so a bare prefix would
	 * concatenate into ?pkit_preorderTOKEN. The block substitutes into a
	 * placeholder instead, and that placeholder has to survive URL encoding.
	 */
	public function test_the_block_url_template_is_substitutable(): void {
		$template = OrderResponse\url_for( '__TOKEN__' );

		$this->assertStringContainsString( '__TOKEN__', $template, 'The placeholder must survive encoding.' );
		$this->assertStringContainsString( 'pkit_preorder=__TOKEN__', $template );

		$built = str_replace( '__TOKEN__', 'abc123', $template );
		$this->assertSame( OrderResponse\url_for( 'abc123' ), $built );
	}

	/* ── What the token resolves to ───────────────────────────── */

	public function test_a_token_resolves_to_its_order(): void {
		$order = $this->an_order();
		$found = Orders\get_order_by_token( $order['token'] );

		$this->assertNotNull( $found );
		$this->assertSame( 'Dana', $found['name'] );
	}

	public function test_an_unknown_token_resolves_to_nothing(): void {
		$this->assertNull( Orders\get_order_by_token( 'nosuchtoken' ) );
	}

	/* ── Cancelling ───────────────────────────────────────────── */

	public function test_a_pending_order_can_be_cancelled_by_token(): void {
		$order = $this->an_order();

		$this->assertTrue( Orders\cancel_order_by_token( $order['token'] ) );
		$this->assertSame( 'cancelled', Orders\get_order_by_token( $order['token'] )['status'] );
	}

	/**
	 * Once the order is packed or collected, cancelling online would tell the
	 * customer something untrue about produce already picked.
	 */
	public function test_a_collected_order_cannot_be_cancelled_online(): void {
		$order = $this->an_order();
		Orders\update_status( (int) $order['id'], 'picked_up' );

		$result = Orders\cancel_order_by_token( $order['token'] );

		$this->assertWPError( $result );
		$this->assertSame( 'not_cancellable', $result->get_error_code() );
	}

	public function test_cancelling_an_unknown_token_is_an_error_not_a_silent_pass(): void {
		$result = Orders\cancel_order_by_token( 'nosuchtoken' );

		$this->assertWPError( $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	/* ── Helpers ──────────────────────────────────────────────── */

	private function an_order( string $email = '' ): array {
		$location = self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_title'  => 'Salad Greens',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $product, '_pkit_price', '4.00' );

		$order = Orders\create_order(
			[
				'name'        => 'Dana',
				'email'       => $email,
				'location_id' => $location,
				'pickup_date' => current_time( 'Y-m-d' ),
				'items'       => [
					[
						'product_id' => $product,
						'qty'        => 2,
					],
				],
			]
		);

		$this->assertNotWPError( $order, is_wp_error( $order ) ? $order->get_error_message() : '' );

		delete_transient(
			'pkit_preorder_rate_' . md5(
				\ProducerKit\Core\Requests\hash_ip( \ProducerKit\Core\Requests\get_client_ip() )
			)
		);

		return $order;
	}
}
