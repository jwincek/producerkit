<?php
/**
 * The plugin must not look like it is only for farms.
 *
 * Icons drift the way prose does, and they are harder to notice: the rename
 * caught "Farm Stand Manager" and "🥕 Farm Stand", and left a carrot on the
 * product editor panel, another in the block inserter, a leafy green and a
 * sheaf of grain on the dashboard, and a carrot at the top of every email the
 * plugin sent.
 *
 * A potter, an author, a beekeeper and a band all install this.
 */

declare(strict_types=1);

final class IconNeutralityTest extends WP_UnitTestCase {

	/**
	 * Icons that name one trade. Not exhaustive — it cannot be — but it fails
	 * on the ones that actually shipped, which is the point.
	 */
	private const TRADE_SPECIFIC = [
		'carrot',
		'hammer',
		'🥕',
		'🥬',
		'🌾',
	];

	/**
	 * @dataProvider source_file_provider
	 */
	public function test_no_source_file_names_a_single_trade_in_an_icon( string $path ): void {
		$source = (string) file_get_contents( $path );

		foreach ( self::TRADE_SPECIFIC as $needle ) {
			// The comment recording why a carrot was removed is allowed to say
			// the word; an icon value is not.
			$in_icon = preg_match(
				'/(icon["\']?\s*[:=]>?\s*["\'][^"\']*' . preg_quote( $needle, '/' ) . '|' . preg_quote( $needle, '/' ) . '\s*\' \. esc_html)/u',
				$source
			);

			$this->assertSame(
				0,
				$in_icon,
				basename( $path ) . " uses \"{$needle}\" as an icon. Every trade sees these."
			);
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function source_file_provider(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge(
			(array) glob( $root . '/blocks/*/block.json' ),
			(array) glob( $root . '/blocks/*/index.js' ),
			(array) glob( $root . '/assets/js/editor-*.js' ),
			(array) glob( $root . '/includes/*.php' ),
			(array) glob( $root . '/modules/*/includes/*.php' )
		);

		$out = [];
		foreach ( array_filter( $files ) as $file ) {
			$out[ basename( dirname( (string) $file ) ) . '/' . basename( (string) $file ) ] = [ (string) $file ];
		}

		return $out;
	}

	/**
	 * The catalogue is a tag in the menu, in the editor panel and in the block
	 * inserter. One thing, one icon.
	 */
	public function test_the_catalogue_icon_agrees_across_surfaces(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertSame(
			'dashicons-tag',
			get_post_type_object( 'pkit_product' )->menu_icon
		);

		$block = json_decode( (string) file_get_contents( $root . '/blocks/product-card/block.json' ), true );
		$this->assertSame( 'tag', $block['icon'] ?? null, 'The Product Card block should match the menu.' );

		$editor = (string) file_get_contents( $root . '/assets/js/editor-product.js' );
		$this->assertStringContainsString( "icon: 'tag'", $editor );
	}
}
