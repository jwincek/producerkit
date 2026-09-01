<?php
/**
 * Producer profile picker.
 *
 * Switching a profile is deliberately additive: it re-labels the taxonomies
 * and seeds the new trade's vocabulary, but never deletes a term or a
 * product. Switching back restores the previous labels, and any terms that
 * were already in use are still attached to the products that used them.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles\Admin;

use ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/** Set when the profile changes; consumed by a rewrite flush on the next load. */
const FLUSH_FLAG = 'lfuf_producer_profile_flush';

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'admin_post_lfuf_save_producer_profile', __NAMESPACE__ . '\\handle_save' );

function register_page(): void {
	add_submenu_page(
		'farm-stand-dashboard',
		__( 'Producer Profile', 'producerkit' ),
		__( 'Producer Profile', 'producerkit' ),
		'manage_options',
		'lfuf-producer-profile',
		__NAMESPACE__ . '\\render_page'
	);
}

/**
 * Persist the chosen profile.
 *
 * The new profile's taxonomies do not exist yet on this request — they
 * register at `init`, which has already run — so seeding and the rewrite
 * flush both happen on the next load, through the self-healing path core
 * already uses.
 */
function handle_save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to change the producer profile.', 'producerkit' ), 403 );
	}

	check_admin_referer( 'lfuf_save_producer_profile' );

	$slug    = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
	$choices = Profiles\choices();

	if ( ! isset( $choices[ $slug ] ) ) {
		wp_safe_redirect( add_query_arg( 'lfuf_profile', 'invalid', menu_url() ) );
		exit;
	}

	$changed = $slug !== Profiles\active_slug();
	update_option( Profiles\OPTION, $slug );

	if ( $changed ) {
		// Taxonomy rewrite slugs come and go with the profile.
		update_option( FLUSH_FLAG, 1 );
	}

	wp_safe_redirect( add_query_arg( 'lfuf_profile', $changed ? 'changed' : 'unchanged', menu_url() ) );
	exit;
}

/**
 * Flush rewrites once, after the new profile's taxonomies have registered.
 */
function maybe_flush(): void {
	if ( get_option( FLUSH_FLAG ) ) {
		delete_option( FLUSH_FLAG );
		flush_rewrite_rules( false );
	}
}

function menu_url(): string {
	return admin_url( 'admin.php?page=lfuf-producer-profile' );
}

function render_page(): void {
	$active = Profiles\active_slug();

	/*
	 * Not form input: this is the redirect marker set by handle_save(), which
	 * did verify a nonce. It only selects which canned notice to print, and is
	 * compared against literals after sanitize_key().
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = isset( $_GET['lfuf_profile'] ) ? sanitize_key( wp_unslash( $_GET['lfuf_profile'] ) ) : '';

	$optional = Profiles\optional_taxonomies();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Producer Profile', 'producerkit' ); ?></h1>

		<?php if ( 'changed' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Profile updated. New terms have been added; nothing was removed.', 'producerkit' ); ?>
			</p></div>
		<?php elseif ( 'invalid' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p>
				<?php esc_html_e( 'That profile does not exist.', 'producerkit' ); ?>
			</p></div>
		<?php endif; ?>

		<p class="description" style="max-width:44em">
			<?php esc_html_e( 'Your profile decides what the product fields are called and which vocabulary is suggested. Switching only adds — existing terms and products are never removed, so you can change your mind.', 'producerkit' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lfuf_save_producer_profile">
			<?php wp_nonce_field( 'lfuf_save_producer_profile' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ( Profiles\choices() as $slug => $label ) : ?>
					<?php $profile = Profiles\get( $slug ); ?>
					<tr>
						<th scope="row" style="padding-bottom:0">
							<label for="lfuf-profile-<?php echo esc_attr( $slug ); ?>">
								<input
									type="radio"
									name="profile"
									id="lfuf-profile-<?php echo esc_attr( $slug ); ?>"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( $slug, $active ); ?>
								>
								<strong><?php echo esc_html( $label ); ?></strong>
							</label>
						</th>
						<td style="padding-bottom:0">
							<p style="margin:0"><?php echo esc_html( (string) $profile['description'] ); ?></p>
							<?php
							$fields = [];
							foreach ( (array) $profile['taxonomies'] as $taxonomy ) {
								if ( ! isset( $optional[ $taxonomy ] ) ) {
									continue;
								}
								$names    = $profile['names'][ $taxonomy ] ?? $optional[ $taxonomy ];
								$fields[] = (string) $names[0];
							}
							?>
							<p class="description" style="margin:.25em 0 0">
								<?php
								if ( $fields ) {
									printf(
										/* translators: %s: comma-separated list of extra product field names. */
										esc_html__( 'Adds fields: %s', 'producerkit' ),
										esc_html( implode( ', ', $fields ) )
									);
								} else {
									esc_html_e( 'No extra product fields.', 'producerkit' );
								}
								?>
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Profile', 'producerkit' ) ); ?>
		</form>
	</div>
	<?php
}
