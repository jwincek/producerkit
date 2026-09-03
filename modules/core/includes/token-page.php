<?php
/**
 * A page a visitor reaches with a token instead of an account.
 *
 * Several things this plugin collects from anonymous visitors need a way back:
 * a quote to accept, an RSVP to cancel, a pre-order to change. None of those
 * people have a login, and asking them to make one to cancel a booking is how
 * you get a phone call instead.
 *
 * So the token is the capability. It arrives in an email, it opens a page that
 * shows what it refers to, and any action is a POST with a nonce — never a GET,
 * because a mail client prefetching a link must not be able to cancel someone's
 * booking or accept a quote on their behalf.
 *
 * The commission quote flow established this shape; RSVPs are the second use
 * and pre-orders will be the third, which is why it lives in core rather than
 * being copied a third time.
 */

declare(strict_types=1);

namespace ProducerKit\Core\TokenPage;

defined( 'ABSPATH' ) || exit;

/**
 * The address for a token page.
 *
 * A query var on the home URL rather than a page the site owner has to create:
 * the email has to know the address before any page exists, and a link that
 * depends on a page still being there is a link that breaks.
 *
 * @param string $query_var Registered query var, e.g. 'pkit_rsvp'.
 * @param string $token     The token itself.
 */
function url_for( string $query_var, string $token ): string {
	return add_query_arg( $query_var, rawurlencode( $token ), home_url( '/' ) );
}

/**
 * Whether this request carries a POST from a token page's own form.
 *
 * Split out because the nonce cannot be checked until the caller has resolved
 * the token, and the caller should not have to reimplement the method test.
 */
function is_submission( string $field ): bool {
	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce once they have resolved the token; this only reports whether a POST happened.
	return 'POST' === $method && isset( $_POST[ $field ] );
}

/**
 * Verify the nonce guarding a token page's form.
 */
function verify( string $action, string $nonce_field = 'pkit_token_nonce' ): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This function is the verification.
	if ( ! isset( $_POST[ $nonce_field ] ) ) {
		return false;
	}

	return (bool) wp_verify_nonce(
		sanitize_key( wp_unslash( $_POST[ $nonce_field ] ) ),
		$action
	);
}

/**
 * Render a standalone page in the site's own theme, and stop.
 *
 * $body is echoed as built rather than passed through wp_kses_post(). Callers
 * build it with every interpolated value escaped at its own site, and kses is
 * for filtering *submitted* content — running it here strips `<form>` and
 * `<input>`, which are not in the post allowlist. That silently removed a
 * nonce field once and left buttons that posted nothing.
 *
 * @param string $title Page heading, plain text.
 * @param string $body  Markup, already escaped by the caller.
 */
function render( string $title, string $body ): void {
	status_header( 200 );
	nocache_headers();

	// A token sits in the URL and the theme will echo that URL back in a
	// canonical link. Keep the page out of indexes, and out of the Referer
	// header on any outbound click, so the link cannot travel further than the
	// person it was sent to.
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	add_action(
		'wp_head',
		static function (): void {
			echo '<meta name="referrer" content="no-referrer">' . "\n";
		},
		1
	);

	get_header();
	?>
	<main class="pkit-token-page wp-block-group is-layout-constrained" style="max-width:38rem;margin:4rem auto;padding:0 1rem;">
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the caller with each value escaped at its own site; see the note above on why kses is wrong here.
		echo $body;
		?>
	</main>
	<?php
	get_footer();
	exit;
}

/**
 * The page shown when a token does not resolve.
 *
 * Deliberately the same answer for "no such token" and "expired": a guesser
 * learns nothing either way.
 */
function render_unknown( string $title, string $message ): void {
	render( $title, '<p>' . esc_html( $message ) . '</p>' );
}
