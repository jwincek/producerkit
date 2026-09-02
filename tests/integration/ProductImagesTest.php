<?php
/**
 * Product image resolution: type placeholders, the featured-image precedence
 * rule, and the cases that must deliberately render nothing.
 */

declare(strict_types=1);

use function ProducerKit\Core\Product_Images\placeholder_url;
use function ProducerKit\Core\Product_Images\thumbnail_url;
use function ProducerKit\Core\Product_Images\type_slug;

final class ProductImagesTest extends WP_UnitTestCase {

	private function make_product( string $type = '' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => 'Kale',
			]
		);

		if ( $type ) {
			wp_set_object_terms( $id, $type, 'pkit_product_type' );
		}

		return $id;
	}

	/**
	 * Every type the plugin seeds by default must have art, or the feature
	 * silently does nothing on a stock install.
	 *
	 * @dataProvider default_type_provider
	 */
	public function test_every_default_product_type_has_a_placeholder( string $slug ): void {
		$file = \ProducerKit\PLUGIN_DIR . '/assets/img/placeholder-' . $slug . '.svg';
		$this->assertFileExists( $file, "no placeholder art for the default type '$slug'" );

		$product = $this->make_product( $slug );
		$this->assertStringContainsString(
			"placeholder-$slug.svg",
			placeholder_url( $product ),
			"placeholder_url() did not resolve art for '$slug'"
		);
	}

	/** Mirrors the defaults seeded in modules/core/includes/taxonomies.php. */
	public function default_type_provider(): array {
		return [
			'produce'      => [ 'produce' ],
			'bread'        => [ 'bread' ],
			'baked good'   => [ 'baked-good' ],
			'pantry good'  => [ 'pantry-good' ],
			'seedling'     => [ 'seedling' ],
		];
	}

	public function test_untyped_product_gets_no_placeholder(): void {
		$this->assertSame( '', placeholder_url( $this->make_product() ) );
	}

	/**
	 * Product types are a user-editable taxonomy, so a site can easily have a
	 * type this plugin ships no art for. That must render no image rather than
	 * a broken one pointing at a file that does not exist.
	 */
	public function test_unknown_product_type_gets_no_placeholder(): void {
		$product = $this->make_product( 'kombucha' );

		$this->assertSame( 'kombucha', type_slug( $product ) );
		$this->assertSame( '', placeholder_url( $product ) );
	}

	public function test_a_real_featured_image_wins_over_the_placeholder(): void {
		$product = $this->make_product( 'produce' );

		$attachment = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
			$product
		);
		set_post_thumbnail( $product, $attachment );

		$url = thumbnail_url( $product );

		$this->assertStringContainsString( 'canola', $url );
		$this->assertStringNotContainsString( 'placeholder-', $url );
	}

	public function test_thumbnail_url_falls_back_when_there_is_no_featured_image(): void {
		$url = thumbnail_url( $this->make_product( 'bread' ) );
		$this->assertStringContainsString( 'placeholder-bread.svg', $url );
	}

	public function test_filter_can_override_the_placeholder(): void {
		$product = $this->make_product( 'produce' );

		$override = static fn() => 'https://example.org/my.png';
		add_filter( 'pkit_product_placeholder_url', $override );

		$this->assertSame( 'https://example.org/my.png', placeholder_url( $product ) );

		remove_filter( 'pkit_product_placeholder_url', $override );
	}

	public function test_filter_can_disable_placeholders_entirely(): void {
		$product = $this->make_product( 'produce' );

		$off = static fn() => '';
		add_filter( 'pkit_product_placeholder_url', $off );

		$this->assertSame( '', placeholder_url( $product ) );
		$this->assertSame( '', thumbnail_url( $product ) );

		remove_filter( 'pkit_product_placeholder_url', $off );
	}
}
