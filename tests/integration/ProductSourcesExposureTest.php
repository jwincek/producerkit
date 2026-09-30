<?php
/**
 * What the public may learn about a product's sources (#102).
 *
 * GET /products/{id}/sources and the get-product-sources ability are both
 * public and both take an arbitrary product ID. They filtered the sources they
 * returned to published ones and never checked the product, so a draft's
 * sources were readable by anyone counting through IDs.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

class ProductSourcesExposureTest extends WP_UnitTestCase {

	private int $source;

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();

		// Nobody logged in: these are public paths.
		wp_set_current_user( 0 );

		$this->source = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_source',
				'post_status' => 'publish',
				'post_title'  => 'Hillside Mill',
			]
		);
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function product( array $overrides = [] ): int {
		$id = (int) self::factory()->post->create(
			array_merge(
				[
					'post_type'   => 'pkit_product',
					'post_status' => 'publish',
				],
				$overrides
			)
		);
		update_post_meta( $id, '_pkit_source_ids', [ $this->source ] );
		return $id;
	}

	/**
	 * Source titles from both public paths.
	 *
	 * @return array{rest: array<int, string>, ability: array<int, string>}
	 */
	private function ask( int $product ): array {
		$rest = rest_do_request( new WP_REST_Request( 'GET', "/producerkit/v1/products/{$product}/sources" ) )->get_data();

		$ability = wp_get_ability( 'producerkit/get-product-sources' )->execute( [ 'product_id' => $product ] );

		return [
			'rest'    => array_map( static fn ( $s ): string => (string) $s['title'], (array) $rest ),
			'ability' => array_map( static fn ( $s ): string => (string) $s['title'], is_wp_error( $ability ) ? [] : (array) $ability ),
		];
	}

	public function test_a_published_products_sources_are_public(): void {
		$this->assertSame(
			[
				'rest'    => [ 'Hillside Mill' ],
				'ability' => [ 'Hillside Mill' ],
			],
			$this->ask( $this->product() )
		);
	}

	/**
	 * Every way a product can be not-public, on both paths.
	 *
	 * @dataProvider hidden_products
	 *
	 * @param array<string, mixed> $overrides
	 */
	public function test_nothing_is_revealed_about_a_product_the_public_cannot_see( array $overrides ): void {
		$this->assertSame(
			[
				'rest'    => [],
				'ability' => [],
			],
			$this->ask( $this->product( $overrides ) )
		);
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function hidden_products(): array {
		return [
			'draft'              => [ [ 'post_status' => 'draft' ] ],
			'private'            => [ [ 'post_status' => 'private' ] ],
			'pending review'     => [ [ 'post_status' => 'pending' ] ],
			'scheduled'          => [
				[
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
				],
			],
			'password-protected' => [ [ 'post_password' => 'secret' ] ],
		];
	}

	/**
	 * The route used to read the meta from any post at all.
	 */
	public function test_an_id_that_is_not_a_product_reveals_nothing(): void {
		$event = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $event, '_pkit_source_ids', [ $this->source ] );

		$this->assertSame(
			[
				'rest'    => [],
				'ability' => [],
			],
			$this->ask( $event )
		);
	}

	/**
	 * A draft answers exactly as a product with no sources does, so a guesser
	 * learns nothing about what exists.
	 */
	public function test_a_draft_is_indistinguishable_from_a_product_with_no_sources(): void {
		$draft = $this->product( [ 'post_status' => 'draft' ] );

		$none = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);

		$draft_response = rest_do_request( new WP_REST_Request( 'GET', "/producerkit/v1/products/{$draft}/sources" ) );
		$none_response  = rest_do_request( new WP_REST_Request( 'GET', "/producerkit/v1/products/{$none}/sources" ) );

		$this->assertSame( $none_response->get_status(), $draft_response->get_status() );
		$this->assertSame( $none_response->get_data(), $draft_response->get_data() );
	}

	/**
	 * Unchanged, and kept as a guard: an unpublished source never appears,
	 * even on a published product.
	 */
	public function test_an_unpublished_source_stays_hidden(): void {
		wp_update_post(
			[
				'ID'          => $this->source,
				'post_status' => 'draft',
			]
		);

		$this->assertSame(
			[
				'rest'    => [],
				'ability' => [],
			],
			$this->ask( $this->product() )
		);
	}
}
