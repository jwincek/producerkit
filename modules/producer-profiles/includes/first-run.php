<?php
/**
 * Asking the one question the plugin never asked.
 *
 * Walking the getting-started guide against a clean install turned up a gap
 * that was not a bug in any single place: nothing ever prompts a producer to
 * say what they make. Everything downstream is named by that choice — which
 * taxonomies exist, what every field is called, what the menus say, what a
 * made-to-order request is called — and the default is a farm, because that
 * is where the plugin started.
 *
 * So a beekeeper installs it, creates a Farm Stand with Growing / Baking
 * Notes, and never learns that one dropdown would have made it an Apiary with
 * Hive Notes. Nothing is broken. They just quietly get somebody else's words,
 * and the longer they go the more work a switch means re-reading.
 *
 * Deliberately not a wizard, and deliberately not an activation redirect.
 * The steps after this one were checked and they are fine; only the first is
 * missing. And hijacking the page load after activation is both rude and a
 * known irritant in WordPress.org review, which this plugin is about to go
 * through.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles\FirstRun;

use ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/** Set once somebody says they do not want to be asked. */
const DISMISSED = 'pkit_profile_prompt_dismissed';

/** Query var carrying a dismissal. */
const DISMISS_ARG = 'pkit_dismiss_profile_prompt';

/**
 * Has anybody actually chosen a trade?
 *
 * The option is read without its default on purpose. active_slugs() falls back
 * to 'farm', so asking it can never distinguish a site that picked a farm from
 * one that has never been asked — and those are exactly the two cases that
 * need telling apart.
 */
function profile_chosen(): bool {
	return false !== get_option( Profiles\OPTION, false );
}

/**
 * Should the prompt appear at all?
 */
function should_prompt(): bool {
	return ! profile_chosen()
		&& ! get_option( DISMISSED, false )
		&& current_user_can( 'edit_posts' );
}

/**
 * The screens where this is worth saying.
 *
 * Not every admin page. Someone writing a blog post is not being asked what
 * trade they practise, and a notice on every screen is how a plugin teaches
 * people to ignore its notices.
 *
 * @return bool
 */
function on_a_relevant_screen(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();

	if ( ! $screen instanceof \WP_Screen ) {
		return false;
	}

	// The plugin's own pages.
	if ( str_contains( (string) $screen->id, 'producerkit' ) || str_contains( (string) $screen->id, 'pkit-' ) ) {
		return true;
	}

	// And the content it owns, which is where the wording is visible.
	return in_array(
		(string) $screen->post_type,
		[ 'pkit_product', 'pkit_location', 'pkit_event', 'pkit_source' ],
		true
	);
}

add_action( 'admin_init', __NAMESPACE__ . '\\maybe_dismiss' );

/**
 * Record a dismissal.
 *
 * Site-wide rather than per user, because the choice being prompted for is
 * site-wide: once somebody has decided not to set a profile, nagging their
 * colleague about it is asking a question that is not theirs to answer.
 */
function maybe_dismiss(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is checked immediately below; this only decides whether to look.
	if ( ! isset( $_GET[ DISMISS_ARG ] ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	check_admin_referer( DISMISS_ARG );

	update_option( DISMISSED, 1 );

	wp_safe_redirect( remove_query_arg( [ DISMISS_ARG, '_wpnonce' ] ) );
	exit;
}

add_action( 'admin_notices', __NAMESPACE__ . '\\render' );

/**
 * The prompt itself.
 */
function render(): void {
	if ( ! should_prompt() || ! on_a_relevant_screen() ) {
		return;
	}

	$settings = admin_url( 'admin.php?page=pkit-producer-profile' );
	$dismiss  = wp_nonce_url( add_query_arg( DISMISS_ARG, '1' ), DISMISS_ARG );
	?>
	<div class="notice notice-info">
		<h2 style="margin-bottom:.4em;">
			<?php esc_html_e( 'What do you make?', 'producerkit' ); ?>
		</h2>

		<p style="max-width:44em;">
			<?php
			esc_html_e(
				'ProducerKit names things after your trade. Until you choose one it uses a farm’s words, because that is where the plugin started — so a beekeeper sees “Growing / Baking Notes” where they mean hive notes, and a potter sees a “Farm Stand” where they mean a studio.',
				'producerkit'
			);
			?>
		</p>

		<p style="max-width:44em;">
			<?php
			esc_html_e(
				'It is one dropdown, it takes a moment, and it is easier now than after you have typed a season’s worth of products.',
				'producerkit'
			);
			?>
		</p>

		<p>
			<a href="<?php echo esc_url( $settings ); ?>" class="button button-primary">
				<?php esc_html_e( 'Choose your trade', 'producerkit' ); ?>
			</a>
			<a href="<?php echo esc_url( $dismiss ); ?>" class="button button-link" style="margin-left:.5em;">
				<?php esc_html_e( 'I’m a farm — don’t ask again', 'producerkit' ); ?>
			</a>
		</p>
	</div>
	<?php
}
