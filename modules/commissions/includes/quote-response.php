<?php
/**
 * The page a customer lands on when they click Accept or Decline.
 *
 * The REST route that records the decision is POST-only, and correctly so: a
 * mail client prefetching a link, a scanner following it, or a browser
 * pre-rendering it must not be able to accept a quote on someone's behalf.
 * GET is for reading.
 *
 * But the quote email can only send a clickable link, and a click is a GET —
 * so with nothing in between, every customer who clicked Accept got a 404 and
 * no quote could ever be accepted. This is the missing half: a GET lands here
 * and shows what is being agreed to, and a button POSTs the decision back with
 * a nonce.
 *
 * Deliberately a query var on the home URL rather than a block the site owner
 * has to place somewhere. The email has to know the address before the site
 * owner has built any page, and a quote link that depends on a page still
 * existing is a quote link that breaks.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions\QuoteResponse;

use ProducerKit\Commissions\Store;

defined( 'ABSPATH' ) || exit;

/** Query var carrying the quote token. */
const QUERY_VAR = 'pkit_quote';

add_filter( 'query_vars', __NAMESPACE__ . '\\register_query_var' );
add_action( 'template_redirect', __NAMESPACE__ . '\\maybe_render' );

/**
 * @param string[] $vars
 * @return string[]
 */
function register_query_var( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/**
 * The address to put in a quote email.
 */
function url_for( string $quote_token ): string {
	return add_query_arg( QUERY_VAR, rawurlencode( $quote_token ), home_url( '/' ) );
}

/**
 * Intercept the request when a quote token is present.
 */
function maybe_render(): void {
	$token = get_query_var( QUERY_VAR );

	if ( ! is_string( $token ) || '' === $token ) {
		return;
	}

	$commission = Store\find_by_quote_token( $token );

	// find_by_quote_token() returns null for an unknown token and for an
	// expired one alike, so neither tells a guesser which they hit.
	if ( null === $commission ) {
		render_page(
			__( 'This quote link is no longer valid', 'producerkit' ),
			'<p>' . esc_html__( 'It may have already been used, or it may have expired. Please get in touch and we will send you a new one.', 'producerkit' ) . '</p>'
		);
	}

	$decision = handle_post( $token, (array) $commission );

	if ( null !== $decision ) {
		render_page( $decision['title'], $decision['body'] );
	}

	render_confirmation( $token, (array) $commission );
}

/**
 * Record the decision, if this request is the POST from the button.
 *
 * @return array{title: string, body: string}|null Null when this is a plain GET.
 */
function handle_post( string $token, array $commission ): ?array {
	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified immediately below; this only decides whether a POST happened at all.
	if ( 'POST' !== $method || ! isset( $_POST['pkit_decision'] ) ) {
		return null;
	}

	if ( ! isset( $_POST['pkit_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pkit_quote_nonce'] ) ), 'pkit_quote_' . $token ) ) {
		return [
			'title' => __( 'That form had expired', 'producerkit' ),
			'body'  => '<p>' . esc_html__( 'Please open the link from your email again.', 'producerkit' ) . '</p>',
		];
	}

	$decision = 'accept' === sanitize_key( wp_unslash( $_POST['pkit_decision'] ) ) ? 'accepted' : 'declined';
	$result   = Store\set_status( (int) $commission['id'], $decision );

	if ( is_wp_error( $result ) ) {
		return [
			'title' => __( 'We could not record that', 'producerkit' ),
			'body'  => '<p>' . esc_html( $result->get_error_message() ) . '</p>',
		];
	}

	if ( 'declined' === $decision ) {
		return [
			'title' => __( 'Thank you for letting us know', 'producerkit' ),
			'body'  => '<p>' . esc_html__( 'We have recorded that you would rather not go ahead. Nothing further will happen.', 'producerkit' ) . '</p>',
		];
	}

	/**
	 * Fires when a customer accepts a quote from the confirmation page.
	 *
	 * The WooCommerce module listens here to raise an order and supply a
	 * payment link; without it the maker arranges payment directly.
	 *
	 * @param array $commission Public-safe commission data.
	 */
	do_action( 'pkit_quote_accepted_onsite', $result );

	/**
	 * Filters what the customer is told after accepting.
	 *
	 * @param string $body       HTML shown after acceptance.
	 * @param array  $commission Commission data.
	 */
	$body = (string) apply_filters(
		'pkit_quote_accepted_message',
		'<p>' . esc_html__( 'Thank you — your commission is confirmed. We will be in touch about payment and timing.', 'producerkit' ) . '</p>',
		$result
	);

	return [
		'title' => __( 'Your commission is confirmed', 'producerkit' ),
		'body'  => $body,
	];
}

