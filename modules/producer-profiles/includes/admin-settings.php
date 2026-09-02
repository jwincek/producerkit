<?php
/**
 * Producer profile picker.
 *
 * Two settings that look similar and are not:
 *
 *   Site      which trades this site practises. Decides which optional
 *             fields exist and which vocabulary is seeded. Both union, and
 *             both are physical facts about the install, so this is one
 *             answer for everybody. Needs manage_options.
 *   Personal  which of those trades' words you read the admin in. Labels
 *             cannot union — there is one Material field and one label — but
 *             a label is display, so two people sharing an install can each
 *             see their own. Anyone who can edit content sets their own.
 *
 * Switching a profile on is additive: it seeds the new trade's vocabulary and
 * never deletes a term or a product. Switching one off leaves everything
 * where it is; only the offer of that vocabulary goes away.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles\Admin;

use ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/** Set when the site's profiles change; consumed by a rewrite flush next load. */
const FLUSH_FLAG = 'lfuf_producer_profile_flush';

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'admin_post_lfuf_save_producer_profile', __NAMESPACE__ . '\\handle_save' );

function register_page(): void {
	add_submenu_page(
		'farm-stand-dashboard',
		__( 'Producer Profile', 'producerkit' ),
		__( 'Producer Profile', 'producerkit' ),
		'edit_posts',
		'lfuf-producer-profile',
		__NAMESPACE__ . '\\render_page'
	);
}

function menu_url(): string {
	return admin_url( 'admin.php?page=lfuf-producer-profile' );
}

/**
 * Persist whichever of the two settings the current user may change.
 */
function handle_save(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to change producer profiles.', 'producerkit' ), 403 );
	}

	check_admin_referer( 'lfuf_save_producer_profile' );

	$choices = Profiles\choices();
	$notice  = 'saved';

	// ── Site: which trades this install practises. ──
	if ( current_user_can( 'manage_options' ) && isset( $_POST['site_profiles'] ) ) {
		$requested = array_values(
			array_intersect(
				array_map( 'sanitize_key', wp_unslash( (array) $_POST['site_profiles'] ) ),
				array_keys( $choices )
			)
		);

		// Refuse to leave the site with none: every field would revert and the
		// admin would look broken for reasons nobody could see.
		if ( ! $requested ) {
			wp_safe_redirect( add_query_arg( 'lfuf_profile', 'none', menu_url() ) );
			exit;
		}

		if ( $requested !== Profiles\active_slugs() ) {
			update_option( Profiles\OPTION, $requested );
			// Taxonomy rewrite slugs come and go with the set.
			update_option( FLUSH_FLAG, 1 );
			$notice = 'changed';
		}
	}

	// ── Personal: whose words this person reads. ──
	$mine = isset( $_POST['my_profile'] ) ? sanitize_key( wp_unslash( $_POST['my_profile'] ) ) : '';

	if ( '' === $mine || ! isset( $choices[ $mine ] ) ) {
		delete_user_meta( get_current_user_id(), Profiles\USER_META );
	} else {
		update_user_meta( get_current_user_id(), Profiles\USER_META, $mine );
	}

	wp_safe_redirect( add_query_arg( 'lfuf_profile', $notice, menu_url() ) );
	exit;
}

/**
 * Flush rewrites once, after the new set of taxonomies has registered.
 */
function maybe_flush(): void {
	if ( get_option( FLUSH_FLAG ) ) {
		delete_option( FLUSH_FLAG );
		flush_rewrite_rules( false );
	}
}

function render_page(): void {
	$active   = Profiles\active_slugs();
	$mine     = Profiles\user_slug( get_current_user_id() );
	$optional = Profiles\optional_taxonomies();
	$can_site = current_user_can( 'manage_options' );

	/*
	 * Not form input: the redirect marker set by handle_save(), which did
	 * verify a nonce. It only selects which canned notice to print.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = isset( $_GET['lfuf_profile'] ) ? sanitize_key( wp_unslash( $_GET['lfuf_profile'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Producer Profile', 'producerkit' ); ?></h1>

		<?php if ( 'changed' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Updated. New vocabulary has been added; nothing was removed.', 'producerkit' ); ?>
			</p></div>
		<?php elseif ( 'saved' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Saved.', 'producerkit' ); ?>
			</p></div>
		<?php elseif ( 'none' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p>
				<?php esc_html_e( 'Pick at least one trade — with none, every field loses its name.', 'producerkit' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lfuf_save_producer_profile">
			<?php wp_nonce_field( 'lfuf_save_producer_profile' ); ?>

			<?php if ( $can_site ) : ?>
				<h2><?php esc_html_e( 'What this site makes', 'producerkit' ); ?></h2>
				<p class="description" style="max-width:44em">
					<?php esc_html_e( 'Tick every trade practised here. This decides which product fields exist and which vocabulary is suggested. Ticking more only ever adds — existing terms and products are never removed.', 'producerkit' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( Profiles\choices() as $slug => $label ) : ?>
						<?php $profile = Profiles\get( $slug ); ?>
						<tr>
							<th scope="row" style="padding-bottom:0">
								<label for="lfuf-site-<?php echo esc_attr( $slug ); ?>">
									<input
										type="checkbox"
										name="site_profiles[]"
										id="lfuf-site-<?php echo esc_attr( $slug ); ?>"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $active, true ) ); ?>
									>
									<strong><?php echo esc_html( $label ); ?></strong>
								</label>
							</th>
							<td style="padding-bottom:0">
								<p style="margin:0"><?php echo esc_html( (string) $profile['description'] ); ?></p>
								<?php
								$fields = [];
								foreach ( (array) $profile['taxonomies'] as $taxonomy ) {
									if ( isset( $optional[ $taxonomy ] ) ) {
										$names    = $profile['names'][ $taxonomy ] ?? $optional[ $taxonomy ];
										$fields[] = (string) $names[0];
									}
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
			<?php endif; ?>

			<h2><?php esc_html_e( 'What you call things', 'producerkit' ); ?></h2>
			<p class="description" style="max-width:44em">
				<?php esc_html_e( 'When a site practises more than one trade the fields are shared, but the words need not be. Pick the trade you work in and the admin uses its vocabulary for you — everyone else keeps theirs.', 'producerkit' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="lfuf-my-profile"><?php esc_html_e( 'My vocabulary', 'producerkit' ); ?></label>
						</th>
						<td>
							<select name="my_profile" id="lfuf-my-profile">
								<option value=""><?php esc_html_e( '— Follow the site —', 'producerkit' ); ?></option>
								<?php foreach ( $active as $slug ) : ?>
									<?php $profile = Profiles\get( $slug ); ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $mine ); ?>>
										<?php echo esc_html( (string) $profile['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( count( $active ) < 2 ) : ?>
								<p class="description">
									<?php esc_html_e( 'Only matters once the site practises more than one trade.', 'producerkit' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save', 'producerkit' ) ); ?>
		</form>
	</div>
	<?php
}
