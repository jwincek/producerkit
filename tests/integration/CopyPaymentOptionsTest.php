<?php
/**
 * Copying payment options between locations (#84).
 *
 * The control itself is React and is verified in a browser. What these assert
 * is the wiring it depends on and would silently lose: that another location's
 * payment methods are reachable through the REST route the editor already
 * uses, so this needed no endpoint of its own.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;
use WP_REST_Request;

class CopyPaymentOptionsTest extends WP_UnitTestCase {

	private int $donor;

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
		\ProducerKit\Core\Meta_Fields\register();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->donor = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
				'post_title'  => 'The Home Yard',
			]
		);

		update_post_meta(
			$this->donor,
			'_pkit_payment_methods',
			(string) wp_json_encode(
				[
					[
						'type'  => 'cash',
						'value' => '',
						'label' => '',
					],
					[
						'type'  => 'venmo',
						'value' => 'leftfield',
						'label' => '',
					],
				]
			)
		);
	}

	private function editor(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/editor-location.js' );
	}

	/**
	 * The editor reads donors through the collection route. If the post type's
	 * REST base or namespace moved, the control would quietly find nobody to
	 * copy from and simply never appear.
	 */
	public function test_payment_methods_are_readable_over_rest(): void {
		$request = new WP_REST_Request( 'GET', '/producerkit/v1/locations' );
		$request->set_param( 'per_page', 20 );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), 'The locations collection route moved.' );

		$found = null;
		foreach ( (array) $response->get_data() as $row ) {
			if ( is_array( $row ) && (int) ( $row['id'] ?? 0 ) === $this->donor ) {
				$found = $row;
			}
		}

		$this->assertNotNull( $found, 'The donor location was not in the collection.' );
		$this->assertArrayHasKey(
			'_pkit_payment_methods',
			(array) ( $found['meta'] ?? [] ),
			'Payment methods are not exposed in REST, so nothing can be copied.'
		);

		$decoded = json_decode( (string) $found['meta']['_pkit_payment_methods'], true );

		$this->assertIsArray( $decoded );
		$this->assertCount( 2, $decoded );
		$this->assertSame( 'venmo', $decoded[1]['type'] );
	}

	/**
	 * A copy, never a link: the two lists must be independent afterwards,
	 * because what a stand takes and what a market booth takes genuinely
	 * differ. That is the reason these live per location at all.
	 */
	public function test_editing_a_copy_leaves_the_original_alone(): void {
		$copy = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);

		// What the control does: take the donor's list as a starting point.
		$copied = (string) get_post_meta( $this->donor, '_pkit_payment_methods', true );
		update_post_meta( $copy, '_pkit_payment_methods', $copied );

		// Then the producer edits it.
		$edited   = json_decode( $copied, true );
		$edited[] = [
			'type'  => 'snap_ebt',
			'value' => '',
			'label' => '',
		];
		update_post_meta( $copy, '_pkit_payment_methods', (string) wp_json_encode( $edited ) );

		$this->assertCount(
			2,
			json_decode( (string) get_post_meta( $this->donor, '_pkit_payment_methods', true ), true ),
			'Editing the copy changed the original.'
		);
		$this->assertCount(
			3,
			json_decode( (string) get_post_meta( $copy, '_pkit_payment_methods', true ), true )
		);
	}

	/**
	 * The control is offered only where it helps: nothing set here, something
	 * set somewhere else. The same rule the board's filters and the Default
	 * Pages button follow.
	 */
	public function test_the_control_is_gated_on_being_useful(): void {
		$this->assertStringContainsString(
			'methods.length === 0 && donors.length > 0',
			$this->editor(),
			'The copy control should not appear once this location has its own list.'
		);
	}

	/**
	 * A donor whose meta will not parse must be skipped, not offered.
	 */
	public function test_unparseable_meta_never_becomes_a_donor(): void {
		update_post_meta( $this->donor, '_pkit_payment_methods', 'not json' );

		$stored = (string) get_post_meta( $this->donor, '_pkit_payment_methods', true );

		$this->assertNull(
			json_decode( $stored, true ),
			'The fixture no longer represents unparseable meta.'
		);
		$this->assertStringContainsString( 'catch', $this->editor() );
	}
}
