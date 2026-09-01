<?php
/**
 * Shared substrate for public submissions.
 *
 * Three features accept input from anonymous visitors — event RSVPs,
 * pre-orders, and (once merged) commission requests. They differ completely
 * in what they store and mean, but the guard rails in front of them are the
 * same: hash the IP, trip a honeypot, ask the spam plugin, issue a token.
 *
 * Those had grown three separate implementations, one of which hashed IPs
 * with bare md5(). Collecting them here makes the guarantees uniform and
 * gives a new request type somewhere to start from.
 *
 * Storage-specific concerns deliberately stay with each feature: how many
 * submissions count as too many, and how those are counted, depend on the
 * table doing the counting.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Hash an IP address for storage.
 *
 * Salted with wp_salt( 'auth' ) so a stored hash cannot be reversed by
 * running the (small) IPv4 space through a plain digest. The salt is
 * per-site, so hashes are not comparable across installs either.
 */
function hash_ip( string $ip ): string {
	return hash( 'sha256', $ip . wp_salt( 'auth' ) );
}

/**
 * The client IP.
 *
 * REMOTE_ADDR only, deliberately: forwarding headers are attacker-controlled
 * unless a known proxy is in front, and trusting them would let one visitor
 * evade every rate limit by varying a header. Sites behind a real proxy can
 * correct this through the filter.
 */
function get_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: '0.0.0.0';

	/**
	 * Filters the client IP used for rate limiting and hashing.
	 *
	 * @param string $ip The address from REMOTE_ADDR.
	 */
	return (string) apply_filters( 'lfuf_client_ip', $ip );
}

/**
 * Issue an opaque token for a public request.
 *
 * Used to let a guest reach their own submission without an account, so it
 * has to be unguessable rather than merely unique.
 */
function issue_token(): string {
	return wp_generate_password( 32, false );
}

/**
 * Whether a honeypot field was filled in.
 *
 * The field is hidden from people and irresistible to naive bots, so any
 * value at all means "not a person".
 */
function honeypot_tripped( mixed $value ): bool {
	return '' !== trim( (string) $value );
}

/**
 * Ask Onsite Spam Guard about a submission, if it is installed.
 *
 * Degrades to "allow" when the plugin is absent — the honeypot and the rate
 * limiter still apply, and failing closed would take the form offline on any
 * site that has not installed it.
 *
 * Skipped when the honeypot has already tripped, so obvious bots take the
 * silent-accept path the callers implement rather than being told they were
 * caught.
 *
 * @param array<string, mixed> $fields  Submitted values, for content checks.
 * @param string               $context A label for the form, e.g. 'preorder'.
 * @return true|\WP_Error
 */
function check_spam( array $fields, string $context ): bool|\WP_Error {
	if ( ! function_exists( 'simple_spam_shield_check' ) ) {
		return true;
	}

	$result = simple_spam_shield_check(
		[
			'context' => $context,
			'fields'  => $fields,
		]
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// The plugin may answer with a plain boolean.
	if ( false === $result ) {
		return new \WP_Error(
			'spam_rejected',
			__( 'That submission looked automated. Please try again.', 'producerkit' )
		);
	}

	return true;
}

/**
 * Run the guards that do not depend on how a request is stored.
 *
 * Returns a WP_Error the caller can surface, or `false` to mean "silently
 * accept" — the honeypot path, where telling a bot it was caught only helps
 * it try again.
 *
 * @param array<string, mixed> $fields   Submitted values.
 * @param string               $context  Form label for the spam plugin.
 * @param mixed                $honeypot The honeypot field's value.
 * @return true|false|\WP_Error True to proceed, false to fake success.
 */
function guard( array $fields, string $context, mixed $honeypot = '' ): bool|\WP_Error {
	if ( honeypot_tripped( $honeypot ) ) {
		return false;
	}

	return check_spam( $fields, $context );
}
