<?php
/**
 * Pure helpers in modules/core/includes/payments.php.
 *
 * method_types() and method_url() need only the i18n/filter stubs from
 * the unit bootstrap; sanitization paths require WP and live in the
 * integration suite.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function Leftfield\Core\Payments\method_types;
use function Leftfield\Core\Payments\method_url;

final class PaymentsHelpersTest extends TestCase {

	public function test_registry_covers_expected_types(): void {
		$types = method_types();
		$this->assertSame(
			[ 'venmo', 'cashapp', 'paypal', 'link', 'cash', 'check', 'snap_ebt', 'market_voucher' ],
			array_keys( $types ),
		);
		foreach ( $types as $key => $config ) {
			$this->assertContains( $config['kind'], [ 'handle', 'url', 'badge' ], "kind for {$key}" );
			if ( $config['kind'] === 'handle' ) {
				$this->assertArrayHasKey( 'url', $config, "handle type {$key} needs a url template" );
			}
		}
	}

	/** @dataProvider url_provider */
	public function test_method_url( string $type, string $value, string $expected ): void {
		$this->assertSame( $expected, method_url( $type, $value ) );
	}

	public function url_provider(): array {
		return [
			'venmo handle'          => [ 'venmo', 'examplefarm', 'https://venmo.com/examplefarm' ],
			'cashapp cashtag'       => [ 'cashapp', 'myfarm', 'https://cash.app/$myfarm' ],
			'paypal.me'             => [ 'paypal', 'myfarm', 'https://paypal.me/myfarm' ],
			'raw link passthrough'  => [ 'link', 'https://squareup.com/x', 'https://squareup.com/x' ],
			'badge has no url'      => [ 'cash', 'anything', '' ],
			'unknown type'          => [ 'bitcoin', 'x', '' ],
			'empty value'           => [ 'venmo', '', '' ],
			'handle is url-encoded' => [ 'venmo', 'a b', 'https://venmo.com/a%20b' ],
		];
	}
}
