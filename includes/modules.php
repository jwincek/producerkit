<?php
/**
 * Switching optional modules off from the dashboard.
 *
 * Four modules can be switched off here. Not nine: the rest are either
 * required, or the plugin is largely incoherent without them, and
 * producer-profiles in particular is a vocabulary switch wearing a module's
 * clothes — turning it off would revert every word on a pottery site to a
 * farm's, which is not what a checkbox next to "Pre-Orders" looks like it
 * does.
 *
 * The four chosen are the ones whose absence the rest of the plugin already
 * handles: is_module_active() guards and function_exists() checks exist for
 * them today, because they were always meant to be optional.
 *
 * Three of them own tables holding a customer's name, email and phone, and
 * each owns the only screen that shows them. Switching one off strands real
 * obligations to real people behind a control that looks like tidying. So
 * nothing here happens without first saying what it will cost — see usage() —
 * and nothing here ever deletes anything.
 */

declare(strict_types=1);

namespace ProducerKit\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Option holding the slugs a site has switched off.
 */
const OPTION = 'pkit_disabled_modules';

/**
 * The modules this screen is allowed to touch.
 *
 * @return array<int, string>
 */
function toggleable(): array {
	return [ 'pre-order', 'commissions', 'notifications', 'woocommerce' ];
}

/**
 * Slugs currently switched off.
 *
 * @return array<int, string>
 */
function disabled(): array {
	$stored = (array) get_option( OPTION, [] );

	// Intersected with the toggleable list rather than trusted: a slug that
	// stopped being toggleable should not keep a module off forever, and a
	// stray value in the option should not disable something this screen
	// never offered to disable.
	return array_values( array_intersect( $stored, toggleable() ) );
}

/**
 * Apply the option to the module list.
 *
 * Priority 10, so a site with its own pkit_active_modules filter still wins:
 * theirs runs afterwards and sees this result. Code beats a click, and the
 * filter stays the documented extension point.
 */
add_filter(
	'pkit_active_modules',
	function ( array $active ): array {
		return array_values( array_diff( $active, disabled() ) );
	}
);

/**
 * What a module is holding, in a sentence a producer can act on.
 *
 * Returns the parts rather than a built string so the caller decides the
 * markup, and so the "is anything depending on this" question has an answer
 * that is not a string comparison.
 *
 * @return array{lines: array<int, string>, holds_data: bool}
 */
function usage( string $slug ): array {
	$lines = [];
	$holds = false;

	switch ( $slug ) {
		case 'pre-order':
			if ( function_exists( '\\ProducerKit\\PreOrder\\Orders\\table_name' ) ) {
				$counts = row_counts( \ProducerKit\PreOrder\Orders\table_name(), [ 'pending', 'confirmed' ] );

				if ( $counts['total'] > 0 ) {
					$holds   = true;
					$lines[] = sprintf(
						/* translators: 1: total pre-orders. 2: how many are not yet collected. */
						_n( '%1$d order, %2$d still to collect', '%1$d orders, %2$d still to collect', $counts['total'], 'producerkit' ),
						$counts['total'],
						$counts['open']
					);
				}
			}

			$lines = array_merge( $lines, block_usage( 'producerkit/preorder-form' ) );
			break;

		case 'commissions':
			if ( function_exists( '\\ProducerKit\\Commissions\\Store\\table_name' ) ) {
				$counts = row_counts( \ProducerKit\Commissions\Store\table_name(), [ 'new', 'quoted', 'accepted', 'in_progress' ] );

				if ( $counts['total'] > 0 ) {
					$holds   = true;
					$lines[] = sprintf(
						/* translators: 1: total requests. 2: how many are still open. */
						_n( '%1$d request, %2$d still open', '%1$d requests, %2$d still open', $counts['total'], 'producerkit' ),
						$counts['total'],
						$counts['open']
					);
				}
			}

			$lines = array_merge( $lines, block_usage( 'producerkit/commission-form' ) );
			break;

		case 'notifications':
			// Stores nothing of its own. What it costs is silence: the emails
			// a customer is expecting simply stop.
			$lines[] = __( 'Customers stop receiving email about their orders and requests. Nothing else changes.', 'producerkit' );
			break;

		case 'woocommerce':
			if ( ! class_exists( '\\WooCommerce' ) ) {
				$lines[] = __( 'WooCommerce is not active, so this module is already doing nothing.', 'producerkit' );
				break;
			}

			$lines[] = __( 'Accepted quotes and pre-orders stop being sent to WooCommerce for payment. You would arrange payment directly instead.', 'producerkit' );
			break;
	}

	return [
		'lines'      => $lines,
		'holds_data' => $holds,
	];
}

/**
 * Total rows, and how many are in a status still needing the producer.
 *
 * @param array<int, string> $open_statuses
 * @return array{total: int, open: int}
 */
function row_counts( string $table, array $open_statuses ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the module's own table_name().
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return [
			'total' => 0,
			'open'  => 0,
		];
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table, counted for a one-off admin screen.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

	if ( 0 === $total || ! $open_statuses ) {
		return [
			'total' => $total,
			'open'  => 0,
		];
	}

	$placeholders = implode( ', ', array_fill( 0, count( $open_statuses ), '%s' ) );

	$open = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers cannot be parameterized.
			"SELECT COUNT(*) FROM {$table} WHERE status IN ({$placeholders})",
			...$open_statuses
		)
	);

	return [
		'total' => $total,
		'open'  => $open,
	];
}

/**
 * Published pages carrying one of this module's blocks.
 *
 * Those blocks return nothing at all when their module is off, so a page using
 * one goes blank with no explanation. Worth saying before rather than after.
 *
 * @return array<int, string>
 */
function block_usage( string $block ): array {
	global $wpdb;

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_status = 'publish'
			 AND post_type IN ( 'page', 'post' )
			 AND post_content LIKE %s",
			'%<!-- wp:' . $wpdb->esc_like( $block ) . '%'
		)
	);

	if ( 0 === $count ) {
		return [];
	}

	return [
		sprintf(
			/* translators: %d: number of published pages using the block. */
			_n(
				'%d published page uses its block, and would render nothing there.',
				'%d published pages use its block, and would render nothing there.',
				$count,
				'producerkit'
			),
			$count
		),
	];
}

/**
 * Handle the toggle.
 */
add_action(
	'admin_init',
	function (): void {
		if ( ! isset( $_GET['pkit_module'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$slug = sanitize_key( wp_unslash( $_GET['pkit_module'] ) );

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'pkit_module_' . $slug ) ) {
			return;
		}

		if ( ! in_array( $slug, toggleable(), true ) ) {
			return;
		}

		$off = disabled();

		if ( in_array( $slug, $off, true ) ) {
			$off    = array_values( array_diff( $off, [ $slug ] ) );
			$result = 'on';
		} else {
			$off[]  = $slug;
			$result = 'off';
		}

		update_option( OPTION, array_values( array_unique( $off ) ) );

		// Module boundaries move menus and rewrite rules around. Flushing is
		// cheap here because this happens once, on a click.
		delete_option( 'rewrite_rules' );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'             => 'producerkit',
					'pkit_module_done' => $slug,
					'pkit_module_now'  => $result,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
);

/**
 * The URL that flips one module.
 */
function toggle_url( string $slug ): string {
	return wp_nonce_url(
		add_query_arg(
			[
				'page'        => 'producerkit',
				'pkit_module' => $slug,
			],
			admin_url( 'admin.php' )
		),
		'pkit_module_' . $slug
	);
}