/**
 * The Accept / Decline confirmation itself.
 */
function render_confirmation( string $token, array $commission ): void {
	$price = null !== ( $commission['quoted_price'] ?? null )
		? number_format( (float) $commission['quoted_price'], 2 )
		: '';

	ob_start();
	?>
	<p><?php echo esc_html( sprintf( /* translators: %s: customer name. */ __( 'Hello %s,', 'producerkit' ), (string) $commission['name'] ) ); ?></p>
	<p><?php esc_html_e( 'Here is the quote for the piece you asked about. Nothing is agreed until you choose below.', 'producerkit' ); ?></p>

	<dl class="pkit-quote__terms">
		<?php if ( '' !== $price ) : ?>
			<dt><?php esc_html_e( 'Price', 'producerkit' ); ?></dt>
			<dd><strong><?php echo esc_html( $price ); ?></strong></dd>
		<?php endif; ?>
		<?php if ( ! empty( $commission['estimated_date'] ) ) : ?>
			<dt><?php esc_html_e( 'Estimated ready', 'producerkit' ); ?></dt>
			<dd><?php echo esc_html( (string) $commission['estimated_date'] ); ?></dd>
		<?php endif; ?>
		<dt><?php esc_html_e( 'What you asked for', 'producerkit' ); ?></dt>
		<dd><?php echo nl2br( esc_html( (string) $commission['description'] ) ); ?></dd>
	</dl>

	<?php if ( ! empty( $commission['maker_note'] ) ) : ?>
		<p><?php echo nl2br( esc_html( (string) $commission['maker_note'] ) ); ?></p>
	<?php endif; ?>

	<form method="post" class="pkit-quote__actions">
		<?php wp_nonce_field( 'pkit_quote_' . $token, 'pkit_quote_nonce' ); ?>
		<button type="submit" name="pkit_decision" value="accept" class="wp-element-button">
			<?php esc_html_e( 'Accept this quote', 'producerkit' ); ?>
		</button>
		<button type="submit" name="pkit_decision" value="decline" class="wp-element-button is-style-outline">
			<?php esc_html_e( 'No thank you', 'producerkit' ); ?>
		</button>
	</form>
	<?php
	render_page( __( 'Your quote', 'producerkit' ), (string) ob_get_clean() );
}

/**
 * Render a standalone page and stop.
 *
 * Uses the theme's own header and footer so the page looks like the rest of
 * the site rather than a bare plugin screen.
 *
 * $body is echoed as-is rather than through wp_kses_post(). It is markup this
 * file builds, with every interpolated value escaped at the point it goes in —
 * and kses is for filtering *submitted* content. Running it here stripped the
 * <form> and the hidden nonce input, which are not in the post allowlist,
 * leaving two buttons that posted nothing and a decision no customer could
 * make. Escaping in the wrong context is not extra safety; it is a bug.
 */
function render_page( string $title, string $body ): void {
	status_header( 200 );
	nocache_headers();

	// The token sits in the URL, and the theme will echo that URL back in a
	// canonical link. Keep the page out of indexes, and out of the Referer
	// header on any outbound click, so a quote link cannot travel further than
	// the person it was sent to.
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
	<main class="pkit-quote wp-block-group is-layout-constrained" style="max-width:38rem;margin:4rem auto;padding:0 1rem;">
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above; every interpolated value is escaped at its own site. See the note in this docblock on why kses is wrong here.
		echo $body;
		?>
	</main>
	<?php
	get_footer();
	exit;
}
