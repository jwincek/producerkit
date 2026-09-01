<?php
/**
 * Schema.org JSON-LD output for product and location singles.
 */

declare(strict_types=1);

use function ProducerKit\Core\Availability\upsert;
use function ProducerKit\Core\StructuredData\location_data;
use function ProducerKit\Core\StructuredData\print_json_ld;
use function ProducerKit\Core\StructuredData\product_data;

final class StructuredDataTest extends WP_UnitTestCase {

	public function test_product_offer_from_parseable_price_and_availability(): void {
		$product = self::factory()->post->create(
			[
				'post_type'    => 'lfuf_product',
				'post_status'  => 'publish',
				'post_title'   => 'Schema Kale',
				'post_excerpt' => 'Fresh kale',
			]
		);
		update_post_meta( $product, '_lfuf_price', '$4.50 / bunch' );
		upsert(
			[
				'product_id'     => $product,
				'status'         => 'limited',
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);

		$data = product_data( get_post( $product ) );

		$this->assertSame( 'Product', $data['@type'] );
		$this->assertSame( 'Fresh kale', $data['description'] );
		$this->assertSame( '4.50', $data['offers']['price'] );
		$this->assertSame( 'USD', $data['offers']['priceCurrency'] );
		$this->assertSame( 'https://schema.org/LimitedAvailability', $data['offers']['availability'] );
	}

	public function test_donation_price_emits_no_offer(): void {
		$product = self::factory()->post->create(
			[
				'post_type'   => 'lfuf_product',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $product, '_lfuf_price', 'donation' );

		$this->assertArrayNotHasKey( 'offers', product_data( get_post( $product ) ) );
	}

	public function test_location_business_data(): void {
		$location = self::factory()->post->create(
			[
				'post_type'   => 'lfuf_location',
				'post_status' => 'publish',
				'post_title'  => 'Schema Stand',
			]
		);
		update_post_meta( $location, '_lfuf_address', '123 Farm Road' );
		update_post_meta( $location, '_lfuf_lat', 36.1 );
		update_post_meta( $location, '_lfuf_lng', -82.2 );
		update_post_meta(
			$location,
			'_lfuf_ss_schedule',
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
		update_post_meta( $location, '_lfuf_venmo_handle', 'examplefarm' );
		update_post_meta(
			$location,
			'_lfuf_payment_methods',
			[
				[
					'type'  => 'snap_ebt',
					'value' => '',
					'label' => '',
				],
			]
		);

		$data = location_data( get_post( $location ) );

		$this->assertSame( 'LocalBusiness', $data['@type'] );
		$this->assertSame( '123 Farm Road', $data['address'] );
		$this->assertSame( 36.1, $data['geo']['latitude'] );
		$this->assertSame( [ 'Sa 09:00-16:00' ], $data['openingHours'] );
		$this->assertSame( 'Venmo, SNAP/EBT', $data['paymentAccepted'] );
	}

	public function test_print_outputs_on_product_single_and_escapes_script_breakouts(): void {
		$product = self::factory()->post->create(
			[
				'post_type'    => 'lfuf_product',
				'post_status'  => 'publish',
				'post_excerpt' => 'x</script><script>alert(1)</script>y',
			]
		);

		$this->go_to( get_permalink( $product ) );
		$this->assertTrue( is_singular( 'lfuf_product' ) );

		ob_start();
		print_json_ld();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'application/ld+json', $output );
		$this->assertStringNotContainsString( '<script>alert', $output, 'JSON_HEX_TAG must neutralize breakouts' );

		$json = trim( str_replace( [ '<script type="application/ld+json">', '</script>' ], '', $output ) );
		$this->assertNotNull( json_decode( $json, true ), 'payload must be valid JSON' );
	}

	public function test_print_skips_other_content_and_respects_suppress_filter(): void {
		$page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$this->go_to( get_permalink( $page ) );
		ob_start();
		print_json_ld();
		$this->assertSame( '', ob_get_clean(), 'no JSON-LD outside plugin CPT singles' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'lfuf_product',
				'post_status' => 'publish',
			]
		);
		add_filter( 'lfuf_structured_data', '__return_empty_array' );
		$this->go_to( get_permalink( $product ) );
		ob_start();
		print_json_ld();
		$this->assertSame( '', ob_get_clean(), 'filter must be able to suppress output' );
	}
}
