<?php
/**
 * The shared substrate in front of every public submission: IP hashing,
 * token issue, honeypot, and spam-guard delegation.
 */

declare(strict_types=1);

use ProducerKit\Core\Requests;

final class RequestsTest extends WP_UnitTestCase {

	/* ── IP hashing ───────────────────────────────────────────── */

	public function test_hashed_ip_is_salted_not_a_bare_digest(): void {
		$ip = '203.0.113.7';

		$this->assertNotSame(
			hash( 'sha256', $ip ),
			Requests\hash_ip( $ip ),
			'An unsalted hash of an IPv4 address is trivially reversible by brute force.'
		);
		$this->assertNotSame( md5( $ip ), Requests\hash_ip( $ip ) );
	}

	public function test_hashed_ip_is_stable_and_distinct(): void {
		$this->assertSame( Requests\hash_ip( '203.0.113.7' ), Requests\hash_ip( '203.0.113.7' ) );
		$this->assertNotSame( Requests\hash_ip( '203.0.113.7' ), Requests\hash_ip( '203.0.113.8' ) );
		$this->assertSame( 64, strlen( Requests\hash_ip( '203.0.113.7' ) ), 'Column is VARCHAR(64).' );
	}

	/**
	 * The point of the extraction: pre-orders and RSVPs used to hash
	 * independently. A rate limit is only meaningful if one visitor produces
	 * the same hash everywhere.
	 */
	public function test_both_modules_now_agree_on_the_hash(): void {
		$ip = '198.51.100.22';

		$this->assertSame(
			\ProducerKit\PreOrder\Orders\hash_ip( $ip ),
			\ProducerKit\EventManager\RSVP\hash_ip( $ip )
		);
		$this->assertSame( Requests\hash_ip( $ip ), \ProducerKit\PreOrder\Orders\hash_ip( $ip ) );
	}

	/* ── Client IP ────────────────────────────────────────────── */

	public function test_client_ip_ignores_forwarding_headers_by_default(): void {
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';

		$this->assertSame(
			'203.0.113.7',
			Requests\get_client_ip(),
			'Trusting X-Forwarded-For would let one visitor evade every rate limit.'
		);

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	public function test_client_ip_is_filterable_for_sites_behind_a_proxy(): void {
		$override = static fn () => '198.51.100.5';
		add_filter( 'lfuf_client_ip', $override );

		$this->assertSame( '198.51.100.5', Requests\get_client_ip() );

		remove_filter( 'lfuf_client_ip', $override );
	}

	/* ── Tokens ───────────────────────────────────────────────── */

	public function test_tokens_are_long_and_unique(): void {
		$a = Requests\issue_token();
		$b = Requests\issue_token();

		$this->assertSame( 32, strlen( $a ) );
		$this->assertNotSame( $a, $b );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]+$/', $a, 'Token travels in a URL.' );
	}

	/* ── Honeypot ─────────────────────────────────────────────── */

	/** @dataProvider honeypot_provider */
	public function test_honeypot_detection( mixed $value, bool $tripped ): void {
		$this->assertSame( $tripped, Requests\honeypot_tripped( $value ) );
	}

	public function honeypot_provider(): array {
		return [
			'empty string'    => [ '', false ],
			'absent'          => [ null, false ],
			'whitespace only' => [ "  \n\t ", false ],
			'filled by a bot' => [ 'http://spam.example', true ],
			'single char'     => [ 'x', true ],
		];
	}

	/**
	 * A tripped honeypot returns false — "pretend it worked" — rather than an
	 * error, so a bot is not told which field gave it away.
	 */
	public function test_guard_fakes_success_when_the_honeypot_trips(): void {
		$result = Requests\guard( [ 'name' => 'Bot' ], 'preorder', 'gotcha' );

		$this->assertFalse( $result );
		$this->assertNotWPError( $result );
	}

	public function test_guard_allows_a_clean_submission(): void {
		$this->assertTrue( Requests\guard( [ 'name' => 'A Person' ], 'preorder', '' ) );
	}

	/**
	 * Onsite Spam Guard is an optional dependency. With it absent the form
	 * must stay open — failing closed would take every site without it
	 * offline — and the honeypot plus rate limiter still apply.
	 */
	public function test_spam_check_degrades_to_allow_when_the_plugin_is_absent(): void {
		if ( function_exists( 'simple_spam_shield_check' ) ) {
			$this->markTestSkipped( 'Onsite Spam Guard is active in this environment.' );
		}

		$this->assertTrue( Requests\check_spam( [ 'name' => 'A Person' ], 'preorder' ) );
	}
}
