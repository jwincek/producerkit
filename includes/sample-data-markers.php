<?php
/**
 * Sample Data front-end markers.
 *
 * When sample data is loaded, this file:
 *  1. Adds a "(Sample)" badge next to any post title that has the
 *     _lfuf_sample_data meta — works across all blocks automatically.
 *  2. Shows a site-wide banner on the front end (logged-in editors only)
 *     reminding them sample data is active.
 *  3. Shows a persistent admin notice on all admin pages.
 */

declare(strict_types=1);

namespace Leftfield\SampleData\Markers;

defined( 'ABSPATH' ) || exit;

/**
 * Only activate markers if sample data is loaded.
 */
if ( ! get_option( 'lfuf_sample_data_loaded', false ) ) {
	return;
}

/* ───────────────────────────────────────────────
 * 1. Title badge — appends "(Sample)" to post titles
 *    for any post with the _lfuf_sample_data meta.
 *    Works in both front-end and admin list views.
 * ─────────────────────────────────────────────── */

add_filter(
	'the_title',
	function ( string $title, int $post_id = 0 ): string {
		if ( $post_id < 1 ) {
			return $title;
		}

		// Only tag our custom post types.
		$our_types = [ 'lfuf_product', 'lfuf_source', 'lfuf_location', 'lfuf_event' ];
		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, $our_types, true ) ) {
			return $title;
		}

		if ( ! get_post_meta( $post_id, '_lfuf_sample_data', true ) ) {
			return $title;
		}

		// Don't double-tag if the title already contains "(Sample)".
		if ( str_contains( $title, '(Sample)' ) ) {
			return $title;
		}

		// In the admin, add a plain text marker.
		if ( is_admin() ) {
			return $title . ' (Sample)';
		}

		// On the front end, add a styled badge.
		return $title . ' <span class="lfuf-sample-badge">Sample</span>';
	},
	10,
	2
);

/* ───────────────────────────────────────────────
 * 2. Front-end banner — shown to logged-in editors
 *    at the top of every page.
 * ─────────────────────────────────────────────── */

add_action(
	'wp_footer',
	function (): void {
		// Only show to editors.
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$remove_url = wp_nonce_url(
			admin_url( 'admin.php?page=farm-stand-dashboard&lfuf_sample_action=remove' ),
			'lfuf_sample_action',
		);
		?>
	<div class="lfuf-sample-banner">
		<span class="lfuf-sample-banner__icon">🧪</span>
		<span class="lfuf-sample-banner__text">
			<?php esc_html_e( 'Sample data is active. Content marked "Sample" is test data.', 'producerkit' ); ?>
		</span>
		<a href="<?php echo esc_url( $remove_url ); ?>" class="lfuf-sample-banner__link">
			<?php esc_html_e( 'Remove sample data →', 'producerkit' ); ?>
		</a>
	</div>
		<?php
	}
);

/* ───────────────────────────────────────────────
 * 3. Front-end + admin styles for sample markers.
 * ─────────────────────────────────────────────── */

add_action( 'wp_head', __NAMESPACE__ . '\\output_styles' );
add_action( 'admin_head', __NAMESPACE__ . '\\output_styles' );

function output_styles(): void {
	?>
	<style>
		/* Sample badge on post titles */
		.lfuf-sample-badge {
			display: inline-block;
			font-size: 0.6rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			padding: 0.15rem 0.45rem;
			border-radius: 0.2rem;
			background: #fef3c7;
			color: #92400e;
			border: 1px solid #fcd34d;
			vertical-align: middle;
			margin-left: 0.35rem;
			line-height: 1;
		}

		/* Front-end banner for editors */
		.lfuf-sample-banner {
			position: fixed;
			bottom: 0;
			left: 0;
			right: 0;
			z-index: 99999;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			padding: 0.5rem 1rem;
			background: #fef3c7;
			border-top: 2px solid #fcd34d;
			font-size: 0.85rem;
			color: #92400e;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}

		.lfuf-sample-banner__icon {
			font-size: 1rem;
		}

		.lfuf-sample-banner__link {
			color: #92400e;
			font-weight: 600;
			text-decoration: underline;
		}

		.lfuf-sample-banner__link:hover {
			color: #78350f;
		}

		/* Admin: highlight sample rows in list tables */
		.post-type-lfuf_product tr.type-lfuf_product .row-title:has(~ .lfuf-sample-badge),
		.post-type-lfuf_event tr.type-lfuf_event .row-title:has(~ .lfuf-sample-badge),
		.post-type-lfuf_location tr.type-lfuf_location .row-title:has(~ .lfuf-sample-badge) {
			opacity: 0.7;
		}
	</style>
	<?php
}

/* ───────────────────────────────────────────────
 * 4. Admin notice — persistent reminder on all
 *    admin pages when sample data is loaded.
 * ─────────────────────────────────────────────── */

add_action(
	'admin_notices',
	function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show on the dashboard page itself (it has its own section).
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'toplevel_page_farm-stand-dashboard' ) {
			return;
		}

		$remove_url = wp_nonce_url(
			admin_url( 'admin.php?page=farm-stand-dashboard&lfuf_sample_action=remove' ),
			'lfuf_sample_action',
		);

		printf(
			'<div class="notice notice-warning"><p>🧪 %s <a href="%s">%s</a></p></div>',
			esc_html__( 'ProducerKit sample data is loaded. Remember to remove it before going live.', 'producerkit' ),
			esc_url( $remove_url ),
			esc_html__( 'Remove sample data →', 'producerkit' ),
		);
	}
);
