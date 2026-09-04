<?php
/**
 * WooCommerce feature-compatibility declarations.
 *
 * WooCommerce only looks at plugins carrying a "WC tested up to" header; it is
 * how PluginUtil::is_woocommerce_aware_plugin() decides whether a plugin is an
 * extension at all. Without that header this plugin was invisible to the
 * compatibility machinery — it never appeared in the HPOS screen in any
 * bucket, compatible or otherwise.
 *
 * Adding the header alone is worse than not having it. The custom_order_tables
 * feature declares default_plugin_compatibility = 'incompatible', so a visible
 * plugin that has not declared itself lands in "uncertain", which that default
 * folds into "incompatible" — and FeaturesController then disables the HPOS
 * radio outright. Verified on WooCommerce 11.1.0: header without declaration
 * put producerkit/producerkit.php in the blocking list and greyed the setting.
 *
 * So the header and the declaration are one change, and these tests exist to
 * stop them being separated later. The environment has no WooCommerce, so the
 * assertions are on the plugin's own source rather than on WooCommerce state.
 */

declare(strict_types=1);

final class WooFeatureCompatTest extends WP_UnitTestCase {

	private function plugin_file(): string {
		return (string) file_get_contents( \ProducerKit\PLUGIN_DIR . '/producerkit.php' );
	}

	public function test_environment_has_no_woocommerce(): void {
		$this->assertFalse(
			class_exists( '\WooCommerce' ),
			'These are source-level assertions precisely because WooCommerce is absent here.'
		);
	}

	public function test_header_declares_woocommerce_support(): void {
		$source = $this->plugin_file();

		$this->assertMatchesRegularExpression(
			'/^\s*\*\s*WC requires at least:\s*\S+/m',
			$source,
			'Without a WC floor the plugin makes no claim about which WooCommerce it works with.'
		);
		$this->assertMatchesRegularExpression(
			'/^\s*\*\s*WC tested up to:\s*\S+/m',
			$source,
			'This header is the only thing that makes WooCommerce treat the plugin as an extension.'
		);
	}

	public function test_visibility_and_declaration_travel_together(): void {
		$source = $this->plugin_file();

		$visible  = 1 === preg_match( '/^\s*\*\s*WC tested up to:\s*\S+/m', $source );
		$declares = str_contains( $source, "add_action(\n\t'before_woocommerce_init'" )
			&& str_contains( $source, 'FeaturesUtil::declare_compatibility' );

		$this->assertSame(
			$visible,
			$declares,
			'A "WC tested up to" header without a feature declaration disables the HPOS '
				. 'setting for the whole store. Remove both or keep both.'
		);
	}

	public function test_declares_the_two_features_it_can_honestly_claim(): void {
		$source = $this->plugin_file();

		foreach ( [ 'custom_order_tables', 'cart_checkout_blocks' ] as $feature ) {
			$this->assertStringContainsString(
				"'" . $feature . "'",
				$source,
				sprintf( 'Compatibility with %s is declared and true; dropping it re-flags the plugin.', $feature )
			);
		}
	}

	public function test_declaration_is_hooked_where_woocommerce_demands(): void {
		$this->assertStringContainsString(
			"add_action(\n\t'before_woocommerce_init'",
			$this->plugin_file(),
			'declare_compatibility() returns false with a _doing_it_wrong() notice anywhere else.'
		);
	}

	public function test_declaration_is_not_gated_on_the_optional_module(): void {
		$source = $this->plugin_file();

		$start = strpos( $source, "'before_woocommerce_init'" );
		$this->assertIsInt( $start );

		$block = substr( $source, $start, 600 );

		$this->assertStringNotContainsString(
			'is_module_active',
			$block,
			'The WooCommerce module is optional. A site that switched it off integrates '
				. 'with nothing, is trivially compatible, and must not block HPOS either.'
		);
	}

	public function test_integration_surface_stays_narrow_enough_for_the_claim(): void {
		// The cart_checkout_blocks claim rests on this: nothing hooks the cart,
		// registers a gateway, or adds checkout fields. If that ever changes,
		// the declaration becomes a false claim and this test says so.
		$hooks = [];
		foreach ( glob( \ProducerKit\PLUGIN_DIR . '/modules/*/includes/*.php' ) as $file ) {
			preg_match_all(
				"/add_(?:action|filter)\(\s*'(woocommerce_[a-z_]+)'/",
				(string) file_get_contents( $file ),
				$m
			);
			$hooks = array_merge( $hooks, $m[1] );
		}

		$hooks = array_values( array_unique( $hooks ) );
		sort( $hooks );

		$this->assertSame(
			[
				'woocommerce_order_status_cancelled',
				'woocommerce_order_status_completed',
				'woocommerce_order_status_processing',
				'woocommerce_order_status_refunded',
			],
			$hooks,
			'Order-status transitions fire identically under HPOS and under block checkout. '
				. 'A cart, gateway or checkout-field hook would break one of the two claims.'
		);
	}
}
