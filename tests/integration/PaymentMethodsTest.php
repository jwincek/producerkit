<?php
/**
 * Per-location payment methods: write-side sanitization, the legacy
 * Venmo merge, enrichment, and block rendering.
 */

declare(strict_types=1);

use function ProducerKit\Core\Payments\get_payment_methods;

final class PaymentMethodsTest extends WP_UnitTestCase {

	private function make_location(): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);
	}

	public function test_sanitizer_drops_hostile_and_unknown_entries(): void {
		$location = $this->make_location();
		update_post_meta(
			$location,
			'_pkit_payment_methods',
			[
				[
					'type'  => 'venmo',
					'value' => '@my-farm',
					'label' => '',
				],
				[
					'type'  => 'link',
					'value' => 'javascript:alert(1)',
					'label' => 'Evil',
				],
				[
					'type'  => 'link',
					'value' => 'https://sq.example/x',
					'label' => 'Square',
				],
				[
					'type'  => 'bitcoin',
					'value' => 'bc1xyz',
					'label' => '',
				],
				[
					'type'  => 'snap_ebt',
					'value' => '',
					'label' => '',
				],
			]
		);

		$stored = json_decode( (string) get_post_meta( $location, '_pkit_payment_methods', true ), true );
		$types  = array_column( $stored, 'type' );

		$this->assertSame( [ 'venmo', 'link', 'snap_ebt' ], $types, 'hostile link and unknown type must be dropped' );
		$this->assertSame( 'my-farm', $stored[0]['value'], 'leading @ stripped' );
		$this->assertSame( 'https://sq.example/x', $stored[1]['value'] );
	}

	public function test_legacy_venmo_handle_merges_when_list_has_no_venmo(): void {
		$location = $this->make_location();
		update_post_meta( $location, '_pkit_venmo_handle', 'oldhandle' );

		$methods = get_payment_methods( $location );
		$this->assertCount( 1, $methods );
		$this->assertSame( 'venmo', $methods[0]['type'] );
		$this->assertSame( 'https://venmo.com/oldhandle', $methods[0]['url'] );
		$this->assertTrue( $methods[0]['is_link'] );
	}

	public function test_explicit_venmo_entry_wins_over_legacy_handle(): void {
		$location = $this->make_location();
		update_post_meta( $location, '_pkit_venmo_handle', 'oldhandle' );
		update_post_meta(
			$location,
			'_pkit_payment_methods',
			[
				[
					'type'  => 'venmo',
					'value' => 'newhandle',
					'label' => '',
				],
			]
		);

		$methods = get_payment_methods( $location );
		$venmos  = array_values( array_filter( $methods, fn ( $m ) => $m['type'] === 'venmo' ) );
		$this->assertCount( 1, $venmos, 'legacy handle must not duplicate an explicit venmo entry' );
		$this->assertSame( 'newhandle', $venmos[0]['value'] );
	}

	public function test_enrichment_labels_and_badges(): void {
		$location = $this->make_location();
		update_post_meta(
			$location,
			'_pkit_payment_methods',
			[
				[
					'type'  => 'cash',
					'value' => '',
					'label' => '',
				],
				[
					'type'  => 'cashapp',
					'value' => 'myfarm',
					'label' => 'Cash App ($myfarm)',
				],
			]
		);

		$methods = get_payment_methods( $location );
		$this->assertSame( 'Cash', $methods[0]['label'], 'default label from the registry' );
		$this->assertFalse( $methods[0]['is_link'] );
		$this->assertSame( 'Cash App ($myfarm)', $methods[1]['label'], 'custom label override' );
		$this->assertSame( 'https://cash.app/$myfarm', $methods[1]['url'] );
	}

	public function test_location_info_block_renders_methods_without_leaking_hostile_urls(): void {
		$location = $this->make_location();
		update_post_meta( $location, '_pkit_venmo_handle', 'examplefarm' );
		update_post_meta(
			$location,
			'_pkit_payment_methods',
			[
				[
					'type'  => 'venmo',
					'value' => 'examplefarm',
					'label' => '',
				],
				[
					'type'  => 'snap_ebt',
					'value' => '',
					'label' => '',
				],
				[
					'type'  => 'link',
					'value' => 'javascript:alert(1)',
					'label' => 'Evil',
				],
			]
		);

		$html = do_blocks( sprintf( '<!-- wp:producerkit/location-info {"locationId":%d} /-->', $location ) );

		$this->assertStringContainsString( 'pkit-location-info__payments', $html );
		$this->assertStringContainsString( 'https://venmo.com/examplefarm', $html );
		$this->assertStringContainsString( 'SNAP/EBT', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_qr_container_renders_only_when_enabled(): void {
		$location = $this->make_location();
		update_post_meta( $location, '_pkit_venmo_handle', 'examplefarm' );

		$off = do_blocks( sprintf( '<!-- wp:producerkit/location-info {"locationId":%d} /-->', $location ) );
		$this->assertStringNotContainsString( 'data-pkit-qr', $off );

		$on = do_blocks( sprintf( '<!-- wp:producerkit/location-info {"locationId":%d,"showQR":true} /-->', $location ) );
		$this->assertStringContainsString( 'data-pkit-qr="https://venmo.com/examplefarm"', $on );
	}
}
