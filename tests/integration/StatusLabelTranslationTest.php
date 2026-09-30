<?php
/**
 * Availability statuses in the site's language (#101).
 *
 * Eleven places built "Sold out" from the slug sold_out, which is English
 * whatever the site's language. These render the real blocks under a stand-in
 * translation and look for the translated word.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\Core\Availability;

class StatusLabelTranslationTest extends WP_UnitTestCase {

	/**
	 * A French language pack, as far as these five words go.
	 */
	private const FRENCH = [
		'Abundant'    => 'Abondant',
		'Available'   => 'Disponible',
		'Limited'     => 'Limité',
		'Sold out'    => 'Épuisé',
		'Unavailable' => 'Indisponible',
	];

	private int $product;

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
		Availability\create_table();

		$this->product = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => 'Honey',
			]
		);

		Availability\upsert(
			[
				'product_id'     => $this->product,
				'location_id'    => 0,
				'status'         => 'sold_out',
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);

		add_filter( 'gettext_producerkit', [ $this, 'translate' ], 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'gettext_producerkit', [ $this, 'translate' ], 10 );
		parent::tear_down();
	}

	public function translate( string $translation, string $text ): string {
		return self::FRENCH[ $text ] ?? $translation;
	}

	public function test_every_status_has_a_translatable_label(): void {
		foreach ( Availability\valid_statuses() as $status ) {
			$label = Availability\status_label( $status );

			$this->assertContains( $label, self::FRENCH, "{$status} did not go through __()." );
		}
	}

	/**
	 * The blocks a visitor actually sees.
	 *
	 * @dataProvider blocks
	 */
	public function test_the_block_shows_the_translated_status( string $block ): void {
		$html = render_block(
			[
				'blockName'    => 'producerkit/' . $block,
				'attrs'        => [ 'productId' => $this->product ],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			]
		);

		$this->assertStringContainsString( 'Épuisé', $html, "{$block} did not translate the status." );
		$this->assertStringNotContainsString( 'Sold out', $html, "{$block} still shows the English label." );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function blocks(): array {
		return [
			'availability board' => [ 'availability-board' ],
			'product card'       => [ 'product-card' ],
			'availability badge' => [ 'availability-badge' ],
		];
	}

	/**
	 * The single product page, which renders its own details rather than a
	 * block.
	 */
	public function test_the_product_page_shows_the_translated_status(): void {
		$html = \ProducerKit\Core\SingleContent\render_product_details( get_post( $this->product ) );

		$this->assertStringContainsString( 'Épuisé', $html );
		$this->assertStringNotContainsString( 'Sold out', $html );
	}
}
